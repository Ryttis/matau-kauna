<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemAutoAssignmentRule extends Model
{
    protected $fillable = [
        'name',
        'category',
        'institution_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function matchesProblem(Problem $problem): bool
    {
        $ruleCategory = $this->category !== null && $this->category !== '' ? trim($this->category) : null;
        $problemCategory = $problem->category !== null && $problem->category !== '' ? trim($problem->category) : null;

        return $ruleCategory !== null && $problemCategory === $ruleCategory;
    }
}
