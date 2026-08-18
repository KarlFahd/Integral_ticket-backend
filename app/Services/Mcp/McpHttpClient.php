<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * McpHttpClient — the MCP client side of our bot.
 *
 * MCP (Model Context Protocol) is a standard wire protocol for AI models to
 * discover and call tools. The wire format is JSON-RPC 2.0: every request has
 * { jsonrpc, id, method, params } and every response has { jsonrpc, id, result }.
 *
 * This class wraps the two MCP methods BotService actually needs:
 *   - tools/list  → "what tools can I call and what are their argument schemas?"
 *   - tools/call  → "call this tool with these arguments and give me the result"
 *
 * There's no official PHP MCP SDK yet, so we hand-roll these two calls as plain
 * HTTP POST requests to our own McpController endpoint. If a second MCP server
 * is ever added (e.g. a pre-built Google Calendar MCP), you'd just create a
 * second instance of this class pointed at a different base URL — nothing here
 * is specific to tickets.
 *
 * Auth note: MCP's spec doesn't define authentication. We use a shared secret
 * (X-Mcp-Secret header) plus the current user's identity (X-Mcp-Username /
 * X-Mcp-Is-Admin / X-Mcp-Is-Hr) so the MCP server can apply the same
 * role-based access rules our normal API controllers use.
 */
class McpHttpClient
{
    public function __construct(
        private readonly string $baseUrl,  // e.g. "http://localhost:8000/api/mcp"
        private readonly string $secret,   // shared with McpController via config('services.mcp.secret')
    ) {}

    /**
     * Ask the MCP server which tools exist and what their argument schemas are.
     * Called once at the start of every BotService::chat() call.
     *
     * Returns an array of MCP tool definitions, each shaped like:
     *   { name, description, inputSchema: { type, properties, required } }
     *
     * The server (TicketMcpTools::definitions()) filters this list by role —
     * create_user and list_users only appear for Admin and HR users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTools(string $username, bool $isAdmin, bool $isHr): array
    {
        $response = $this->call('tools/list', [], $username, $isAdmin, $isHr);

        return $response['tools'] ?? [];
    }

    /**
     * Execute a specific tool on the MCP server with the given arguments.
     * Called once per tool call Gemini decides to make during the agentic loop.
     *
     * Returns MCP's standard result shape plus our non-standard "action" field:
     *   {
     *     content: [{ type: "text", text: "..." }],   // what to show/tell Gemini
     *     action:  { type: "navigate", path: "..." }  // optional frontend side-effect
     *   }
     *
     * @return array{content: array<int, array{type: string, text: string}>, action: ?array<string, mixed>}
     */
    public function callTool(string $name, array $args, string $username, bool $isAdmin, bool $isHr): array
    {
        return $this->call('tools/call', ['name' => $name, 'arguments' => $args], $username, $isAdmin, $isHr);
    }

    /**
     * Send a single JSON-RPC 2.0 request to the MCP server and return the
     * "result" field of the response.
     *
     * JSON-RPC 2.0 wire format:
     *   Request:  { "jsonrpc": "2.0", "id": "<uuid>", "method": "...", "params": {...} }
     *   Response: { "jsonrpc": "2.0", "id": "<uuid>", "result": {...} }
     *             or { "jsonrpc": "2.0", "id": "<uuid>", "error": { code, message } }
     *
     * The UUID id is how JSON-RPC matches responses to requests when many
     * requests fly in parallel — not relevant here (we're synchronous) but
     * required by the spec.
     *
     * @param array<string, mixed> $params
     */
    private function call(string $method, array $params, string $username, bool $isAdmin, bool $isHr): array
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    // Shared secret so McpController rejects requests from anything
                    // other than our own BotService.
                    'X-Mcp-Secret'   => $this->secret,
                    // Thread the caller's identity through to the MCP server.
                    // MCP's spec has no opinion on "who is the user" — this is
                    // our own convention, needed because tool results are role-scoped
                    // (an employee can't list_users, an admin can see all tickets, etc.).
                    'X-Mcp-Username' => $username,
                    'X-Mcp-Is-Admin' => $isAdmin ? 'true' : 'false',
                    'X-Mcp-Is-Hr'    => $isHr ? 'true' : 'false',
                ])
                ->post($this->baseUrl, [
                    'jsonrpc' => '2.0',
                    'id'      => (string) Str::uuid(),
                    'method'  => $method,
                    'params'  => $params,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // The MCP server process itself is unreachable (not running, wrong
            // port, etc.) — same failure class callGemini() already handles for
            // the Gemini API. Degrade to an empty result instead of a raw 500,
            // so BotService can still return a normal chat message.
            Log::warning('McpHttpClient: could not connect to MCP server', [
                'method' => $method,
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('McpHttpClient: request failed', [
                'method' => $method,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [];
        }

        // JSON-RPC uses an "error" field (not HTTP status) for application-level errors.
        if ($response->json('error') !== null) {
            Log::warning('McpHttpClient: MCP server returned an error', [
                'method' => $method,
                'error'  => $response->json('error'),
            ]);

            return [];
        }

        // Unwrap the JSON-RPC envelope — the caller only needs the payload inside "result".
        return (array) $response->json('result', []);
    }
}
