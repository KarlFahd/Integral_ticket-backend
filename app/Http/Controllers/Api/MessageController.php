<?php

namespace App\Http\Controllers\Api;

use App\DTO\StoreMessageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Services\TicketMessageService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketMessageService $messageService,
    ) {}

    public function index(int $id): AnonymousResourceCollection|JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $messages = $this->messageService->getMessagesForTicket($id);

        return TicketMessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->getTicketById($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $dto     = StoreMessageDTO::fromArray($request->validated());
        $message = $this->messageService->createMessage($id, $dto);

        return (new TicketMessageResource($message))->response()->setStatusCode(201);
    }
}
