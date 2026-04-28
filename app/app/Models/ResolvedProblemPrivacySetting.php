<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResolvedProblemPrivacySetting extends Model
{
    public const DAYS_AFTER_RESOLUTION_MIN = 30;

    public const DAYS_AFTER_RESOLUTION_MAX = 3650;

    public const DAYS_AFTER_RESOLUTION_DEFAULT = 180;

    protected $fillable = ['days_after_resolution', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function get(): ?self
    {
        return static::query()->first();
    }

    public static function firstOrCreateDefault(): self
    {
        $row = static::get();
        if ($row) {
            return $row;
        }

        return static::create([
            'days_after_resolution' => static::DAYS_AFTER_RESOLUTION_DEFAULT,
            'is_active' => false,
        ]);
    }
}
