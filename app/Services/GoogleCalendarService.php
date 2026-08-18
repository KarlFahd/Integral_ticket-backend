<?php

namespace App\Services;

use App\Models\Event;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;

/**
 * Talks to ONE shared Google Calendar via a service account — not per-user
 * OAuth. The service account must be shared on that calendar (in Google's
 * own UI) with "Make changes to events" access; this class has no way to
 * do that step itself.
 *
 * Every public method here follows the same resilience pattern already
 * used by McpHttpClient::call(): wrap the real call in try/catch, log a
 * warning, and return a safe "nothing happened" value instead of throwing.
 * A missing credentials file, an unreachable Google API, or bad
 * permissions must never stop an event from being saved locally.
 */
class GoogleCalendarService
{
    private ?Client $client = null;

    private bool $attemptedClientBuild = false;

    public function __construct(
        private readonly ?string $credentialsPath,
        private readonly ?string $calendarId,
        private readonly string $timezone,
    ) {}

    /**
     * Create the event on the shared Google Calendar.
     *
     * @return string|null The Google-assigned event id, or null on any failure.
     */
    public function createEvent(Event $event): ?string
    {
        $service = $this->service();

        if ($service === null) {
            return null;
        }

        try {
            $created = $service->events->insert($this->calendarId, $this->buildGoogleEvent($event));

            return $created->getId();
        } catch (GoogleServiceException|\Throwable $e) {
            Log::warning('GoogleCalendarService: failed to create event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(Event $event): bool
    {
        if ($event->google_event_id === null) {
            return false;
        }

        $service = $this->service();

        if ($service === null) {
            return false;
        }

        try {
            $service->events->update($this->calendarId, $event->google_event_id, $this->buildGoogleEvent($event));

            return true;
        } catch (GoogleServiceException|\Throwable $e) {
            Log::warning('GoogleCalendarService: failed to update event', [
                'event_id' => $event->id,
                'google_event_id' => $event->google_event_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteEvent(string $googleEventId): bool
    {
        $service = $this->service();

        if ($service === null) {
            return false;
        }

        try {
            $service->events->delete($this->calendarId, $googleEventId);

            return true;
        } catch (GoogleServiceException|\Throwable $e) {
            Log::warning('GoogleCalendarService: failed to delete event', [
                'google_event_id' => $googleEventId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function buildGoogleEvent(Event $event): GoogleEvent
    {
        $description = $event->description
            ."\n\nType: {$event->type}"
            ."\nCreated by: {$event->creator->name}";

        if ($event->participants->isNotEmpty()) {
            $description .= "\nParticipants: ".$event->participants->pluck('name')->join(', ');
        }

        $googleEvent = new GoogleEvent([
            'summary' => $event->title,
            'description' => $description,
            'start' => new EventDateTime([
                'dateTime' => $event->startsAt()->toRfc3339String(),
                'timeZone' => $this->timezone,
            ]),
        ]);

        $end = $event->endsAt() ?? $event->startsAt()->clone()->addHour();
        $googleEvent->setEnd(new EventDateTime([
            'dateTime' => $end->toRfc3339String(),
            'timeZone' => $this->timezone,
        ]));

        return $googleEvent;
    }

    /**
     * Lazily builds and caches the Google Calendar client. Returns null (and
     * logs once) if credentials aren't configured yet or fail to load — the
     * "Google isn't set up yet" degrade-gracefully path.
     */
    private function service(): ?Calendar
    {
        if ($this->client !== null) {
            return new Calendar($this->client);
        }

        if ($this->attemptedClientBuild) {
            return null;
        }

        $this->attemptedClientBuild = true;

        if (! $this->credentialsPath || ! $this->calendarId || ! is_file($this->credentialsPath)) {
            Log::warning('GoogleCalendarService: not configured — missing credentials path, calendar id, or key file.', [
                'credentials_path' => $this->credentialsPath,
            ]);

            return null;
        }

        try {
            $client = new Client;
            $client->setAuthConfig($this->credentialsPath);
            $client->addScope(Calendar::CALENDAR_EVENTS);

            $this->client = $client;

            return new Calendar($client);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService: failed to build Google client', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
