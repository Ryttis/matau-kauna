<?php

namespace App\Console\Commands;

use App\Jobs\RunAuditLogPurgeJob;
use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge {--sync : Vykdyti sinchroniškai be queue}';

    protected $description = 'Ištrina audito žurnalo įrašus, vyresnius nei nustatytas saugojimo laikotarpis.';

    public function handle(DataRetentionService $service): int
    {
        if (! $this->option('sync')) {
            RunAuditLogPurgeJob::dispatch();
            $this->info('Audito trynimo job įdėtas į queue.');

            return self::SUCCESS;
        }

        $deleted = $service->runAuditLogPurge();
        if ($deleted > 0) {
            $this->info("Ištrinta {$deleted} audito įrašų.");
        }

        return self::SUCCESS;
    }
}
