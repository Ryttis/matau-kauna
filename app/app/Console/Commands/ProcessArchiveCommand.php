<?php

namespace App\Console\Commands;

use App\Models\DataRetentionRule;
use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class ProcessArchiveCommand extends Command
{
    protected $signature = 'archive:process';

    protected $description = 'Archiuuoja įrašus pagal taisykles ir ištrina pasibaigusius archyvuotus įrašus.';

    public function handle(DataRetentionService $service): int
    {
        $service->runArchive();
        $inArchive = $service->countArchived();
        $deletedTotal = 0;
        foreach ([
            DataRetentionRule::ENTITY_USER,
            DataRetentionRule::ENTITY_INSTITUTION,
            DataRetentionRule::ENTITY_PROBLEM,
            DataRetentionRule::ENTITY_CLASSIFIER_VALUE,
        ] as $entityType) {
            $deletedTotal += $service->runDeletionFor($entityType);
        }

        if ($inArchive > 0 || $deletedTotal > 0) {
            $this->info("Archivuota: {$inArchive}, ištrinta: {$deletedTotal}.");
        }

        return self::SUCCESS;
    }
}
