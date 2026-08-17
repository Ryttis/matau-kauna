<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Models\AuditLog;
use App\Models\AuditSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogService
{
    private const ALWAYS_LOG_EVENTS = [
        AuditLog::EVENT_LOGIN,
        AuditLog::EVENT_LOGOUT,
        AuditLog::EVENT_AUDIT_CONFIG,
        AuditLog::EVENT_INTEGRATION,
    ];

    private const MAX_MESSAGE_LENGTH = 2000;

    private const MAX_METADATA_BYTES = 262144;

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

    /**
     * Audito įrašas negali sugriauti pagrindinio veiksmo: jei nepavyksta įrašyti su metaduomenimis,
     * bandoma be jų, o visiškai nepavykus - klaida rašoma į Laravel žurnalą.
     */
    private function write(array $data): void
    {
        $data['occurred_at'] = $data['occurred_at'] ?? now();
        $data['message'] = $this->safeText($data['message'] ?? null);
        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->safeMetadata($data['metadata']);
        }

        try {
            AuditLog::query()->create($data);

            return;
        } catch (\Throwable $first) {
            // Svarbiausia išsaugoti patį įrašą (veiksmas, rezultatas, žinutė), todėl bandome be metaduomenų.
        }

        try {
            AuditLog::query()->create(array_merge($data, [
                'metadata' => [
                    'audit_write_degraded' => true,
                    'audit_write_error' => $this->safeText($first->getMessage()),
                ],
            ]));

            return;
        } catch (\Throwable $second) {
            Log::error('Nepavyko įrašyti audito žurnalo įrašo.', [
                'event_type' => $data['event_type'] ?? null,
                'action' => $data['action'] ?? null,
                'result' => $data['result'] ?? null,
                'message' => $data['message'] ?? null,
                'first_error' => $first->getMessage(),
                'second_error' => $second->getMessage(),
            ]);
        }
    }

    private function safeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = str_replace("\0", '', $text);
        if (! mb_check_encoding($clean, 'UTF-8')) {
            $clean = mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
        }

        return Str::limit($clean, self::MAX_MESSAGE_LENGTH);
    }

    private function safeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata) || $metadata === []) {
            return null;
        }

        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && strlen($encoded) <= self::MAX_METADATA_BYTES) {
            return $metadata;
        }

        $repaired = json_decode((string) json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ), true);

        if (is_array($repaired)) {
            $repairedEncoded = json_encode($repaired, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($repairedEncoded !== false && strlen($repairedEncoded) <= self::MAX_METADATA_BYTES) {
                return $repaired;
            }
        }

        return [
            'audit_metadata_truncated' => true,
            'audit_metadata_bytes' => $encoded === false ? null : strlen($encoded),
        ];
    }
}
