<?php

namespace App\Services;

use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketService
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
    ) {}

    public function getAllTickets(): Collection
    {
        return $this->ticketRepository->all();
    }

    public function getTicketById(int $id): ?Ticket
    {
        return $this->ticketRepository->findById($id);
    }

    public function createTicket(StoreTicketDTO $dto): Ticket
    {
        return $this->ticketRepository->create($dto);
    }

    public function updateTicketStatus(Ticket $ticket, UpdateTicketStatusDTO $dto): Ticket
    {
        return $this->ticketRepository->updateStatus($ticket, $dto);
    }

    public function updateTicketPriority(Ticket $ticket, string $priority): Ticket
    {
        return $this->ticketRepository->updatePriority($ticket, $priority);
    }
}
