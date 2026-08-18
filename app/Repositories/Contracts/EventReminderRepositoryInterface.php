<?php

namespace App\Repositories\Contracts;

use App\Models\EventReminder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface EventReminderRepositoryInterface
{
    /** @return Collection<int, EventReminder> */
    public function getForUser(int $userId): Collection;

    public function create(int $eventId, int $userId, string $preview, Carbon $remindAt): EventReminder;

    public function markSent(int $id): void;

    public function delete(int $id): bool;

    public function cancelPendingForEvent(int $eventId): void;
}
