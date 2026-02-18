<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge';

    protected $description = 'Delete audit log entries older than configured retention period.';

    public function handle(DataRetentionService $service): int
    {
        $deleted = $service->runAuditLogPurge();
        if ($deleted > 0) {
            $this->info("Deleted {$deleted} audit log(s).");
        }
        return self::SUCCESS;
    }
}
