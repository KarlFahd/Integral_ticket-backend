<?php

namespace App\Jobs;

use App\Services\EventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched the moment an event is created — moves the live Google Calendar
 * API call off the request/response cycle, the same way SendEventReminderJob
 * already does for reminders. Without this, create_event (especially the MCP
 * tool version, called over a public tunnel with extra network hops) can take
 * long enough that the caller times out and retries — even though the event
 * was already created successfully, producing duplicates.
 */
class SyncEventToGoogleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $eventId,
    ) {}

    public function handle(EventService $eventService): void
    {
        $eventService->syncEventToGoogleInBackground($this->eventId);
    }
}
