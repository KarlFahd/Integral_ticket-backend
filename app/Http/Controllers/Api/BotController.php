<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BotChatRequest;
use App\Services\BotService;
use Illuminate\Http\JsonResponse;

class BotController extends Controller
{
    public function __construct(
        private readonly BotService $botService,
    ) {}

    public function chat(BotChatRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->botService->chat(
            $data['messages'],
            $data['username'],
            (bool) ($data['is_admin'] ?? false),
            (bool) ($data['is_hr'] ?? false),
        );

        return response()->json($result);
    }
}
