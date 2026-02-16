<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemResolutionTermRule extends Model
{
    protected $table = 'problem_resolution_term_rules';

    protected $fillable = [
        'days',
        'institution_id',
        'category',
        'problem_type_id',
        'problem_nature_id',
        'is_active',
    ];

    protected $casts = [
        'days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function problemType(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'problem_type_id');
    }

    public function problemNature(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'problem_nature_id');
    }

    public function matchesProblem(Problem $problem): bool
    {
        if ($this->institution_id !== null && (int) $problem->institution_id !== (int) $this->institution_id) {
            return false;
        }

        $ruleCategory = $this->category !== null && $this->category !== '' ? trim($this->category) : null;
        $problemCategory = $problem->category !== null && $problem->category !== '' ? trim($problem->category) : null;
        if ($ruleCategory !== null && $problemCategory !== $ruleCategory) {
            return false;
        }

        if ($this->problem_nature_id !== null) {
            return (int) $problem->problem_nature_id === (int) $this->problem_nature_id;
        }

        if ($this->problem_type_id !== null && (int) $problem->problem_type_id !== (int) $this->problem_type_id) {
            return false;
        }

        return true;
    }
}
