<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketMessage */
class TicketMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'sender'     => $this->sender,
            'is_agent'   => $this->is_agent,
            'message'    => $this->message,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
