<?php

namespace App\Repositories\Contracts;

use App\DTO\StoreMessageDTO;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Collection;

interface TicketMessageRepositoryInterface
{
    /** @return Collection<int, TicketMessage> */
    public function getForTicket(int $ticketId): Collection;

    public function create(int $ticketId, StoreMessageDTO $dto): TicketMessage;
}
