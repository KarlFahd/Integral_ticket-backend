<?php

namespace App\Repositories;

use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    private const RELATIONS = ['statusHistories.oldStatus', 'statusHistories.newStatus', 'category', 'priority', 'status'];

    public function all(): Collection
    {
        return Ticket::with(self::RELATIONS)->latest()->get();
    }

    public function findById(int $id): ?Ticket
    {
        return Ticket::with(self::RELATIONS)->find($id);
    }

    public function create(StoreTicketDTO $dto): Ticket
    {
        // Every new ticket starts life as "Open" — resolved by name rather
        // than a hardcoded id, so the seeded insert order isn't a silent
        // assumption baked into application code.
        $openStatusId = Status::where('name', 'Open')->value('id');

        $ticket = Ticket::create([
            'title' => $dto->title,
            'description' => $dto->description,
            'category_id' => $dto->categoryId,
            'priority_id' => $dto->priorityId,
            'status_id' => $openStatusId,
            'attachment' => $dto->attachment,
            'created_by' => $dto->createdBy,
        ]);

        TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'old_status_id' => null,
            'new_status_id' => $openStatusId,
        ]);

        return $ticket->load(self::RELATIONS);
    }

    public function updateStatus(Ticket $ticket, UpdateTicketStatusDTO $dto): Ticket
    {
        TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'old_status_id' => $ticket->status_id,
            'new_status_id' => $dto->statusId,
        ]);

        $ticket->update(['status_id' => $dto->statusId]);

        return $ticket->fresh(self::RELATIONS);
    }

    public function updatePriority(Ticket $ticket, int $priorityId): Ticket
    {
        $ticket->update(['priority_id' => $priorityId]);

        return $ticket->fresh(self::RELATIONS);
    }

    public function delete(Ticket $ticket): void
    {
        $ticket->delete();
    }
}
