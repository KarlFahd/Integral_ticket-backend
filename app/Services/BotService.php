<?php

namespace App\Services;

use App\Services\Mcp\McpHttpClient;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BotService — the agentic loop orchestrator.
 *
 * High-level flow for every user message:
 *   1. Ask the MCP server (McpController) which tools exist.        → listTools()
 *   2. Translate those tool schemas from MCP format to Gemini format. → mcpToolsToGeminiDeclarations()
 *   3. Send the full conversation + tools to Gemini.               → callGemini()
 *   4. If Gemini wants to call a tool:
 *        a. Execute the tool via the MCP server.                   → mcpClient->callTool()
 *        b. Append the tool result to the conversation.
 *        c. Go back to step 3.
 *   5. If Gemini writes a plain text reply → return it to the caller.
 *
 * This is called an "agentic loop" because the model *acts* (calls tools,
 * reads results, decides the next step) instead of just responding in one shot.
 */
class BotService
{
    // Free-tier Gemini models, tried in order — see https://ai.google.dev/gemini-api/docs/pricing
    // "lite" first: this bot only fills structured fields, doesn't need heavy reasoning,
    // and in testing it had far more free-tier capacity available than gemini-3.5-flash.
    private const MODELS = ['gemini-3.1-flash-lite', 'gemini-3.5-flash'];

    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // Safety cap: if the model hasn't produced a final text reply after 6 tool
    // calls, something is wrong (bad tool descriptions, unexpected model behavior).
    // We stop and return a fallback message rather than looping forever.
    private const MAX_ITERATIONS = 6;

