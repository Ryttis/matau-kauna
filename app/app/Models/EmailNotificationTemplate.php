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

    public const KEY_INACTIVE_REPORTER_REMINDER = 'inactive_reporter_reminder';

    public const KEY_REPORTER_STATUS_CHANGE = 'reporter_status_change';

    public const KEY_REPORTER_REVIEW_ENTRY = 'reporter_review_entry';

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
