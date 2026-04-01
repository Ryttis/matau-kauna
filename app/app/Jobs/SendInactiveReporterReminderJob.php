<?php

namespace App\Jobs;

use App\Services\InactiveReporterDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInactiveReporterReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $userId,
        public int $inactivityDays,
    ) {}

    public function handle(InactiveReporterDeletionService $service): void
    {
        $service->sendReminderByUserId($this->userId, $this->inactivityDays);
    }
}
