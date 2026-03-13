<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class ProcessArchiveCommand extends Command
{
    protected $signature = 'archive:process';

    protected $description = 'Archiuuoja įrašus pagal taisykles ir ištrina pasibaigusius archyvuotus įrašus.';

    public function handle(DataRetentionService $service): int
    {
        $archived = $service->runArchive();
        $deleted = $service->runDeletion();

        if ($archived > 0 || $deleted > 0) {
            $this->info("Archivuota: {$archived}, ištrinta: {$deleted}.");
        }

        return self::SUCCESS;
    }
}
