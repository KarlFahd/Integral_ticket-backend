<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTicketDetailTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'get_ticket_detail';

    protected string $description = 'Get full details for a single ticket by its numeric ID.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'get_ticket_detail');
    }
}
