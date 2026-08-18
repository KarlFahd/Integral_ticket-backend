<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => $this->category?->name,
            'priority_id' => $this->priority_id,
            'priority' => $this->priority?->name,
            'status_id' => $this->status_id,
            'status' => $this->status?->name,
            'attachment' => $this->attachment,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn (TicketStatusHistory $h) => [
                'old_status' => $h->oldStatus?->name,
                'new_status' => $h->newStatus?->name,
                'changed_at' => $h->changed_at->toISOString(),
            ])->all()
            ),
        ];
    }
}
