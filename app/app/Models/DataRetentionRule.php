<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRetentionRule extends Model
{
    public const ENTITY_USER = 'user';

    public const ENTITY_INSTITUTION = 'institution';

    public const ENTITY_PROBLEM = 'problem';

    public const ENTITY_CLASSIFIER_VALUE = 'classifier_value';

    public const ENTITY_AUDIT_LOG = 'audit_log';

    protected $fillable = [
        'entity_type',
        'delete_after_days',
        'is_active',
    ];

    protected $casts = [
        'delete_after_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
