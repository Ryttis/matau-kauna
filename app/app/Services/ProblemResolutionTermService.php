<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ProblemResolutionTermRule;
use Carbon\Carbon;

class ProblemResolutionTermService
{
    public function applyRules(Problem $problem): bool
    {
        if ($problem->completion_deadline !== null) {
            return false;
        }

        if ($problem->institution_id === null) {
            return false;
        }

        $rule = ProblemResolutionTermRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(fn (ProblemResolutionTermRule $r) => $r->matchesProblem($problem));

        if (! $rule) {
            return false;
        }

        $problem->completion_deadline = Carbon::today()->addDays($rule->days);
        $problem->save();

        return true;
    }
}
