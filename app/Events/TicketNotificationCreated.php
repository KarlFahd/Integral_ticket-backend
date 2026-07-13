<?php

namespace App\Events;

use App\Http\Resources\TicketNotificationResource;
use App\Models\TicketNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TicketNotification $notification,
        public string $recipientUsername,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('notifications.' . $this->recipientUsername)];
    }

    public function broadcastWith(): array
    {
        return ['notification' => (new TicketNotificationResource($this->notification))->resolve()];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
