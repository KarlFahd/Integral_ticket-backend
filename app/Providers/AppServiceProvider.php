<?php

namespace App\Providers;

use App\Repositories\Contracts\TicketMessageRepositoryInterface;
use App\Repositories\Contracts\TicketNotificationRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\TicketMessageRepository;
use App\Repositories\TicketNotificationRepository;
use App\Repositories\TicketRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(TicketMessageRepositoryInterface::class, TicketMessageRepository::class);
        $this->app->bind(TicketNotificationRepositoryInterface::class, TicketNotificationRepository::class);
    }

    public function boot(): void {}
}
