<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventReminderResource;
use App\Models\User;
use App\Services\EventReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventReminderController extends Controller
{
    public function __construct(
        private readonly EventReminderService $eventReminderService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = User::where('username', $request->query('username'))->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return EventReminderResource::collection(
            $this->eventReminderService->getForUser($user->id)
        );
    }

    public function clear(int $id): JsonResponse
    {
        $this->eventReminderService->clear($id);

        return response()->json(['message' => 'Reminder cleared.']);
    }
}
