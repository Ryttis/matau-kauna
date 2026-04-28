<?php

namespace App\Services;

use App\Models\Problem;

class ProblemMergeContextService
{
    public function forProblem(Problem $problem): array
    {
        $mergeTarget = null;
        $mergedChildren = [];
        $mergedSiblings = [];

        if ($problem->merged_into_problem_id) {
            $problem->load('mergedInto:id,internal_number');
            if ($problem->mergedInto) {
                $mergeTarget = [
                    'id' => $problem->mergedInto->id,
                    'internal_number' => $problem->mergedInto->internal_number,
                ];
            }
            $mergedSiblings = Problem::query()
                ->where('merged_into_problem_id', $problem->merged_into_problem_id)
                ->where('id', '!=', $problem->id)
                ->notArchived()
                ->get(['id', 'internal_number'])
                ->map(fn ($p) => ['id' => $p->id, 'internal_number' => $p->internal_number])
                ->values()
                ->all();
        } else {
            $mergedChildren = $problem->mergedProblems()->notArchived()->get(['id', 'internal_number'])
                ->map(fn ($p) => ['id' => $p->id, 'internal_number' => $p->internal_number])
                ->values()
                ->all();
        }

        return [
            'mergeTarget' => $mergeTarget,
            'mergedChildren' => $mergedChildren,
            'mergedSiblings' => $mergedSiblings,
        ];
    }
}
