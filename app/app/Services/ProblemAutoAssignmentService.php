<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use Illuminate\Support\Facades\Log;

class ProblemAutoAssignmentService
{
    public function applyRules(Problem $problem): bool
    {
        if ($problem->institution_id !== null) {
            Log::info('ProblemAutoAssignment: skip, problem already has institution', [
                'problem_id' => $problem->id,
                'institution_id' => $problem->institution_id,
            ]);

            return false;
        }

        $rules = ProblemAutoAssignmentRule::query()
            ->where('is_active', true)
            ->whereHas('institution', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();

        Log::info('ProblemAutoAssignment: rules loaded', [
            'problem_id' => $problem->id,
            'problem_category' => $problem->category,
            'rules_count' => $rules->count(),
            'rule_ids' => $rules->pluck('id')->all(),
        ]);

        $rule = $rules->first(fn (ProblemAutoAssignmentRule $r) => $r->matchesProblem($problem));

        if (! $rule) {
            Log::info('ProblemAutoAssignment: no matching rule', [
                'problem_id' => $problem->id,
                'problem_category' => $problem->category,
            ]);

            return false;
        }

        $problem->institution_id = $rule->institution_id;
        $problem->save();

        Log::info('ProblemAutoAssignment: assigned', [
            'problem_id' => $problem->id,
            'institution_id' => $rule->institution_id,
            'rule_id' => $rule->id,
        ]);

        return true;
    }
}
