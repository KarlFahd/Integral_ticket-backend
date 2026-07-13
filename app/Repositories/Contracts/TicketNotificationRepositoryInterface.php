<?php

namespace App\Repositories\Contracts;

use App\Models\TicketNotification;
use Illuminate\Database\Eloquent\Collection;

interface TicketNotificationRepositoryInterface
{
    /** @return Collection<int, TicketNotification> */
    public function getForUser(int $userId): Collection;

    public function create(int $userId, int $ticketId, string $sender, bool $isAgent, string $preview): TicketNotification;

    public function delete(int $id): bool;

    public function deleteForUserAndTicket(int $userId, int $ticketId): void;
}
