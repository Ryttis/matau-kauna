<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge';

    protected $description = 'Ištrina audito žurnalo įrašus, vyresnius nei nustatytas saugojimo laikotarpis.';

    public function handle(DataRetentionService $service): int
    {
        $deleted = $service->runAuditLogPurge();
        if ($deleted > 0) {
            $this->info("Ištrinta {$deleted} audito įrašų.");
        }

        return self::SUCCESS;
    }
}
