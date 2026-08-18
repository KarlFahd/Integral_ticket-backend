<?php

namespace App\Services;

use App\Events\EventReminderCreated;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventReminder;
use App\Repositories\Contracts\EventReminderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EventReminderService
{
    public function __construct(
        private readonly EventReminderRepositoryInterface $repo,
    ) {}

    /** @return Collection<int, EventReminder> */
    public function getForUser(int $userId): Collection
    {
        return $this->repo->getForUser($userId);
    }

    public function clear(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Only the creator ever gets reminded — Google's own reminders can't
     * selectively target one person on a single shared calendar, so this is
     * entirely our own app's notification, scheduled to fire a fixed lead
     * time before the event starts.
     */
    public function scheduleReminderForCreator(Event $event): void
    {
        $remindAt = $event->startsAt()->subMinutes(
            (int) config('services.google_calendar.reminder_lead_minutes', 15)
        );

        // Event was created too close to (or after) its own start time —
        // nothing meaningful to remind the creator about anymore.
        if ($remindAt->isPast()) {
            return;
        }

        $preview = "Reminder: \"{$event->title}\" starts at {$event->startsAt()->format('g:i A')}.";

        $reminder = $this->repo->create($event->id, $event->created_by_user_id, $preview, $remindAt);

        SendEventReminderJob::dispatch($reminder->id)->delay($remindAt);
    }

    /**
     * Cancel any not-yet-fired reminder for this event — called when the
     * event is edited (time changed) or deleted, so a stale reminder never
     * fires for something that moved or no longer exists.
     */
    public function cancelPendingForEvent(int $eventId): void
    {
        $this->repo->cancelPendingForEvent($eventId);
    }

    /**
     * Called by SendEventReminderJob when the delayed reminder's time
     * arrives. Skips silently if the reminder was cancelled in the
     * meantime (event edited/deleted after scheduling but before firing).
     */
    public function fire(int $reminderId): void
    {
        $reminder = EventReminder::with(['event', 'user'])->find($reminderId);

        if ($reminder === null || $reminder->status !== 'pending') {
            return;
        }

        $this->repo->markSent($reminderId);
        $reminder->refresh();

        broadcast(new EventReminderCreated($reminder, $reminder->user->username));
    }
}
