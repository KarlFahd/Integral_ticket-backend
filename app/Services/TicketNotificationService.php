<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketNotification;
use App\Models\User;
use App\Repositories\Contracts\TicketNotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketNotificationService
{
    public function __construct(
        private readonly TicketNotificationRepositoryInterface $repo,
    ) {}

    /** @return Collection<int, TicketNotification> */
    public function getForUser(int $userId): Collection
    {
        return $this->repo->getForUser($userId);
    }

    public function clear(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function clearForTicket(int $userId, int $ticketId): void
    {
        $this->repo->deleteForUserAndTicket($userId, $ticketId);
    }

    /**
     * Notify the right recipients that a new message was posted on a ticket.
     * - Agent/admin sent it  -> notify the ticket's creator.
     * - Client/employee/HR sent it -> notify every admin.
     *
     * @return Collection<int, TicketNotification>
     */
    public function notifyForNewMessage(TicketMessage $message, Ticket $ticket): Collection
    {
        $recipients = $message->is_agent
            ? User::where('username', $ticket->created_by)->get()
            : User::where('is_admin', true)->get();

        $preview = str($message->message)->limit(80)->toString();

        return $recipients->map(
            fn (User $recipient) => $this->repo->create(
                $recipient->id,
                $ticket->id,
                $message->sender,
                $message->is_agent,
                $preview,
            )
        );
    }
}
