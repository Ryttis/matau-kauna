<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const ACTOR_USER = 'user';
    public const ACTOR_SYSTEM = 'system';

    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_USER_ACTION = 'user_action';
    public const EVENT_INTEGRATION = 'integration';
    public const EVENT_SYSTEM = 'system';
    public const EVENT_AUDIT_CONFIG = 'audit_config';

    protected $fillable = [
        'occurred_at',
        'actor_type',
        'actor_id',
        'actor_identifier',
        'event_type',
        'action',
        'subject_type',
        'subject_id',
        'result',
        'message',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeOccurredBetween($query, $from, $to)
    {
        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }
        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        return $query;
    }

    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeResult($query, string $result)
    {
        return $query->where('result', $result);
    }

    public function scopeSearch($query, ?string $q)
    {
        if (! $q || ! trim($q)) {
            return $query;
        }
        $q = '%' . trim($q) . '%';
        return $query->where(function ($qry) use ($q) {
            $qry->where('action', 'ilike', $q)
                ->orWhere('message', 'ilike', $q)
                ->orWhere('actor_identifier', 'ilike', $q);
        });
    }
}
