<?php

namespace App\Repositories;

use App\DTO\StoreEventDTO;
use App\DTO\UpdateEventDTO;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EventRepository implements EventRepositoryInterface
{
    private const RELATIONS = ['creator', 'participants', 'type'];

    public function all(): Collection
    {
        return Event::with(self::RELATIONS)->orderBy('event_date')->orderBy('start_time')->get();
    }

    public function findById(int $id): ?Event
    {
        return Event::with(self::RELATIONS)->find($id);
    }

    public function create(StoreEventDTO $dto): Event
    {
        $event = Event::create([
            'title' => $dto->title,
            'description' => $dto->description,
            'type_id' => $dto->typeId,
            'event_date' => $dto->eventDate,
            'start_time' => $dto->startTime,
            'end_time' => $dto->endTime,
            'created_by_user_id' => $dto->createdByUserId,
        ]);

        $event->participants()->sync($dto->participantUserIds);

        return $event->load(self::RELATIONS);
    }

    public function update(Event $event, UpdateEventDTO $dto): Event
    {
        $event->update([
            'title' => $dto->title,
            'description' => $dto->description,
            'type_id' => $dto->typeId,
            'event_date' => $dto->eventDate,
            'start_time' => $dto->startTime,
            'end_time' => $dto->endTime,
        ]);

        $event->participants()->sync($dto->participantUserIds);

        return $event->fresh(self::RELATIONS);
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function markGoogleSynced(Event $event, string $googleEventId): void
    {
        $event->update([
            'google_event_id' => $googleEventId,
            'google_sync_status' => 'synced',
            'google_sync_error' => null,
        ]);
    }

    public function markGoogleSyncFailed(Event $event, string $error): void
    {
        $event->update([
            'google_sync_status' => 'failed',
            'google_sync_error' => $error,
        ]);
    }
}
