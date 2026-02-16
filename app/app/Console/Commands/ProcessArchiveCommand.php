<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class ProcessArchiveCommand extends Command
{
    protected $signature = 'archive:process';

    protected $description = 'Archive eligible records and delete expired archived records.';

    public function handle(DataRetentionService $service): int
    {
        $archived = $service->runArchive();
        $deleted = $service->runDeletion();

        if ($archived > 0 || $deleted > 0) {
            $this->info("Archived: {$archived}, deleted: {$deleted}.");
        }

        return self::SUCCESS;
    }
}
