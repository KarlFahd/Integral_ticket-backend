<?php

namespace App\Services;

use App\DTO\StoreEventDTO;
use App\DTO\UpdateEventDTO;
use App\Events\EventCreated;
use App\Events\EventDeleted;
use App\Events\EventUpdated;
use App\Jobs\SyncEventToGoogleJob;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly EventReminderService $eventReminderService,
    ) {}

    public function getAllEvents(): Collection
    {
        return $this->eventRepository->all();
    }

    public function getEventById(int $id): ?Event
    {
        return $this->eventRepository->findById($id);
    }

    public function createEvent(StoreEventDTO $dto): Event
    {
        // Save locally first, always — Google sync is best-effort and must
        // never be the reason an event fails to save (see GoogleCalendarService).
        $event = $this->eventRepository->create($dto);

        // Queued, not synchronous — a live Google Calendar API call inside
        // the request/response cycle was slow enough (especially reached
        // through the MCP connector's tunnel) that callers timed out and
        // retried, creating duplicate events even though the first call had
        // already succeeded. See SyncEventToGoogleJob.
        SyncEventToGoogleJob::dispatch($event->id);
        $this->eventReminderService->scheduleReminderForCreator($event);

        $fresh = $this->eventRepository->findById($event->id);

        // Broadcast here — not in the controller — because this is the ONE
        // place both the web UI and the bot's create_event MCP tool go
        // through. The bot creates events with no browser action at all, so
        // without this an open Calendar page would never learn about it
        // until manually refreshed.
        broadcast(new EventCreated($fresh));

        return $fresh;
    }

    public function updateEvent(Event $event, UpdateEventDTO $dto): Event
    {
        $updated = $this->eventRepository->update($event, $dto);

        $this->googleCalendarService->updateEvent($updated);

        // The event's time may have changed — cancel whatever reminder was
        // scheduled against the old time and schedule a fresh one.
        $this->eventReminderService->cancelPendingForEvent($updated->id);
        $this->eventReminderService->scheduleReminderForCreator($updated);

        broadcast(new EventUpdated($updated));

        return $updated;
    }

    public function deleteEvent(Event $event): void
    {
        $id = $event->id;

        if ($event->google_event_id !== null) {
            // Best-effort — if Google is unreachable, still proceed with the
            // local delete rather than leaving the event stuck.
            $this->googleCalendarService->deleteEvent($event->google_event_id);
        }

        $this->eventReminderService->cancelPendingForEvent($event->id);
        $this->eventRepository->delete($event);

        broadcast(new EventDeleted($id));
    }

    /**
     * Called from SyncEventToGoogleJob (queued from createEvent()), not
     * directly from the HTTP request — this is where the actual live call to
     * Google happens, off the request/response cycle.
     */
    public function syncEventToGoogleInBackground(int $eventId): void
    {
        $event = $this->eventRepository->findById($eventId);

        if ($event === null) {
            return;
        }

        $this->syncToGoogle($event);

        // Let an open Calendar page pick up the sync-status change (pending
        // -> synced/failed) without needing a manual refresh.
        broadcast(new EventUpdated($this->eventRepository->findById($eventId)));
    }

    private function syncToGoogle(Event $event): void
    {
        $googleEventId = $this->googleCalendarService->createEvent($event);

        if ($googleEventId !== null) {
            $this->eventRepository->markGoogleSynced($event, $googleEventId);
        } else {
            $this->eventRepository->markGoogleSyncFailed($event, 'Could not reach Google Calendar — will show as pending until this is retried.');
        }
    }
}
