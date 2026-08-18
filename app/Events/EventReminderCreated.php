<?php

namespace App\Events;

use App\Http\Resources\EventReminderResource;
use App\Models\EventReminder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventReminderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EventReminder $reminder,
        public string $recipientUsername,
    ) {}

    public function broadcastOn(): array
    {
        // Same per-user channel ticket notifications already use — the
        // frontend's bell dropdown listens for both event names on it.
        return [new Channel('notifications.'.$this->recipientUsername)];
    }

    public function broadcastWith(): array
    {
        return ['reminder' => (new EventReminderResource($this->reminder))->resolve()];
    }

    public function broadcastAs(): string
    {
        return 'event-reminder.created';
    }
}
