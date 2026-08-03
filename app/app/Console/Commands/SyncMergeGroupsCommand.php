<?php

namespace App\Console\Commands;

use App\Models\Problem;
use App\Services\ProblemMergeGroupSyncService;
use Illuminate\Console\Command;

class SyncMergeGroupsCommand extends Command
{
    protected $signature = 'problems:sync-merge-groups {--dry-run : Tik parodyti, kas būtų pakeista}';

    protected $description = 'Suplokština sujungtų problemų grandines ir perduoda šakninės problemos parametrus apjungtoms problemoms';

    public function handle(ProblemMergeGroupSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $flattened = $this->flattenChains($dryRun);
        $synced = $this->syncGroups($syncService, $dryRun);

        $prefix = $dryRun ? 'Būtų suplokštinta' : 'Suplokštinta';
        $this->info("{$prefix} grandinių: {$flattened}.");

        $prefix = $dryRun ? 'Būtų sinchronizuota' : 'Sinchronizuota';
        $this->info("{$prefix} apjungtų problemų: {$synced}.");

        return self::SUCCESS;
    }

    /**
     * Vaikai, kurių tėvas pats yra apjungtas į kitą problemą, perkeliami tiesiai į grupės šaknį.
     */
    private function flattenChains(bool $dryRun): int
    {
        $changed = 0;

        Problem::query()
            ->whereNotNull('merged_into_problem_id')
            ->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('problems as parents')
                ->whereColumn('parents.id', 'problems.merged_into_problem_id')
                ->whereNotNull('parents.merged_into_problem_id'))
            ->chunkById(200, function ($problems) use (&$changed, $dryRun) {
                foreach ($problems as $problem) {
                    $rootId = $problem->mergeGroupRootId();
                    if ($rootId === (int) $problem->merged_into_problem_id) {
                        continue;
                    }

                    $this->line("  #{$problem->id} ({$problem->internal_number}): {$problem->merged_into_problem_id} -> {$rootId}");
                    $changed++;

                    if (! $dryRun) {
                        $newParentId = $rootId === (int) $problem->id ? null : $rootId;
                        Problem::query()->where('id', $problem->id)->update([
                            'merged_into_problem_id' => $newParentId,
                        ]);
                    }
                }
            });

        return $changed;
    }

    private function syncGroups(ProblemMergeGroupSyncService $syncService, bool $dryRun): int
    {
        $changed = 0;

        Problem::query()
            ->whereNull('merged_into_problem_id')
            ->notArchived()
            ->whereHas('mergedProblems')
            ->chunkById(100, function ($roots) use (&$changed, $syncService, $dryRun) {
                foreach ($roots as $root) {
                    if ($dryRun) {
                        $changed += $this->countOutOfSyncChildren($root);

                        continue;
                    }

                    $changed += count($syncService->syncGroupFromRoot($root));
                }
            });

        return $changed;
    }

    private function countOutOfSyncChildren(Problem $root): int
    {
        $rootInstitutionId = $root->institution_id === null ? null : (int) $root->institution_id;
        $rootDeadline = $root->completion_deadline?->toDateString();

        return Problem::query()
            ->where('merged_into_problem_id', $root->id)
            ->notArchived()
            ->get(['id', 'institution_id', 'status', 'completion_deadline'])
            ->filter(function (Problem $child) use ($root, $rootInstitutionId, $rootDeadline) {
                $childInstitutionId = $child->institution_id === null ? null : (int) $child->institution_id;

                return $childInstitutionId !== $rootInstitutionId
                    || (string) $child->status !== (string) $root->status
                    || $child->completion_deadline?->toDateString() !== $rootDeadline;
            })
            ->count();
    }
}
