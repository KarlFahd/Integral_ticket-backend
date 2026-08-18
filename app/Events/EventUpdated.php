<?php

namespace App\Events;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Event $event) {}

    public function broadcastOn(): array
    {
        return [new Channel('calendar-events')];
    }

    public function broadcastWith(): array
    {
        return ['event' => (new EventResource($this->event))->resolve()];
    }

    public function broadcastAs(): string
    {
        return 'event.updated';
    }
}
