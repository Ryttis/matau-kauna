<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProblemReviewHistory extends Model
{
    protected $table = 'problem_review_history';

    protected $fillable = [
        'problem_id',
        'status',
        'responsible',
        'comment',
        'visibility',
    ];

    protected $casts = [];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function reviewAttachments(): HasMany
    {
        return $this->hasMany(ProblemReviewAttachment::class, 'problem_review_history_id')->orderBy('id');
    }
}
