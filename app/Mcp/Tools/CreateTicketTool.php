<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTicketTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'create_ticket';

    protected string $description = 'Create a new support ticket on behalf of the current user, to be picked up by '
        .'an Admin/Agent. Call this only once you have all four fields.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Short ticket title, 3-255 characters.')->required(),
            'description' => $schema->string()->description('Description of the issue, 3-200 characters.')->required(),
            'category' => $schema->string()->enum(TicketMcpTools::CATEGORIES)->required(),
            'priority' => $schema->string()->enum(TicketMcpTools::PRIORITIES)->required(),
        ];
    }

    // Tickets are how Employees and HR report a problem to an Admin/Agent —
    // an Admin creating a ticket would mean talking to themselves, so this
    // tool isn't offered to them at all (mirrors TicketMcpTools::definitions()
    // for the in-app bot).
    public function shouldRegister(Request $request): bool
    {
        return ! (bool) $request->user()?->is_admin;
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'create_ticket');
    }
}
