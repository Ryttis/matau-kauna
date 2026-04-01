<?php

namespace App\Console\Commands;

use App\Jobs\RunArchiveArchivingJob;
use App\Jobs\RunArchiveDeletionJob;
use App\Models\DataRetentionRule;
use App\Services\DataRetentionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Console\Command;

class ProcessArchiveCommand extends Command
{
    protected $signature = 'archive:process {--sync : Vykdyti sinchroniškai be queue}';

    protected $description = 'Archiuuoja įrašus pagal taisykles ir ištrina pasibaigusius archyvuotus įrašus.';

    public function handle(DataRetentionService $service): int
    {
        if (! $this->option('sync')) {
            $jobs = [new RunArchiveArchivingJob];
            foreach ([
                DataRetentionRule::ENTITY_USER,
                DataRetentionRule::ENTITY_INSTITUTION,
                DataRetentionRule::ENTITY_PROBLEM,
                DataRetentionRule::ENTITY_CLASSIFIER_VALUE,
            ] as $entityType) {
                $jobs[] = new RunArchiveDeletionJob($entityType);
            }
            Bus::chain($jobs)->dispatch();
            $this->info('Archyvavimo ir trynimo jobai įdėti į queue.');

            return self::SUCCESS;
        }

        $archivedNow = $service->runArchive();
        $deletedTotal = 0;
        foreach ([
            DataRetentionRule::ENTITY_USER,
            DataRetentionRule::ENTITY_INSTITUTION,
            DataRetentionRule::ENTITY_PROBLEM,
            DataRetentionRule::ENTITY_CLASSIFIER_VALUE,
        ] as $entityType) {
            $deletedTotal += $service->runDeletionFor($entityType);
        }

        if ($archivedNow > 0 || $deletedTotal > 0) {
            $this->info("Archivuota: {$archivedNow}, ištrinta: {$deletedTotal}.");
        }

        return self::SUCCESS;
    }
}
