<?php

namespace App\Repositories;

use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    public function all(): Collection
    {
        return Ticket::with('statusHistories')->latest()->get();
    }

    public function findById(int $id): ?Ticket
    {
        return Ticket::with('statusHistories')->find($id);
    }

    public function create(StoreTicketDTO $dto): Ticket
    {
        $ticket = Ticket::create([
            'title' => $dto->title,
            'description' => $dto->description,
            'category' => $dto->category,
            'priority' => $dto->priority,
            'status' => 'Open',
            'attachment' => $dto->attachment,
            'created_by' => $dto->createdBy,
        ]);

        TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'old_status' => null,
            'new_status' => 'Open',
        ]);

        return $ticket->load('statusHistories');
    }

    public function updateStatus(Ticket $ticket, UpdateTicketStatusDTO $dto): Ticket
    {
        TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'old_status' => $ticket->status,
            'new_status' => $dto->status,
        ]);

        $ticket->update(['status' => $dto->status]);

        return $ticket->fresh(['statusHistories']);
    }

    public function updatePriority(Ticket $ticket, string $priority): Ticket
    {
        $ticket->update(['priority' => $priority]);

        return $ticket->fresh(['statusHistories']);
    }
}
