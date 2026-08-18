<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTicketsTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'list_tickets';

    protected string $description = 'List support tickets visible to the current user, optionally filtered by status.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(TicketMcpTools::STATUSES)
                ->description('Optional. Omit to list tickets of every status.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'list_tickets');
    }
}
