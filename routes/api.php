<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/bot/chat', [BotController::class, 'chat']);

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
