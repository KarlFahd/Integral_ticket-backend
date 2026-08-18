<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReminder extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'preview',
        'remind_at',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
