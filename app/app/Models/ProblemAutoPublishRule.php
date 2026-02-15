<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProblemAutoPublishRule extends Model
{
    protected $fillable = [
        'category',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function matchesProblem(Problem $problem): bool
    {
        $ruleCategory = $this->category !== null && $this->category !== '' ? trim($this->category) : null;
        $problemCategory = $problem->category !== null && $problem->category !== '' ? trim($problem->category) : null;
        if ($ruleCategory !== null && $problemCategory !== $ruleCategory) {
            return false;
        }

        $ruleStatus = $this->status !== null && $this->status !== '' ? trim($this->status) : null;
        $problemStatus = $problem->status !== null && $problem->status !== '' ? trim($problem->status) : null;
        if ($ruleStatus !== null && $problemStatus !== $ruleStatus) {
            return false;
        }

        return true;
    }
}
