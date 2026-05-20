<?php

namespace App\Console\Commands;

use App\Models\ProblemGisTransfer;
use App\Services\ArcGisProblemTransferService;
use Illuminate\Console\Command;

class PullGisAttachmentsCommand extends Command
{
    protected $signature = 'gis:pull-attachments {--institution= : Limit to a single institution_gis_config_id}';

    protected $description = 'Sinchronizuoja problemos būseną, terminą ir priedus iš ArcGIS';

    public function handle(ArcGisProblemTransferService $service): int
    {
        $query = ProblemGisTransfer::query()
            ->whereIn('send_status', [
                ProblemGisTransfer::STATUS_FEATURE_CREATED,
                ProblemGisTransfer::STATUS_SENT,
                ProblemGisTransfer::STATUS_ATTACHMENT_FAILED,
            ])
            ->whereNotNull('external_object_id')
            ->with('config');

        if ($institutionConfigId = $this->option('institution')) {
            $query->where('institution_gis_config_id', (int) $institutionConfigId);
        }

        $totalDownloaded = 0;
        $totalSkipped = 0;
        $statusChanged = 0;
        $deadlineChanged = 0;
        $processed = 0;

        $query->chunkById(50, function ($transfers) use ($service, &$totalDownloaded, &$totalSkipped, &$statusChanged, &$deadlineChanged, &$processed) {
            foreach ($transfers as $transfer) {
                if (! $transfer->config || ! $transfer->config->enabled) {
                    continue;
                }

                try {
                    $result = $service->syncFromGis($transfer, ['trigger' => 'command']);
                    $totalDownloaded += $result['attachments_downloaded'];
                    $totalSkipped += $result['attachments_skipped'];
                    if ($result['status_changed']) {
                        $statusChanged++;
                    }
                    if ($result['deadline_changed']) {
                        $deadlineChanged++;
                    }
                    $processed++;
                } catch (\Throwable $e) {
                    $this->warn('Transfer #'.$transfer->id.': '.$e->getMessage());
                }
            }
        });

        $this->info("Patikrinta perdavimų: {$processed}; būsenų pakeista: {$statusChanged}; terminų pakeista: {$deadlineChanged}; priedų atsisiųsta: {$totalDownloaded}; praleista: {$totalSkipped}.");

        return self::SUCCESS;
    }
}
