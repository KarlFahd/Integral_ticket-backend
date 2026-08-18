<?php

namespace App\Repositories\Contracts;

use App\DTO\StoreEventDTO;
use App\DTO\UpdateEventDTO;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;

interface EventRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Event;

    public function create(StoreEventDTO $dto): Event;

    public function update(Event $event, UpdateEventDTO $dto): Event;

    public function delete(Event $event): void;

    public function markGoogleSynced(Event $event, string $googleEventId): void;

    public function markGoogleSyncFailed(Event $event, string $error): void;
}
