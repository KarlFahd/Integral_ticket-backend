<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mcp\PublicMcpTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PublicMcpController — the PUBLIC, unauthenticated MCP endpoint, meant to
 * be reached from outside this app entirely (e.g. a Claude.ai custom
 * connector, over a Cloudflare tunnel). This is deliberately a separate
 * endpoint from McpController (`/api/mcp`), not a public mode on the same
 * one:
 *
 *   - McpController  (`/api/mcp`)         → internal only, shared-secret
 *     protected, full 11-tool read/write set (TicketMcpTools), used by our
 *     own BotService.
 *   - PublicMcpController (`/api/mcp/public`) → no secret, no identity
 *     headers, only the two read-only tools in PublicMcpTools.
 *
 * Why no auth check here at all: Claude.ai's custom connectors only
 * support either full OAuth or no authentication on a normal account (the
 * "attach a header/API key" option is beta-gated). Implementing OAuth was
 * an explicit non-goal for this pass — so this endpoint has nothing to
 * check, and is safe to leave that way ONLY because PublicMcpTools never
 * exposes anything beyond reading ticket data. If this class ever grows a
 * tool that changes data, it needs real authentication first.
 */
class PublicMcpController extends Controller
{
    public function handle(Request $request, PublicMcpTools $tools): JsonResponse
    {
        $id = $request->input('id');
        $method = (string) $request->input('method');
        $params = (array) $request->input('params', []);

        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2025-06-18',
                'serverInfo' => ['name' => 'integral-ticket-mcp-public', 'version' => '1.0.0'],
                'capabilities' => ['tools' => new \stdClass],
            ],
            'tools/list' => ['tools' => $tools->definitions()],
            'tools/call' => $tools->call(
                (string) ($params['name'] ?? ''),
                (array) ($params['arguments'] ?? []),
            ),
            default => null,
        };

        if ($result === null) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => "Method not found: {$method}"],
            ]);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }
}
