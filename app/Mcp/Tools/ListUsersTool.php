<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListUsersTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'list_users';

    protected string $description = 'List all user accounts and their roles. Only available to HR and Admin roles.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    // Only surfaced to HR/Admin — mirrors TicketMcpTools::definitions()'s own role gating,
    // now enforced against the real Passport-authenticated user instead of a header.
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->is_admin || (bool) $user?->is_hr;
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'list_users');
    }
}
