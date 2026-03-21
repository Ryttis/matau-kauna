<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemReviewAttachment extends Model
{
    protected $table = 'problem_review_attachments';

    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'problem_review_history_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'kind',
    ];

    public function reviewHistory(): BelongsTo
    {
        return $this->belongsTo(ProblemReviewHistory::class, 'problem_review_history_id');
    }

    public function publicUrl(): string
    {
        $p = ltrim($this->path, '/');

        return '/'.$p;
    }

    public function getUrlAttribute(): string
    {
        return $this->publicUrl();
    }

    public function toPublicPayload(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->publicUrl(),
            'mime_type' => $this->mime_type,
            'kind' => $this->kind,
            'size_bytes' => (int) $this->size_bytes,
        ];
    }
}
