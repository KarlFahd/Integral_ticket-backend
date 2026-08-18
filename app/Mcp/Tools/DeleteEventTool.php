<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteEventTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'delete_event';

    protected string $description = 'Permanently cancel a calendar event by its numeric ID — also removes it from '
        .'the shared Google Calendar. If you only have a title, call list_events first to find the ID. Ask the '
        .'user to confirm before calling this, since it can\'t be undone.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'delete_event');
    }
}
