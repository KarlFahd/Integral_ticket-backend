<?php

namespace App\Repositories\Contracts;

use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

interface TicketRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Ticket;

    public function create(StoreTicketDTO $dto): Ticket;

    public function updateStatus(Ticket $ticket, UpdateTicketStatusDTO $dto): Ticket;

    public function updatePriority(Ticket $ticket, string $priority): Ticket;
}
