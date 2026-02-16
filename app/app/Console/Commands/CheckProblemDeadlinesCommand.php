<?php

namespace App\Console\Commands;

use App\Services\ProblemDeadlineMonitorService;
use Illuminate\Console\Command;

class CheckProblemDeadlinesCommand extends Command
{
    protected $signature = 'problems:check-deadlines';

    protected $description = 'Atnaujina problemų termino būsenas ir siunčia priminimus artėjantiems/praėjusiems terminams';

    public function handle(ProblemDeadlineMonitorService $monitor): int
    {
        $monitor->run();

        $this->info('Terminų tikrinimas atliktas.');

        return self::SUCCESS;
    }
}
