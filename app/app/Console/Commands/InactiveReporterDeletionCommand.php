<?php

namespace App\Console\Commands;

use App\Services\InactiveReporterDeletionService;
use Illuminate\Console\Command;

class InactiveReporterDeletionCommand extends Command
{
    protected $signature = 'reporters:inactive-deletion';

    protected $description = 'Siunčia priminimus neaktyviems pareiškėjams ir šalina paskyras po nustatyto termino';

    public function handle(InactiveReporterDeletionService $service): int
    {
        $result = $service->runRemindersAndDeletions();
        $this->info(sprintf('Priminimai išsiųsti: %d, paskyros pašalintos: %d.', $result['reminded'], $result['deleted']));
        return self::SUCCESS;
    }
}
