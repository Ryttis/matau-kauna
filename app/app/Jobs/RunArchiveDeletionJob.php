<?php

namespace App\Jobs;

use App\Services\DataRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunArchiveDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public string $entityType) {}

    public function handle(DataRetentionService $service): void
    {
        $service->runDeletionFor($this->entityType);
    }
}
