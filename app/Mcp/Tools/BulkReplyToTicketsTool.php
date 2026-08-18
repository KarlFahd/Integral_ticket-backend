<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use App\Services\Mcp\TicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class BulkReplyToTicketsTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'bulk_reply_to_tickets';

    protected string $description = 'Post the SAME reply message to every ticket matching the given filters — use this '
        .'instead of calling reply_to_ticket once per ticket whenever the user asks to message "all tickets" '
        .'matching some condition (e.g. "message every High priority ticket"). At least one filter (status, priority, '
        .'min_id, or max_id) is required — refuse and ask for a filter if the user wants it sent to literally every '
        .'ticket with no condition at all. Resolved tickets are always excluded, even if the filters would otherwise '
        .'match them.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->description('Message text, max 1000 characters.')->required(),
            'status' => $schema->string()->enum(TicketMcpTools::STATUSES)
                ->description('Optional filter: only tickets with this status.'),
            'priority' => $schema->string()->enum(TicketMcpTools::PRIORITIES)
                ->description('Optional filter: only tickets with this priority.'),
            'min_id' => $schema->integer()->description('Optional filter: only tickets with an ID greater than this.'),
            'max_id' => $schema->integer()->description('Optional filter: only tickets with an ID less than this.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'bulk_reply_to_tickets');
    }
}
