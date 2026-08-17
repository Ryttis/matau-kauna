<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IntegrationAuditService
{
    private const REDACTED = '[pašalinta]';

    private const TRUNCATED = '[sutrumpinta]';

    private const MAX_STRING_LENGTH = 2000;

    private const MAX_ARRAY_ITEMS = 200;

    private const MAX_DEPTH = 12;

    private const MAX_TOTAL_BYTES = 262144;

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
        $clean = $this->sanitizeValue($metadata);

        return $this->capSize(is_array($clean) ? $clean : []);
    }

    private function sanitizeValue(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return self::TRUNCATED;
            }

            $clean = [];
            $index = 0;
            foreach ($value as $childKey => $childValue) {
                if ($index >= self::MAX_ARRAY_ITEMS) {
                    $clean[] = self::TRUNCATED.': '.(count($value) - self::MAX_ARRAY_ITEMS).' įrašų';
                    break;
                }
                $clean[$childKey] = $this->sanitizeValue(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                    $depth + 1,
                );
                $index++;
            }

            return $clean;
        }

        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if (is_float($value) && ! is_finite($value)) {
            return null;
        }

        if (is_string($value) && $this->looksLikeSensitiveUrl($key, $value)) {
            $value = $this->redactUrl($value);
        }

        return is_string($value) ? $this->safeString($value) : $value;
    }

    /**
     * Metaduomenys saugomi JSON stulpelyje, todėl netaisyta UTF-8 seka ar per ilga reikšmė
     * neturi sugriauti viso audito įrašo.
     */
    private function safeString(string $value): string
    {
        $clean = str_replace("\0", '', $value);
        if (! mb_check_encoding($clean, 'UTF-8')) {
            $clean = mb_convert_encoding($clean, 'UTF-8', 'UTF-8');
        }

        return Str::limit($clean, self::MAX_STRING_LENGTH, ' '.self::TRUNCATED);
    }

    /**
     * Diagnostikai svarbiausi laukai (klaida, problema, sistema) paliekami paskutiniai,
     * pirmiausia išmetamas didžiausias turinys - siųstas payload.
     */
    private function capSize(array $metadata): array
    {
        foreach ([['request', 'params'], ['request'], ['response'], ['files']] as $path) {
            if ($this->encodedSize($metadata) <= self::MAX_TOTAL_BYTES) {
                return $metadata;
            }
            if (Arr::has($metadata, $path)) {
                Arr::set($metadata, implode('.', $path), self::TRUNCATED);
            }
        }

        if ($this->encodedSize($metadata) <= self::MAX_TOTAL_BYTES) {
            return $metadata;
        }

        return Arr::only($metadata, ['system', 'direction', 'trigger', 'service', 'problem', 'error'])
            + ['metadata_truncated' => true];
    }

    private function encodedSize(array $metadata): int
    {
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? PHP_INT_MAX : strlen($encoded);
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
