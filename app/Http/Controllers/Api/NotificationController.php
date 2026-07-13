<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketNotificationResource;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly TicketNotificationService $notificationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = User::where('username', $request->query('username'))->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return TicketNotificationResource::collection(
            $this->notificationService->getForUser($user->id)
        );
    }

    public function clear(int $id): JsonResponse
    {
        $this->notificationService->clear($id);

        return response()->json(['message' => 'Notification cleared.']);
    }

    public function clearForTicket(Request $request, int $ticketId): JsonResponse
    {
        $user = User::where('username', $request->query('username'))->first();

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $this->notificationService->clearForTicket($user->id, $ticketId);

        return response()->json(['message' => 'Notifications cleared for ticket.']);
    }
}
