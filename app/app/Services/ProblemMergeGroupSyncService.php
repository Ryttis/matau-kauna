<?php

namespace App\Services;

use App\Models\Problem;

class ProblemMergeGroupSyncService
{
    /**
     * Parenka, kuri problema turi likti sujungimo grupės šaknimi ("tėvine").
     *
     * Esama grupės šaknis visada laimi: jei apjungimą vykdanti problema jau turi
     * vaikų - ji lieka šaknimi; kitu atveju šaknimi tampa seniausia pažymėta
     * problema, kuri jau yra grupės šaknis. Jei tokių nėra - lieka dabartinė.
     *
     * @param  array<int, int>  $selectedIds
     */
    public function resolveMergeRootId(Problem $current, array $selectedIds): int
    {
        if ($current->merged_into_problem_id === null && $current->mergedProblems()->exists()) {
            return (int) $current->id;
        }

        $existingRoot = Problem::query()
            ->whereIn('id', $selectedIds)
            ->whereNull('merged_into_problem_id')
            ->whereHas('mergedProblems')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return (int) ($existingRoot->id ?? $current->id);
    }

    /**
     * Perduoda šakninės problemos parametrus visoms jos apjungtoms problemoms.
     *
     * @return array<int, int> pakeistų problemų id
     */
    public function syncGroupFromRoot(Problem $root): array
    {
        $root->refresh();

        $children = Problem::query()
            ->where('merged_into_problem_id', $root->id)
            ->notArchived()
            ->get(['id', 'institution_id', 'status', 'completion_deadline']);

        if ($children->isEmpty()) {
            return [];
        }

        $rootInstitutionId = $root->institution_id === null ? null : (int) $root->institution_id;
        $rootStatus = (string) $root->status;
        $rootDeadline = $root->completion_deadline;
        $statusIsTerminal = in_array($rootStatus, Problem::NON_NOTIFIABLE_STATUSES, true);

        $changedIds = [];
        $institutionChangedIds = [];

        foreach ($children as $child) {
            $childInstitutionId = $child->institution_id === null ? null : (int) $child->institution_id;
            $institutionChanged = $childInstitutionId !== $rootInstitutionId;
            $statusChanged = (string) $child->status !== $rootStatus;
            $deadlineChanged = $this->deadlineDiffers($child->completion_deadline, $rootDeadline);

            if (! $institutionChanged && ! $statusChanged && ! $deadlineChanged) {
                continue;
            }

            $changedIds[] = (int) $child->id;
            if ($institutionChanged) {
                $institutionChangedIds[] = (int) $child->id;
            }
        }

        if ($changedIds === []) {
            return [];
        }

        $update = [
            'institution_id' => $rootInstitutionId,
            'status' => $rootStatus,
            'status_updated_at' => now(),
            'completion_deadline' => $rootDeadline,
        ];
        if ($statusIsTerminal || $rootDeadline === null) {
            $update['deadline_status'] = null;
            $update['deadline_reminder_sent_at'] = null;
        }

        Problem::query()->whereIn('id', $changedIds)->update($update);

        // Kitos įstaigos darbuotojas nebegali spręsti šios problemos.
        if ($institutionChangedIds !== []) {
            Problem::query()->whereIn('id', $institutionChangedIds)->update([
                'assigned_employee_id' => null,
                'assigned_institution_team_id' => null,
            ]);
        }

        return $changedIds;
    }

    private function deadlineDiffers(mixed $childDeadline, mixed $rootDeadline): bool
    {
        $child = $childDeadline?->toDateString();
        $rootValue = $rootDeadline?->toDateString();

        return $child !== $rootValue;
    }
}
