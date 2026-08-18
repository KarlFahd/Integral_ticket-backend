<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Mcp\TicketMcpTools;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Shared by every tool in app/Mcp/Tools — each tool is a thin wrapper that
 * forwards to TicketMcpTools::call() (the same class the internal bot uses),
 * scoped to whichever real user Passport authenticated this request as.
 */
trait CallsTicketMcpTools
{
    public function __construct(private readonly TicketMcpTools $tools) {}

    protected function respond(Request $request, string $toolName): Response
    {
        $user = $request->user();

        $result = $this->tools->call(
            $toolName,
            $request->all(),
            $user->username,
            (bool) $user->is_admin,
            (bool) $user->is_hr,
        );

        return Response::text($result['content'][0]['text']);
    }
}