    public function __construct(
        private readonly McpHttpClient $mcpClient,
    ) {}

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{reply: string, actions: array<int, array<string, mixed>>}
     */
    public function chat(array $messages, string $username, bool $isAdmin, bool $isHr): array
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            return [
                'reply' => "Integral Bot isn't configured yet — an administrator needs to add a Gemini API key to the backend.",
                'actions' => [],
            ];
        }

        // Step 1: Ask our MCP server which tools the current user is allowed to use.
        // The MCP server (McpController → TicketMcpTools::definitions()) filters
        // the list by role — HR/Admin get create_user and list_users, others don't.
        $tools = $this->mcpToolsToGeminiDeclarations(
            $this->mcpClient->listTools($username, $isAdmin, $isHr)
        );

        // Step 2: Convert our app's message format {role, content} to Gemini's
        // format {role, parts: [{text}]} and rename "assistant" → "model".
        $contents = $this->toGeminiContents($messages);

        // Actions are side-channel instructions for the frontend (navigate / theme).
        // We collect them across ALL iterations, not just the last one, because
        // a single request could theoretically call navigate AND toggle_theme.
        $actions = [];

        $systemPrompt = $this->buildSystemPrompt($username, $isAdmin, $isHr);

        // The agentic loop: each iteration = one round-trip to Gemini.
        // Normal conversations finish in 1-2 iterations.
        // Complex multi-step requests (e.g. "create a ticket AND open the tickets page")
        // might use 2-3. We cap at 6 to prevent runaway loops.
        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $response = $this->callGemini($contents, $tools, $systemPrompt, $apiKey);

            if ($response === null) {
                return [
                    'reply' => "I couldn't reach Gemini right now — it may be busy or unreachable. Please try again in a moment.",
                    'actions' => $actions,
                ];
            }

            if ($response->failed()) {
                Log::warning('BotService: Gemini request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'reply' => 'Integral Bot ran into a problem talking to Gemini. Please try again in a moment.',
                    'actions' => $actions,
                ];
            }

            // Gemini's response is a "candidates" array. We always take [0].
            // "parts" contains either text blocks OR functionCall blocks (never both at once).
            $parts = $response->json('candidates.0.content.parts', []);

            if ($parts === []) {
                return [
                    'reply' => "I couldn't generate a response to that — could you rephrase?",
                    'actions' => $actions,
                ];
            }

            // Split into function calls vs. text — if there are no function calls,
            // Gemini is done thinking and has a final answer for the user.
            $functionCalls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if ($functionCalls === []) {
                // EXIT: Gemini wrote a plain reply — extract the text and return it.
                return ['reply' => $this->extractText($parts), 'actions' => $actions];
            }

            // CONTINUE: Gemini wants to call tools. Append Gemini's "I want to call
            // these tools" message to the conversation, then execute each tool.
            $contents[] = ['role' => 'model', 'parts' => $this->fixEmptyFunctionCallArgs($parts)];

            $responseParts = [];

            foreach ($functionCalls as $part) {
                $name = (string) ($part['functionCall']['name'] ?? '');
                $args = (array) ($part['functionCall']['args'] ?? []);

                // Execute the tool via the MCP server (JSON-RPC tools/call).
                // The result is {content: [{type, text}], action: ?{type, ...}}.
                $result = $this->mcpClient->callTool($name, $args, $username, $isAdmin, $isHr);

                // "action" is our app's non-standard extension to MCP — it carries
                // frontend instructions (navigate, theme) that the text reply alone can't.
                $resultText = $result['content'][0]['text'] ?? "The {$name} tool didn't return a result.";
                $action = $result['action'] ?? null;

                if ($action !== null) {
                    $actions[] = $action;
                }

                // Tell Gemini what the tool returned so it can decide what to do next.
                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => ['result' => $resultText],
                    ],
                ];
            }

            // Append the tool results as a "user" turn (Gemini's convention —
            // tool results always come from the "user" role in its multi-turn format).
            $contents[] = ['role' => 'user', 'parts' => $responseParts];

            // Now loop back: Gemini will read the tool results and either call more
            // tools or produce its final text reply.
        }

        // If we exit the loop without Gemini finishing, it means 6 tool calls happened
        // and it still hasn't written a text reply — something went wrong.
        return [
            'reply' => "That's taking more steps than expected — could you try rephrasing your request?",
            'actions' => $actions,
        ];
    }

    /**
     * Tries each model in self::MODELS in order, moving to the next one only
     * when a model reports 503 (overloaded) or the connection itself fails.
     * Returns null only if every model in the list failed.
     *
     * The two-model strategy is purely a free-tier availability workaround:
     * gemini-3.1-flash-lite has very generous limits but can still go 503 under
     * heavy load. gemini-3.5-flash is the fallback — slower free-tier limits
     * but more reliable when the lite model is busy.
     *
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function callGemini(array $contents, array $tools, string $systemPrompt, string $apiKey): ?\Illuminate\Http\Client\Response
    {
        foreach (self::MODELS as $model) {
            try {
                $response = Http::connectTimeout(8)
                    ->timeout(20)
                    // HTTP/2 negotiation hangs for larger POST bodies on some Windows
                    // dev setups (curl/nghttp2 + AV/firewall inspection). Force 1.1.
                    ->withOptions(['curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]])
                    ->retry(2, 400, throw: false)
                    ->post(
                        self::API_BASE.$model.':generateContent?key='.$apiKey,
                        [
                            'contents' => $contents,
                            // Wrap tools in "functionDeclarations" — Gemini's required shape.
                            'tools' => [['functionDeclarations' => $tools]],
                            // systemInstruction is sent separately from contents;
                            // Gemini treats it as always-visible context that doesn't
                            // appear in the conversation history the model echoes back.
                            'systemInstruction' => ['parts' => ['text' => $systemPrompt]],
                            'generationConfig' => ['maxOutputTokens' => 1024],
                        ],
                    );
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Network-level failure (DNS, refused connection) — try next model.
                continue;
            }

            // Model overloaded — try the next one in the list instead of failing outright.
            if ($response->status() === 503) {
                continue;
            }

            return $response;
        }

        return null;
    }

    /**
     * Gemini uses "user" / "model" roles, but our app (and MCP) uses the
     * OpenAI convention "user" / "assistant". Rename "assistant" → "model"
     * and wrap the text in the parts array Gemini expects.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function toGeminiContents(array $messages): array
    {
        return array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string) $m['content']]],
        ], $messages);
    }

    /**
     * Pre-existing bug, unrelated to MCP: when Gemini calls a tool with no
     * arguments, it sends `args: {}`. PHP's JSON decoder has no separate
     * empty-object type, so that becomes `[]` — and json_encode(`[]`)
     * produces `[]`, not `{}`, when we echo the call back a turn later.
     * Gemini's API then rejects it ("Proto field is not repeating, cannot
     * start list"). Force any empty args array back into `{}` before it's
     * re-sent.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function fixEmptyFunctionCallArgs(array $parts): array
    {
        return array_map(function (array $part) {
            if (isset($part['functionCall']['args']) && $part['functionCall']['args'] === []) {
                $part['functionCall']['args'] = new \stdClass;
            }

            return $part;
        }, $parts);
    }

    /** @param array<int, array<string, mixed>> $parts */
    private function extractText(array $parts): string
    {
        $text = collect($parts)->pluck('text')->filter()->implode('');

        return $text !== '' ? $text : "I'm not sure how to respond to that.";
    }

    /**
     * The system prompt is Gemini's "personality brief" — it's sent on every
     * request (outside of the normal conversation history) and tells the model
     * who it is, who the current user is, and what rules to follow.
     * Injecting username + role here means the model won't invent tickets or
     * users that the current user can't actually see.
     */
    private function buildSystemPrompt(string $username, bool $isAdmin, bool $isHr): string
    {
        $role = $isAdmin ? 'Admin/Agent' : ($isHr ? 'HR' : 'Employee');

        // create_event needs a real YYYY-MM-DD date, but users say things like
        // "tomorrow" or "next Monday" — the model has no other way to know
        // what "today" actually is, so it's injected here explicitly.
        $tz = config('services.google_calendar.timezone');
        $today = now($tz)->translatedFormat('l, F j, Y');

        // Generated from TicketMcpTools's constants rather than typed out
        // here a second time — those constants are the single source of
        // truth for these four value lists across every MCP surface, so
        // this prompt can't quietly drift out of sync with the real schema.
        $categories = implode(', ', TicketMcpTools::CATEGORIES);
        $priorities = implode(', ', TicketMcpTools::PRIORITIES);
        $statuses = implode(', ', TicketMcpTools::STATUSES);
        $eventTypes = implode(', ', TicketMcpTools::EVENT_TYPES);

        return <<<PROMPT
        You are "Integral Bot", a helpful in-app assistant for the Integral support ticket system.
        The current user is "{$username}", role: {$role}.
        Today's date is {$today} (timezone: {$tz}).

        Rules:
        - Only use the tools you've been given. If you weren't given a tool for something, say you can't do that — never claim to have done something you didn't actually do.
        - If a tool needs information the user hasn't given you, ask one short, specific clarifying question instead of guessing or calling the tool with made-up values.
        - Ticket categories are exactly: {$categories}.
        - Ticket priorities are exactly: {$priorities}.
        - Ticket statuses are exactly: {$statuses}.
        - Calendar event types are exactly: {$eventTypes}.
        - When creating a calendar event, work out the actual date yourself from today's date above before calling create_event — never pass relative words like "tomorrow" as the date.
        - Never invent ticket IDs, usernames, or data — only reference what tool results actually return to you.
        - Keep replies short and conversational (one to three sentences), not a report.
        - You only see and act on what the current user is allowed to see and act on in the app itself — don't apologize for that, just work within it.
        PROMPT;
    }

    /**
     * MCP and Gemini both describe tools using JSON Schema, but they disagree
     * on one field name: MCP calls the argument schema "inputSchema", Gemini
     * calls it "parameters". This function renames that one field.
     *
     * This is the ONLY Gemini-specific code in the whole MCP layer. If we ever
     * switch to a different AI model (Claude, GPT-4, ...), this is the one
     * function to update — everything else (McpHttpClient, McpController,
     * TicketMcpTools) is model-agnostic standard MCP.
     *
     * @param  array<int, array<string, mixed>>  $mcpTools
     * @return array<int, array<string, mixed>>
     */
    private function mcpToolsToGeminiDeclarations(array $mcpTools): array
    {
        return array_map(function (array $tool) {
            $schema = $tool['inputSchema'];

            // Same empty-object-vs-empty-array issue as
            // fixEmptyFunctionCallArgs(), on a different seam: a no-argument
            // tool's `properties: {}` gets decoded into PHP `[]` by
            // McpHttpClient (Laravel's Response::json() uses associative-
            // array mode), then re-encoded as `[]` instead of `{}` when we
            // send it on to Gemini, which rejects it as "Cannot bind a list
            // to map for field 'properties'". Force it back to an object.
            if (isset($schema['properties']) && $schema['properties'] === []) {
                $schema['properties'] = new \stdClass;
            }

            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                // Rename: MCP "inputSchema" → Gemini "parameters"
                'parameters' => $schema,
            ];
        }, $mcpTools);
    }
}
