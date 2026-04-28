<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstitutionGisConfigService
{
    public function settingsPayload(?InstitutionGisConfig $config): array
    {
        return [
            'enabled' => $config?->enabled ?? false,
            'generate_token_url' => $config?->generate_token_url ?? '',
            'technical_username' => $config?->technical_username ?? '',
            'technical_password' => '',
            'has_technical_password' => filled($config?->technical_password),
            'client_type' => $config?->client_type ?? 'referer',
            'token_expiration_minutes' => $config?->token_expiration_minutes ?? 60,
            'feature_server_url' => $config?->feature_server_url ?? '',
            'attachment_url' => $config?->attachment_url ?? '',
            'wkid' => $config?->wkid ?? 3346,
            'has_active_token' => filled($config?->active_token),
            'active_token_expires_at' => $config?->active_token_expires_at?->toIso8601String(),
        ];
    }

    public function updateForInstitution(Institution $institution, array $payload): ?InstitutionGisConfig
    {
        $payload = $payload === [] ? ['enabled' => false] : $payload;
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $config = $institution->gisConfig()->first();

        if (! $config && ! $enabled && $this->payloadIsEmpty($payload)) {
            return null;
        }

        $config ??= new InstitutionGisConfig([
            'institution_id' => $institution->id,
        ]);

        $currentPassword = $config->technical_password;
        $incomingPassword = trim((string) ($payload['technical_password'] ?? ''));

        if ($enabled && $incomingPassword === '' && ! filled($currentPassword)) {
            throw ValidationException::withMessages([
                'gis.technical_password' => 'Įveskite techninio vartotojo slaptažodį.',
            ]);
        }

        $fillable = Arr::only($payload, [
            'generate_token_url',
            'technical_username',
            'client_type',
            'token_expiration_minutes',
            'feature_server_url',
            'attachment_url',
            'wkid',
        ]);
        $fillable['enabled'] = $enabled;

        if (array_key_exists('wkid', $fillable) && ! filled($fillable['wkid'])) {
            $fillable['wkid'] = 3346;
        }

        if ($incomingPassword !== '') {
            $fillable['technical_password'] = $incomingPassword;
        }

        $connectionFieldsChanged = $config->exists && (
            ($fillable['generate_token_url'] ?? null) !== $config->generate_token_url
            || ($fillable['technical_username'] ?? null) !== $config->technical_username
            || array_key_exists('technical_password', $fillable)
            || ($fillable['client_type'] ?? null) !== $config->client_type
            || (int) ($fillable['token_expiration_minutes'] ?? 0) !== (int) $config->token_expiration_minutes
            || ($fillable['feature_server_url'] ?? null) !== $config->feature_server_url
            || ($fillable['attachment_url'] ?? null) !== $config->attachment_url
        );

        if (! $enabled || $connectionFieldsChanged) {
            $fillable['active_token'] = null;
            $fillable['active_token_expires_at'] = null;
        }

        $config->fill($fillable);
        $config->save();

        return $config->fresh();
    }

    public function getValidToken(InstitutionGisConfig $config, bool $forceRefresh = false): string
    {
        if (! $config->enabled) {
            throw ValidationException::withMessages([
                'gis' => 'GIS siuntimas šiai įstaigai nėra įjungtas.',
            ]);
        }

        $hasUsableToken = filled($config->active_token)
            && $config->active_token_expires_at !== null
            && $config->active_token_expires_at->greaterThan(now()->addMinute());

        if (! $forceRefresh && $hasUsableToken) {
            return (string) $config->active_token;
        }

        return $this->refreshToken($config);
    }

    public function refreshToken(InstitutionGisConfig $config): string
    {
        if (
            blank($config->generate_token_url)
            || blank($config->technical_username)
            || blank($config->technical_password)
            || blank($config->client_type)
            || blank($config->token_expiration_minutes)
        ) {
            throw ValidationException::withMessages([
                'gis' => 'Trūksta ArcGIS autentifikacijos nustatymų.',
            ]);
        }

        $payload = [
            'username' => $config->technical_username,
            'password' => $config->technical_password,
            'client' => $config->client_type,
            'f' => 'json',
            'expiration' => $config->token_expiration_minutes,
        ];

        if (Str::lower($config->client_type) === 'referer') {
            $payload['referer'] = $this->deriveRefererOrigin($config->generate_token_url);
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post($config->generate_token_url, $payload);

        $json = $response->json();
        if (! is_array($json) || ! isset($json['token'], $json['expires'])) {
            $detail = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : trim((string) $response->body());

            throw ValidationException::withMessages([
                'gis' => 'Nepavyko gauti ArcGIS tokeno: '.Str::limit($detail, 500),
            ]);
        }

        $config->forceFill([
            'active_token' => (string) $json['token'],
            'active_token_expires_at' => Carbon::createFromTimestampMs((int) $json['expires']),
        ])->save();

        return (string) $config->active_token;
    }

    private function payloadIsEmpty(array $payload): bool
    {
        $candidate = Arr::except($payload, ['enabled', 'technical_password']);
        foreach ($candidate as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return trim((string) ($payload['technical_password'] ?? '')) === '';
    }

    private function deriveRefererOrigin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return rtrim("{$scheme}://{$host}{$port}", '/');
    }
}
