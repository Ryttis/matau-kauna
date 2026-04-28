<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProblemAttachment extends Model
{
    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'problem_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'kind',
        'source',
    ];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function gisTransferAttachments(): HasMany
    {
        return $this->hasMany(ProblemGisTransferAttachment::class, 'problem_attachment_id');
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
