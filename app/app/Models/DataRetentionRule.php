<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRetentionRule extends Model
{
    protected $fillable = [
        'entity_type',
        'archive_after_days',
        'delete_after_days',
        'is_active',
    ];

    protected $casts = [
        'archive_after_days' => 'integer',
        'delete_after_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public const ENTITY_PROBLEM = 'problem';
    public const ENTITY_AUDIT_LOG = 'audit_log';
}
