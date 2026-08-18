<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventReminderController;
use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicMcpController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/bot/chat', [BotController::class, 'chat']);

// The MCP server endpoint — speaks JSON-RPC 2.0 (initialize / tools/list /
// tools/call), protected by a shared secret (see McpController) rather than
// user auth, since callers here act "as" whichever user they say they are.
Route::post('/mcp', [McpController::class, 'handle']);

// A SEPARATE, deliberately narrow public endpoint — no shared secret, no
// identity headers, and only the two read-only tools in PublicMcpTools are
// ever reachable through it (see PublicMcpController's doc comment for
// why). Rate-limited since it's the one endpoint in this app with zero
// authentication at all.
Route::post('/mcp/public', [PublicMcpController::class, 'handle'])->middleware('throttle:30,1');

Route::prefix('2fa')->group(function () {
    Route::post('/setup',   [TwoFactorController::class, 'setup']);
    Route::post('/enable',  [TwoFactorController::class, 'enable']);
    Route::post('/verify',  [TwoFactorController::class, 'verify']);
    Route::post('/disable', [TwoFactorController::class, 'disable']);
    Route::post('/status',  [TwoFactorController::class, 'status']);
});

Route::prefix('users')->group(function () {
    Route::get('/',  [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
});

Route::prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index']);
    Route::post('/', [TicketController::class, 'store']);
    Route::get('/{id}', [TicketController::class, 'show']);
    Route::patch('/{id}/status', [TicketController::class, 'updateStatus']);
    Route::patch('/{id}/priority', [TicketController::class, 'updatePriority']);
    Route::delete('/{id}', [TicketController::class, 'destroy']);
    Route::get('/{id}/messages', [MessageController::class, 'index']);
    Route::post('/{id}/messages', [MessageController::class, 'store']);
});

Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::delete('/ticket/{ticketId}', [NotificationController::class, 'clearForTicket']);
    Route::delete('/{id}', [NotificationController::class, 'clear']);
});

Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::post('/', [EventController::class, 'store']);
    Route::get('/{id}', [EventController::class, 'show']);
    Route::patch('/{id}', [EventController::class, 'update']);
    Route::delete('/{id}', [EventController::class, 'destroy']);
});

Route::prefix('event-reminders')->group(function () {
    Route::get('/', [EventReminderController::class, 'index']);
    Route::delete('/{id}', [EventReminderController::class, 'clear']);
});
