<?php

namespace App\Console\Commands;

use App\Services\InactiveReporterDeletionService;
use Illuminate\Console\Command;

class InactiveReporterDeletionCommand extends Command
{
    protected $signature = 'reporters:inactive-deletion {--sync : Vykdyti be queue job\'ų}';

    protected $description = 'Siunčia priminimus neaktyviems pareiškėjams ir šalina paskyras po nustatyto termino';

    public function handle(InactiveReporterDeletionService $service): int
    {
        $dispatchToQueue = ! $this->option('sync');
        $result = $service->runRemindersAndDeletions($dispatchToQueue);
        $mode = $dispatchToQueue ? 'queue' : 'sync';
        $this->info(sprintf('[%s] Priminimai: %d, paskyros: %d.', $mode, $result['reminded'], $result['deleted']));

        return self::SUCCESS;
    }
}
