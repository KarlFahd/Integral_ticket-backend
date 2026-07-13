<?php

namespace App\Events;

use App\Http\Resources\TicketMessageResource;
use App\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketMessage $message) {}

    public function broadcastOn(): array
    {
        return [new Channel('ticket.' . $this->message->ticket_id)];
    }

    public function broadcastWith(): array
    {
        return ['message' => (new TicketMessageResource($this->message))->resolve()];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
