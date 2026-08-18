<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListEventsTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'list_events';

    protected string $description = 'List all events on the shared company calendar (meetings, deadlines, '
        .'reminders). Use this first when the user refers to an event by a fuzzy title, so you can find its '
        .'numeric ID before calling get_event_detail, update_event, or delete_event.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'list_events');
    }
}
