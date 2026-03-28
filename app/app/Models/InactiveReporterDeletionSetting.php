<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InactiveReporterDeletionSetting extends Model
{
    public const INACTIVITY_DAYS_MIN = 30;

    public const INACTIVITY_DAYS_MAX = 3650;

    public const INACTIVITY_DAYS_DEFAULT = 180;

    protected $fillable = ['inactivity_days', 'is_active'];

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
            'inactivity_days' => static::INACTIVITY_DAYS_DEFAULT,
            'is_active' => false,
        ]);
    }

    public function reminderDays(): int
    {
        return (int) ceil($this->inactivity_days / 2);
    }
}
