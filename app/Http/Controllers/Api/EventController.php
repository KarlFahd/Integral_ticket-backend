<?php

namespace App\Http\Controllers\Api;

use App\DTO\StoreEventDTO;
use App\DTO\UpdateEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return EventResource::collection($this->eventService->getAllEvents());
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();

        $creator = User::where('username', $data['username'])->firstOrFail();
        $participantIds = User::whereIn('username', $data['participant_usernames'] ?? [])->pluck('id')->all();

        $event = $this->eventService->createEvent(
            StoreEventDTO::fromArray($data, $creator->id, $participantIds)
        );

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function show(int $id): EventResource|JsonResponse
    {
        $event = $this->eventService->getEventById($id);

        if ($event === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return new EventResource($event);
    }

    public function update(UpdateEventRequest $request, int $id): EventResource|JsonResponse
    {
        $event = $this->eventService->getEventById($id);

        if ($event === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $data = $request->validated();
        $participantIds = User::whereIn('username', $data['participant_usernames'] ?? [])->pluck('id')->all();

        $updated = $this->eventService->updateEvent(
            $event,
            UpdateEventDTO::fromArray($data, $participantIds)
        );

        return new EventResource($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $event = $this->eventService->getEventById($id);

        if ($event === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $this->eventService->deleteEvent($event);

        return response()->json(['message' => 'Event deleted.']);
    }
}
