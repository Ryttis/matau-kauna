<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AuditSetting extends Model
{
    public const KEY_ENABLED = 'enabled';

    protected $fillable = ['key', 'value'];

    public static function isEnabled(): bool
    {
        $key = 'audit_setting_enabled';

        return (bool) Cache::remember($key, 60, function () {
            $row = static::query()->where('key', static::KEY_ENABLED)->first();

            return $row ? (bool) filter_var($row->value, FILTER_VALIDATE_BOOLEAN) : true;
        });
    }

    public static function setEnabled(bool $enabled): void
    {
        static::query()->updateOrInsert(
            ['key' => static::KEY_ENABLED],
            ['value' => $enabled ? '1' : '0', 'updated_at' => now()]
        );
        Cache::forget('audit_setting_enabled');
    }

    public static function clearCache(): void
    {
        Cache::forget('audit_setting_enabled');
    }
}
