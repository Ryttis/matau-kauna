<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotificationTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'is_enabled'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public const KEY_INSTITUTION_ASSIGNMENT = 'institution_assignment';
    public const KEY_EMPLOYEE_ASSIGNMENT = 'employee_assignment';
    public const KEY_DEADLINE_APPROACHING = 'deadline_approaching';
    public const KEY_DEADLINE_OVERDUE = 'deadline_overdue';

    public static function getByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public static function isEnabled(string $key): bool
    {
        $template = static::getByKey($key);
        return $template && $template->is_enabled;
    }
}
