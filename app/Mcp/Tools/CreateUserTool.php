<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateUserTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'create_user';

    protected string $description = 'Create a new user account. Only available to HR and Admin roles. A temporary '
        .'password is generated automatically — never ask the user to type a password.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'username' => $schema->string()->required(),
            'email' => $schema->string()->required(),
            'is_hr' => $schema->boolean()->description('Grant HR access. Defaults to false.'),
            'is_admin' => $schema->boolean()->description('Grant Admin/Agent access. Defaults to false.'),
        ];
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
        return $this->respond($request, 'create_user');
    }
}
