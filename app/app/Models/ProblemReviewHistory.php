<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemReviewHistory extends Model
{
    protected $table = 'problem_review_history';

    protected $fillable = [
        'problem_id',
        'status',
        'responsible',
        'comment',
    ];

    protected $casts = [];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }
}
