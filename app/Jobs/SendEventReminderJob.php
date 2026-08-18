<?php

namespace App\Jobs;

use App\Services\EventReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched with ->delay() at the moment an event is created — no cron or
 * Laravel scheduler needed, it just rides the queue worker that's already
 * part of the normal `composer dev` startup (queue:listen).
 */
class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $reminderId,
    ) {}

    public function handle(EventReminderService $eventReminderService): void
    {
        $eventReminderService->fire($this->reminderId);
    }
}
