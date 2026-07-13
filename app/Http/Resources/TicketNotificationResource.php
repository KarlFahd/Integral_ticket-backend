<?php

namespace App\Http\Resources;

use App\Models\TicketNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketNotification */
class TicketNotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'ticket_id'   => $this->ticket_id,
            'ticket_title' => $this->ticket->title,
            'sender'      => $this->sender,
            'is_agent'    => $this->is_agent,
            'preview'     => $this->preview,
            'created_at'  => $this->created_at->toISOString(),
        ];
    }
}
