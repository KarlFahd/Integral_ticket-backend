<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BulkReplyToTicketsTool;
use App\Mcp\Tools\CreateEventTool;
use App\Mcp\Tools\CreateTicketTool;
use App\Mcp\Tools\CreateUserTool;
use App\Mcp\Tools\DeleteEventTool;
use App\Mcp\Tools\GetEventDetailTool;
use App\Mcp\Tools\GetTicketDetailTool;
use App\Mcp\Tools\ListEventsTool;
use App\Mcp\Tools\ListTicketsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\ReplyToTicketTool;
use App\Mcp\Tools\UpdateEventTool;
use App\Mcp\Tools\UpdateTicketPriorityTool;
use App\Mcp\Tools\UpdateTicketStatusTool;
use Laravel\Mcp\Server;

/**
 * The public, OAuth-authenticated MCP server — reachable by external clients
 * like Claude.ai Connectors once a real Integral Chip user logs in (see
 * routes/ai.php and AppServiceProvider::boot()'s Passport::authorizationView).
 *
 * Every tool here is a thin wrapper around App\Services\Mcp\TicketMcpTools —
 * the exact same class the internal in-app bot uses (see McpController). No
 * business logic, validation, or role-gating is duplicated; each tool just
 * pulls the real, Passport-authenticated user off the request and forwards
 * to TicketMcpTools::call(), so this server has the same capabilities and the
 * same permission rules as the bot, just driven by a real login instead of
 * a shared-secret header.
 */
class IntegralChipServer extends Server
{
    protected string $name = 'Integral Chip';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Tools for the Integral Chip support ticket system, acting on behalf of
        whichever Integral Chip user is currently logged in. Ticket and event
        tools apply to that user's own account; user-management tools are only
        available to HR/Admin accounts and are hidden otherwise. There is no
        navigate or theme-switching tool here — this connector has no access
        to the user's browser, so those only exist in the in-app bot.
        MARKDOWN;

    protected array $tools = [
        CreateTicketTool::class,
        ListTicketsTool::class,
        GetTicketDetailTool::class,
        UpdateTicketStatusTool::class,
        UpdateTicketPriorityTool::class,
        ReplyToTicketTool::class,
        BulkReplyToTicketsTool::class,
        CreateEventTool::class,
        ListEventsTool::class,
        GetEventDetailTool::class,
        UpdateEventTool::class,
        DeleteEventTool::class,
        CreateUserTool::class,
        ListUsersTool::class,
    ];
}
