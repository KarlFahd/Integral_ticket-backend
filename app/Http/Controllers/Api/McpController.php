<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * McpController — the MCP server's transport layer.
 *
 * What is MCP? Model Context Protocol is an open standard (created by Anthropic)
 * that lets AI models discover and call tools through a common wire format.
 * Think of it as "USB for AI tools": any model that speaks MCP can plug into any
 * MCP server without any model-specific glue code.
 *
 * This controller IS the MCP server for this app. It implements the "Streamable
 * HTTP" transport: a single POST endpoint that speaks JSON-RPC 2.0. External
 * MCP clients (Claude Desktop, Anthropic's managed agents, our own BotService)
 * can all POST here and get a valid MCP response.
 *
 * Responsibilities here are purely about the WIRE PROTOCOL:
 *   - Validate the shared secret (auth — MCP has no built-in auth mechanism).
 *   - Parse the JSON-RPC envelope (jsonrpc, id, method, params).
 *   - Route method names to the right handler.
 *   - Wrap the result in a JSON-RPC response envelope.
 *
 * Zero ticket / user domain logic lives here — all of that is in TicketMcpTools.
 */
class McpController extends Controller
{
    public function handle(Request $request, TicketMcpTools $tools): JsonResponse
    {
        // Auth: MCP's spec doesn't define authentication, so we use a shared
        // secret header. hash_equals() is timing-safe — it prevents timing
        // attacks where an attacker could guess the secret character by character
        // based on how long the comparison takes.
        if (! hash_equals((string) config('services.mcp.secret'), (string) $request->header('X-Mcp-Secret'))) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $request->input('id'),
                'error'   => ['code' => -32001, 'message' => 'Unauthorized'],
            ], 401);
        }

        $id     = $request->input('id');
        $method = (string) $request->input('method');
        $params = (array) $request->input('params', []);

        // Extract the caller's identity from headers. MCP itself has no concept
        // of "app users" — we thread it through as custom headers on every call
        // so TicketMcpTools can enforce the same role-based access rules that
        // the normal API controllers use (see McpHttpClient for where these are set).
        $username = (string) $request->header('X-Mcp-Username', '');
        $isAdmin  = filter_var($request->header('X-Mcp-Is-Admin', 'false'), FILTER_VALIDATE_BOOLEAN);
        $isHr     = filter_var($request->header('X-Mcp-Is-Hr', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Route MCP method names to their handlers.
        // MCP defines three standard methods; we implement all three:
        //   - initialize  → handshake: tell the client our name/version/capabilities
        //   - tools/list  → return the list of available tools + their schemas
        //   - tools/call  → execute a specific tool and return the result
        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2025-06-18',
                'serverInfo'      => ['name' => 'integral-ticket-mcp', 'version' => '1.0.0'],
                // 'tools' capability advertises that this server has callable tools.
                // The empty stdClass serializes to {} (required by MCP spec, not []).
                'capabilities' => ['tools' => new \stdClass],
            ],
            'tools/list' => ['tools' => $tools->definitions($isAdmin, $isHr)],
            'tools/call' => $tools->call(
                (string) ($params['name'] ?? ''),
                (array) ($params['arguments'] ?? []),
                $username,
                $isAdmin,
                $isHr,
            ),
            default => null,
        };

        // JSON-RPC error code -32601 = "Method not found" (part of the spec).
        if ($result === null) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $id,
                'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
            ]);
        }

        // Wrap the result in the standard JSON-RPC response envelope.
        // The "id" echoed back must match the request's "id" — that's how
        // JSON-RPC lets clients match async responses to their requests.
        return response()->json([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }
}
