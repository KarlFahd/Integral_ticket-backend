<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\CallsTicketMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ReplyToTicketTool extends Tool
{
    use CallsTicketMcpTools;

    protected string $name = 'reply_to_ticket';

    protected string $description = 'Post a reply message on a single ticket.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->required(),
            'message' => $schema->string()->description('Message text, max 1000 characters.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->respond($request, 'reply_to_ticket');
    }
}
