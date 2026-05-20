<?php

namespace App\Services;

use App\Enums\AuditLogResult;
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
        AuditLog::EVENT_INTEGRATION,
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
            'result' => AuditLogResult::Success->value,
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
            'result' => AuditLogResult::Success->value,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    public function logUserAction(?User $user, string $action, ?string $subjectType, int|string|null $subjectId, AuditLogResult|string $result, ?string $message = null, array $metadata = []): void
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
            'result' => $this->normalizeResult($result),
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logIntegration(string $direction, string $systemName, array $params, AuditLogResult|string $result, ?string $message = null): void
    {
        if (! $this->shouldLog(AuditLog::EVENT_INTEGRATION)) {
            return;
        }
        $this->write([
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'actor_identifier' => 'integration',
            'event_type' => AuditLog::EVENT_INTEGRATION,
            'action' => "integration.{$direction}",
            'result' => $this->normalizeResult($result),
            'message' => $message,
            'metadata' => [
                'system' => $systemName,
                'params' => $params,
            ],
        ]);
    }

    public function logIntegrationEvent(
        string $action,
        string $systemName,
        array $metadata,
        AuditLogResult|string $result,
        ?string $message = null,
        ?User $actor = null,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
    ): void {
        if (! $this->shouldLog(AuditLog::EVENT_INTEGRATION)) {
            return;
        }

        $this->write([
            'actor_type' => $actor ? AuditLog::ACTOR_USER : AuditLog::ACTOR_SYSTEM,
            'actor_id' => $actor?->id,
            'actor_identifier' => $actor ? $actor->email : 'integration',
            'event_type' => AuditLog::EVENT_INTEGRATION,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'result' => $this->normalizeResult($result),
            'message' => $message,
            'metadata' => array_merge(['system' => $systemName], $metadata),
        ]);
    }

    public function logSystem(string $action, AuditLogResult|string $result, ?string $message = null, array $metadata = []): void
    {
        if (! $this->shouldLog(AuditLog::EVENT_SYSTEM)) {
            return;
        }
        $this->write([
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'actor_identifier' => 'system',
            'event_type' => AuditLog::EVENT_SYSTEM,
            'action' => $action,
            'result' => $this->normalizeResult($result),
            'message' => $message,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logAuditConfig(?User $user, string $action, AuditLogResult|string $result, ?string $message = null): void
    {
        $this->write([
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user?->id,
            'actor_identifier' => $user ? $user->email : null,
            'event_type' => AuditLog::EVENT_AUDIT_CONFIG,
            'action' => $action,
            'result' => $this->normalizeResult($result),
            'message' => $message,
        ]);
    }

    private function normalizeResult(AuditLogResult|string $result): string
    {
        return $result instanceof AuditLogResult ? $result->value : $result;
    }

    private function write(array $data): void
    {
        $data['occurred_at'] = $data['occurred_at'] ?? now();
        AuditLog::query()->create($data);
    }
}
