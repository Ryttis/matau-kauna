<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;

class ProblemAutoAssignmentService
{
    public function __construct(
        private ProblemResolutionTermService $resolutionTermService
    ) {}

    public function applyRules(Problem $problem): bool
    {
        if ($problem->institution_id !== null) {
            return false;
        }

        $rules = ProblemAutoAssignmentRule::query()
            ->where('is_active', true)
            ->whereHas('institution', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();

        $rule = $rules->first(fn (ProblemAutoAssignmentRule $r) => $r->matchesProblem($problem));

        if (! $rule) {
            return false;
        }

        $problem->institution_id = $rule->institution_id;
        $problem->save();

        $this->resolutionTermService->applyRules($problem);

        return true;
    }
}
