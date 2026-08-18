<?php

namespace App\Http\Resources;

use App\Models\EventReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventReminder */
class EventReminderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_title' => $this->event->title,
            'preview' => $this->preview,
            'remind_at' => $this->remind_at->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
