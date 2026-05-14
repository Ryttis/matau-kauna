<?php

namespace App\Console\Commands;

use App\Models\ProblemDvsTransfer;
use App\Services\DvsProblemTransferService;
use Illuminate\Console\Command;

class PullDvsStatusesCommand extends Command
{
    protected $signature = 'dvs:pull-statuses {--institution= : Limit to a single institution_dvs_config_id}';

    protected $description = 'Sinchronizuoja problemų būsenas iš DVS';

    public function handle(DvsProblemTransferService $service): int
    {
        $query = ProblemDvsTransfer::query()
            ->whereIn('send_status', [
                ProblemDvsTransfer::STATUS_SENT,
                ProblemDvsTransfer::STATUS_FAILED,
            ])
            ->whereNotNull('external_object_id')
            ->with('config');

        if ($institutionConfigId = $this->option('institution')) {
            $query->where('institution_dvs_config_id', (int) $institutionConfigId);
        }

        $processed = 0;
        $resolved = 0;

        $query->chunkById(50, function ($transfers) use ($service, &$processed, &$resolved) {
            foreach ($transfers as $transfer) {
                if (! $transfer->config || ! $transfer->config->enabled) {
                    continue;
                }

                try {
                    $result = $service->syncFromDvs($transfer);
                    if ($result['answer_found']) {
                        $resolved++;
                    }
                    $processed++;
                } catch (\Throwable $e) {
                    $this->warn('DVS transfer #'.$transfer->id.': '.$e->getMessage());
                }
            }
        });

        $this->info("Patikrinta DVS perdavimų: {$processed}; užbaigtų atsakymų rasta: {$resolved}.");

        return self::SUCCESS;
    }
}
