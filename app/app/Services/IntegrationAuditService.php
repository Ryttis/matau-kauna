<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Support\Str;

class IntegrationAuditService
{
    private const REDACTED = '[pašalinta]';

    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    public function log(
        string $action,
        string $system,
        string $direction,
        string $trigger,
        string $service,
        AuditLogResult|string $result,
        ?string $message = null,
        array $metadata = [],
        ?User $actor = null,
        ?Problem $problem = null,
        int|string|null $transferId = null,
    ): void {
        $problemPayload = $metadata['problem'] ?? [];
        if ($problem) {
            $problemPayload = array_merge([
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'institution_id' => $problem->institution_id,
            ], is_array($problemPayload) ? $problemPayload : []);
        }
        if ($transferId !== null) {
            $problemPayload['transfer_id'] = $transferId;
        }

        $payload = array_merge($metadata, [
            'system' => $system,
            'direction' => $direction,
            'trigger' => $trigger,
            'service' => $service,
        ]);

        if ($problemPayload !== []) {
            $payload['problem'] = $problemPayload;
        }

        $this->auditLogService->logIntegrationEvent(
            $action,
            $system,
            $this->sanitize($payload),
            $result,
            $message,
            $actor,
            $problem ? 'Problem' : null,
            $problem?->id,
        );
    }

    public function sanitize(array $metadata): array
    {
        return $this->sanitizeValue($metadata);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitizeValue($childValue, is_string($childKey) ? $childKey : null);
            }

            return $clean;
        }

        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if (is_string($value) && $this->looksLikeSensitiveUrl($key, $value)) {
            return $this->redactUrl($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower(str_replace(['_', '-'], '', $key));

        if (str_contains($normalized, 'password') || str_contains($normalized, 'secret')) {
            return true;
        }

        return in_array($normalized, [
            'token',
            'accesstoken',
            'refreshtoken',
            'activetoken',
            'authorization',
            'headerauthorization',
            'failoduomenys',
            'filebytes',
            'filecontent',
            'base64',
        ], true);
    }

    private function looksLikeSensitiveUrl(?string $key, string $value): bool
    {
        if ($key === null || ! str_contains(Str::lower($key), 'url')) {
            return false;
        }

        return str_contains($value, '?');
    }

    private function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        foreach ($query as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $query[$key] = self::REDACTED;
            }
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$host.$port.$path.'?'.http_build_query($query).$fragment;
    }
}
