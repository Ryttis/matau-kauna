<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVersion extends Model
{
    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy';

    public const TYPE_FAQ = 'faq';

    public const TYPE_COOKIES = 'cookies';

    public const TYPE_CONTACTS = 'contacts';

    public const TYPE_HELP = 'help';

    protected $fillable = [
        'type',
        'version',
        'title',
        'body',
        'items',
        'is_published',
        'published_at',
        'require_reconsent',
        'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'require_reconsent' => 'boolean',
    ];

    /** Content types that use a structured `items` payload instead of (or in addition to) HTML `body`. */
    public static function structuredItemsContentTypes(): array
    {
        return [self::TYPE_FAQ];
    }

    public function usesStructuredItems(): bool
    {
        return in_array($this->type, self::structuredItemsContentTypes(), true);
    }

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

    public static function administrableContentTypes(): array
    {
        return [
            self::TYPE_TERMS,
            self::TYPE_FAQ,
            self::TYPE_PRIVACY,
            self::TYPE_COOKIES,
            self::TYPE_CONTACTS,
            self::TYPE_HELP,
        ];
    }
}
