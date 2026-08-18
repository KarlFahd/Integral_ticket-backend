<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTicketPriorityTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'update_ticket_priority';

    protected string $description = 'Change the priority of a ticket.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->required(),
            'priority' => $schema->string()->enum(TicketMcpTools::PRIORITIES)->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'update_ticket_priority');
    }
}
