<?php

use App\Http\Controllers\McpLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The only session-based ("web" guard) login page in this app — see
// McpLoginController's doc comment for why it exists. Named `login` so
// Laravel's auth middleware finds it automatically when Passport's OAuth
// authorize screen needs a guest to log in first.
Route::get('/mcp-login', [McpLoginController::class, 'show'])->name('login');
Route::post('/mcp-login', [McpLoginController::class, 'attempt']);
Route::post('/mcp-logout', [McpLoginController::class, 'logout'])->name('mcp.logout');
