<?php

namespace App\Repositories;

use App\Models\TicketNotification;
use App\Repositories\Contracts\TicketNotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketNotificationRepository implements TicketNotificationRepositoryInterface
{
    /** @return Collection<int, TicketNotification> */
    public function getForUser(int $userId): Collection
    {
        return TicketNotification::with('ticket')
            ->where('user_id', $userId)
            ->latest()
            ->limit(30)
            ->get();
    }

    public function create(int $userId, int $ticketId, string $sender, bool $isAgent, string $preview): TicketNotification
    {
        return TicketNotification::create([
            'user_id'   => $userId,
            'ticket_id' => $ticketId,
            'sender'    => $sender,
            'is_agent'  => $isAgent,
            'preview'   => $preview,
        ]);
    }

    public function delete(int $id): bool
    {
        return (bool) TicketNotification::where('id', $id)->delete();
    }

    public function deleteForUserAndTicket(int $userId, int $ticketId): void
    {
        TicketNotification::where('user_id', $userId)
            ->where('ticket_id', $ticketId)
            ->delete();
    }
}
