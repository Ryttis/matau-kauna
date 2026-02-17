<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVersion extends Model
{
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_FAQ = 'faq';

    protected $fillable = [
        'type',
        'version',
        'title',
        'body',
        'is_published',
        'published_at',
        'require_reconsent',
        'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'require_reconsent' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public static function getLatestPublished(string $type): ?self
    {
        return static::query()
            ->ofType($type)
            ->published()
            ->orderByDesc('published_at')
            ->first();
    }

    public static function nextVersionNumber(string $type): int
    {
        $max = (int) static::query()->ofType($type)->max('version');

        return $max + 1;
    }
}
