<?php

namespace App\Http\Resources;

use App\Support\CalendarColor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'username'       => $this->username,
            'email'          => $this->email,
            'is_admin'       => (bool) $this->is_admin,
            'is_hr'          => (bool) $this->is_hr,
            'calendar_color' => CalendarColor::forUserId($this->id),
            'created_at'     => $this->created_at?->format('d/m/Y'),
        ];
    }
}
