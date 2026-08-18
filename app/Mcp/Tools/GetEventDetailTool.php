<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetEventDetailTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'get_event_detail';

    protected string $description = 'Get full details for a single calendar event by its numeric ID, including its '
        .'current participants — call this before update_event so you know the exact current values (update_event '
        .'requires every field to be resent, not just the one changing).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'get_event_detail');
    }
}
