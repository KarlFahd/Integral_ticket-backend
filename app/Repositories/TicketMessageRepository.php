<?php

namespace App\Repositories;

use App\DTO\StoreMessageDTO;
use App\Models\TicketMessage;
use App\Repositories\Contracts\TicketMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketMessageRepository implements TicketMessageRepositoryInterface
{
    /** @return Collection<int, TicketMessage> */
    public function getForTicket(int $ticketId): Collection
    {
        return TicketMessage::where('ticket_id', $ticketId)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at')
            ->get();
    }

    public function create(int $ticketId, StoreMessageDTO $dto): TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $ticketId,
            'sender'    => $dto->sender,
            'is_agent'  => $dto->isAgent,
            'message'   => $dto->message,
        ]);
    }
}
