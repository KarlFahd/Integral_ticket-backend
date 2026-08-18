<?php

namespace App\Services\Mcp;

use App\Models\Ticket;
use App\Services\TicketService;

/**
 * PublicMcpTools — the tool set for the PUBLIC, unauthenticated MCP
 * endpoint (see PublicMcpController), reached from outside this app by
 * things like a Claude.ai custom connector.
 *
 * Deliberately a separate class from TicketMcpTools, not a filtered view of
 * it — this is what actually enforces "only two read-only tools are ever
 * reachable here." TicketMcpTools (create_ticket, create_user,
 * bulk_reply_to_tickets, ...) is never referenced by this class or by
 * PublicMcpController, so there is no code path by which the internal
 * write/admin tools could end up exposed publicly by mistake.
 *
 * There is also no `username`/`isAdmin`/`isHr` anywhere in this class,
 * unlike TicketMcpTools — an anonymous public caller has no identity to
 * scope by, so both tools intentionally show everything (see the class
 * doc comment on PublicMcpController for the trade-off this implies).
 */
class PublicMcpTools
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_tickets',
                'description' => 'List support tickets in the system, optionally filtered by status. This is a read-only, public view — it shows every ticket, not scoped to any particular user.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => TicketMcpTools::STATUSES,
                            'description' => 'Optional. Omit to list tickets of every status.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_ticket_detail',
                'description' => 'Get full details for a single ticket by its numeric ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer'],
                    ],
                    'required' => ['ticket_id'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{content: array<int, array{type: string, text: string}>, action: null}
     */
    public function call(string $name, array $input): array
    {
        $resultText = match ($name) {
            'list_tickets' => $this->toolListTickets($input),
            'get_ticket_detail' => $this->toolGetTicketDetail($input),
            default => "Unknown tool: {$name}",
        };

        return [
            'content' => [['type' => 'text', 'text' => $resultText]],
            'action' => null,
        ];
    }

    /** @param array<string, mixed> $input */
    private function toolListTickets(array $input): string
    {
        $tickets = $this->ticketService->getAllTickets();

        $status = $input['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $tickets = $tickets->where('status.name', $status);
        }

        if ($tickets->isEmpty()) {
            return 'No tickets found.';
        }

        // Cap at 20, same as the internal tool — keeps the text short
        // enough for the model to summarise sensibly.
        return $tickets->take(20)->map(
            fn (Ticket $t) => "#{$t->id} \"{$t->title}\" — {$t->status->name}, {$t->priority->name} priority, {$t->category->name}, created by {$t->created_by}"
        )->implode("\n");
    }

    /** @param array<string, mixed> $input */
    private function toolGetTicketDetail(array $input): string
    {
        $ticket = $this->ticketService->getTicketById((int) ($input['ticket_id'] ?? 0));

        if ($ticket === null) {
            return 'Ticket not found.';
        }

        return "Ticket #{$ticket->id}: \"{$ticket->title}\"\n".
            "Status: {$ticket->status->name}\n".
            "Priority: {$ticket->priority->name}\n".
            "Category: {$ticket->category->name}\n".
            "Created by: {$ticket->created_by}\n".
            "Description: {$ticket->description}";
    }
}
