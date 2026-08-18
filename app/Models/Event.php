<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type_id',
        'event_date',
        'start_time',
        'end_time',
        'created_by_user_id',
        'google_event_id',
        'google_sync_status',
        'google_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<EventType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_participants');
    }

    /** @return HasMany<EventReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }

    /**
     * The event's real start instant, interpreted in the office's configured
     * timezone — the single place that combines the separate date/time
     * columns, so GoogleCalendarService and EventReminderService never have
     * to repeat (and risk drifting on) the same Carbon::parse() call.
     */
    public function startsAt(): Carbon
    {
        $date = $this->event_date->format('Y-m-d');

        return Carbon::parse("{$date} {$this->start_time}", config('services.google_calendar.timezone'));
    }

    public function endsAt(): ?Carbon
    {
        if ($this->end_time === null) {
            return null;
        }

        $date = $this->event_date->format('Y-m-d');

        return Carbon::parse("{$date} {$this->end_time}", config('services.google_calendar.timezone'));
    }
}
