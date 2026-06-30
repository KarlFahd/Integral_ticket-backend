<?php

namespace App\Services;

use App\DTO\StoreMessageDTO;
use App\Models\TicketMessage;
use App\Repositories\Contracts\TicketMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketMessageService
{
    public function __construct(
        private readonly TicketMessageRepositoryInterface $repo,
    ) {}

    /** @return Collection<int, TicketMessage> */
    public function getMessagesForTicket(int $ticketId): Collection
    {
        return $this->repo->getForTicket($ticketId);
    }

    public function createMessage(int $ticketId, StoreMessageDTO $dto): TicketMessage
    {
        return $this->repo->create($ticketId, $dto);
    }
}
