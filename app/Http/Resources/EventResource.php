<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type_id' => $this->type_id,
            'type' => $this->type?->name,
            'event_date' => $this->event_date->format('Y-m-d'),
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'participants' => UserResource::collection($this->whenLoaded('participants')),
            'google_sync_status' => $this->google_sync_status,
            'google_sync_error' => $this->google_sync_error,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
