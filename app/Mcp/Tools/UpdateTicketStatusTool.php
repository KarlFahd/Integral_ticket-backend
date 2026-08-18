<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTicketStatusTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'update_ticket_status';

    protected string $description = 'Change the status of a ticket (e.g. close it, mark it resolved).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->required(),
            'status' => $schema->string()->enum(TicketMcpTools::STATUSES)->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'update_ticket_status');
    }
}
