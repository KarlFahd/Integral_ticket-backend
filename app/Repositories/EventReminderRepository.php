<?php

namespace App\Repositories;

use App\Models\EventReminder;
use App\Repositories\Contracts\EventReminderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class EventReminderRepository implements EventReminderRepositoryInterface
{
    /** @return Collection<int, EventReminder> */
    public function getForUser(int $userId): Collection
    {
        return EventReminder::with('event')
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->latest()
            ->limit(30)
            ->get();
    }

    public function create(int $eventId, int $userId, string $preview, Carbon $remindAt): EventReminder
    {
        return EventReminder::create([
            'event_id' => $eventId,
            'user_id' => $userId,
            'preview' => $preview,
            'remind_at' => $remindAt,
            'status' => 'pending',
        ]);
    }

    public function markSent(int $id): void
    {
        EventReminder::where('id', $id)->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);
    }

    public function delete(int $id): bool
    {
        return (bool) EventReminder::where('id', $id)->delete();
    }

    public function cancelPendingForEvent(int $eventId): void
    {
        EventReminder::where('event_id', $eventId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
