<?php

namespace App\Jobs;

use App\Services\InactiveReporterDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteInactiveReporterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $userId) {}

    public function handle(InactiveReporterDeletionService $service): void
    {
        $service->deleteReporterById($this->userId);
    }
}
