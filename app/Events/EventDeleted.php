<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $eventId) {}

    public function broadcastOn(): array
    {
        return [new Channel('calendar-events')];
    }

    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId];
    }

    public function broadcastAs(): string
    {
        return 'event.deleted';
    }
}
