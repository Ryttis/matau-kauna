<?php

namespace App\Console\Commands;

use App\Jobs\HideResolvedProblemsPrivacyJob;
use App\Services\ResolvedProblemPrivacyService;
use Illuminate\Console\Command;

class HideResolvedProblemsPrivacyCommand extends Command
{
    protected $signature = 'problems:hide-resolved-privacy {--sync : Vykdyti sinchroniškai be queue}';

    protected $description = 'Pagal automatizacijos nustatymus nuima viešą skelbimą iš seniai išspręstų problemų';

    public function handle(ResolvedProblemPrivacyService $service): int
    {
        if (! $this->option('sync')) {
            HideResolvedProblemsPrivacyJob::dispatch();
            $this->info('Paslėpimo užduotis įdėta į queue.');

            return self::SUCCESS;
        }

        $updated = $service->hideExpiredResolvedProblems();
        $this->info(sprintf('Atnaujinta problemų: %d.', $updated));

        return self::SUCCESS;
    }
}
