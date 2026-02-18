<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogService
{
    private const ALWAYS_LOG_EVENTS = [
        AuditLog::EVENT_LOGIN,
        AuditLog::EVENT_LOGOUT,
        AuditLog::EVENT_AUDIT_CONFIG,
    ];

    public function shouldLog(string $eventType): bool
    {
        if (in_array($eventType, self::ALWAYS_LOG_EVENTS, true)) {
            return true;
        }
        return AuditSetting::isEnabled();
    }

    public function logLogin(User $user, Request $request): void
    {
        $this->write([
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user->id,
            'actor_identifier' => $user->email,
            'event_type' => AuditLog::EVENT_LOGIN,
            'action' => 'auth.login',
            'result' => AuditLog::RESULT_SUCCESS,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    public function logLogout(User $user, Request $request): void
    {
        $this->write([
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user->id,
            'actor_identifier' => $user->email,
            'event_type' => AuditLog::EVENT_LOGOUT,
            'action' => 'auth.logout',
            'result' => AuditLog::RESULT_SUCCESS,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    public function logUserAction(?User $user, string $action, ?string $subjectType, ?int $subjectId, string $result, ?string $message = null, array $metadata = []): void
    {
        if (! $this->shouldLog(AuditLog::EVENT_USER_ACTION)) {
            return;
        }
        $this->write([
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user?->id,
            'actor_identifier' => $user ? $user->email : null,
            'event_type' => AuditLog::EVENT_USER_ACTION,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'result' => $result,
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logIntegration(string $direction, string $systemName, array $params, string $result, ?string $message = null): void
    {
        if (! $this->shouldLog(AuditLog::EVENT_INTEGRATION)) {
            return;
        }
        $this->write([
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'actor_identifier' => 'integration',
            'event_type' => AuditLog::EVENT_INTEGRATION,
            'action' => "integration.{$direction}",
            'result' => $result,
            'message' => $message,
            'metadata' => [
                'system' => $systemName,
                'params' => $params,
            ],
        ]);
    }

    public function logSystem(string $action, string $result, ?string $message = null, array $metadata = []): void
    {
        if (! $this->shouldLog(AuditLog::EVENT_SYSTEM)) {
            return;
        }
        $this->write([
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'actor_identifier' => 'system',
            'event_type' => AuditLog::EVENT_SYSTEM,
            'action' => $action,
            'result' => $result,
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logAuditConfig(?User $user, string $action, string $result, ?string $message = null): void
    {
        $this->write([
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user?->id,
            'actor_identifier' => $user ? $user->email : null,
            'event_type' => AuditLog::EVENT_AUDIT_CONFIG,
            'action' => $action,
            'result' => $result,
            'message' => $message,
        ]);
    }

    private function write(array $data): void
    {
        $data['occurred_at'] = $data['occurred_at'] ?? now();
        AuditLog::query()->create($data);
    }
}
