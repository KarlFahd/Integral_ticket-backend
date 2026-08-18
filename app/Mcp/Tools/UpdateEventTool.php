<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateEventTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'update_event';

    protected string $description = 'Edit an existing calendar event. This replaces the event\'s full details — you '
        .'must resend every field (title, description, type, event_date, start_time), not just the one that\'s '
        .'changing, so call get_event_detail first to see the current values. participant_usernames REPLACES the '
        .'entire participant list; if you omit it, all participants are removed — to keep existing participants, '
        .'pass their exact usernames again (from get_event_detail).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->required(),
            'title' => $schema->string()->description('Short event title, 3-255 characters.')->required(),
            'description' => $schema->string()->description('What the event is about, 3-2000 characters.')->required(),
            'type' => $schema->string()->enum(TicketMcpTools::EVENT_TYPES)->required(),
            'event_date' => $schema->string()->description('Actual calendar date, format YYYY-MM-DD.')->required(),
            'start_time' => $schema->string()->description('24-hour time, format HH:MM.')->required(),
            'end_time' => $schema->string()->description('Optional. 24-hour time, format HH:MM. Must be after start_time.'),
            'participant_usernames' => $schema->array()->items($schema->string())
                ->description('Replaces the full participant list. Omit to clear all participants — pass the existing usernames back to keep them.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'update_event');
    }
}
