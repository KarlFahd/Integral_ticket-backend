<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateEventTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'create_event';

    protected string $description = 'Create a calendar event on the shared company Google Calendar on behalf of the '
        .'current user (e.g. a meeting, deadline, or reminder). Call this only once you have title, description, '
        .'type, date, and start time. event_date must be an actual calendar date in YYYY-MM-DD format — if the user '
        .'says something relative like "tomorrow" or "next Monday", work out the real date yourself before calling '
        .'this tool. participant_usernames is optional — only include it if the user names specific people to invite '
        .'by their exact username; unrecognized usernames are silently skipped.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Short event title, 3-255 characters.')->required(),
            'description' => $schema->string()->description('What the event is about, 3-2000 characters.')->required(),
            'type' => $schema->string()->enum(TicketMcpTools::EVENT_TYPES)->required(),
            'event_date' => $schema->string()->description('Actual calendar date, format YYYY-MM-DD.')->required(),
            'start_time' => $schema->string()->description('24-hour time, format HH:MM.')->required(),
            'end_time' => $schema->string()->description('Optional. 24-hour time, format HH:MM. Must be after start_time.'),
            'participant_usernames' => $schema->array()->items($schema->string())
                ->description('Optional. Exact usernames of other users to invite.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'create_event');
    }
}
