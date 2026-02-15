<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ProblemAutoPublishRule;

class ProblemAutoPublishService
{
    public function applyRules(Problem $problem): bool
    {
        $rules = ProblemAutoPublishRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $matched = $rules->first(fn (ProblemAutoPublishRule $r) => $r->matchesProblem($problem));
        if (! $matched) {
            return false;
        }

        if ($problem->is_published_publicly) {
            return false;
        }

        $problem->is_published_publicly = true;
        $problem->save();

        return true;
    }
}
