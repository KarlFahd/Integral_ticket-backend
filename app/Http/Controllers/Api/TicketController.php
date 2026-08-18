<?php

namespace App\Http\Controllers\Api;

use App\DTO\StoreTicketDTO;
use App\DTO\UpdateTicketStatusDTO;
use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketPriorityRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TicketResource::collection($this->ticketService->getAllTickets());
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket(
            StoreTicketDTO::fromArray($request->validated())
        );

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(int $id): TicketResource|JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return new TicketResource($ticket);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, int $id): TicketResource|JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $updated = $this->ticketService->updateTicketStatus(
            $ticket,
            UpdateTicketStatusDTO::fromArray($request->validated())
        );

        broadcast(new TicketUpdated($updated));

        return new TicketResource($updated);
    }

    public function updatePriority(UpdateTicketPriorityRequest $request, int $id): TicketResource|JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $updated = $this->ticketService->updateTicketPriority(
            $ticket,
            (int) $request->validated()['priority_id']
        );

        broadcast(new TicketUpdated($updated));

        return new TicketResource($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $this->ticketService->deleteTicket($ticket);

        return response()->json(['message' => 'Ticket deleted.']);
    }
}
