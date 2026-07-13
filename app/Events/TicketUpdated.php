<?php

namespace App\Events;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [new Channel('tickets')];
    }

    public function broadcastWith(): array
    {
        return ['ticket' => (new TicketResource($this->ticket->load('statusHistories')))->resolve()];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }
}
