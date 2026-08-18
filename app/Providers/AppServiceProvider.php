<?php

namespace App\Providers;

use App\Repositories\Contracts\EventReminderRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketMessageRepositoryInterface;
use App\Repositories\Contracts\TicketNotificationRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\EventReminderRepository;
use App\Repositories\EventRepository;
use App\Repositories\TicketMessageRepository;
use App\Repositories\TicketNotificationRepository;
use App\Repositories\TicketRepository;
use App\Services\GoogleCalendarService;
use App\Services\Mcp\McpHttpClient;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(TicketMessageRepositoryInterface::class, TicketMessageRepository::class);
        $this->app->bind(TicketNotificationRepositoryInterface::class, TicketNotificationRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(EventReminderRepositoryInterface::class, EventReminderRepository::class);

        // McpHttpClient takes plain config values, not auto-resolvable
        // dependencies — bind it explicitly so `new McpHttpClient(...)`
        // only ever happens here, in one place.
        $this->app->singleton(McpHttpClient::class, fn () => new McpHttpClient(
            config('services.mcp.url'),
            (string) config('services.mcp.secret'),
        ));

        $this->app->singleton(GoogleCalendarService::class, fn () => new GoogleCalendarService(
            config('services.google_calendar.credentials_path'),
            config('services.google_calendar.calendar_id'),
            (string) config('services.google_calendar.timezone'),
        ));
    }

    public function boot(): void
    {
        // The MCP OAuth consent screen — published to resources/views/mcp/authorize.blade.php
        // (see `php artisan vendor:publish --tag=mcp-views`). Only reached when a user
        // approves a connector like Claude.ai; requires a real web-session login first
        // (see routes/web.php's /mcp-login, which is the only session-based login this
        // API-only app has).
        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));
    }
}
