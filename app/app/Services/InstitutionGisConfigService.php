<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InstitutionGisConfigService
{
    public const CATEGORY_VALUE_MAPPING_KEY = 'category_value_mapping';

    public const STATUS_VALUE_MAPPING_KEY = 'status_value_mapping';

    public const ADDITIONAL_FIELDS_KEY = 'additional_fields';

    public const REPORTER_VALUE_MODE_KEY = 'reporter_value_mode';

    public const REPORTER_DEFAULT_VALUE_KEY = 'reporter_default_value';

    public const REPORTER_VALUE_MODES = [
        'full_name',
        'resident',
        'custom',
    ];

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
            'service_base_url' => $this->serviceBaseUrlForPayload($config),
            'service_layer_id' => $this->serviceLayerIdForPayload($config),
            'feature_server_url' => $config?->feature_server_url ?? '',
            'attachment_url' => $config?->attachment_url ?? '',
            'metadata_url' => $config?->metadata_url ?? '',
            'metadata_layer_id' => $config?->metadata_layer_id ?? '',
            'wkid' => $config?->wkid ?? 3346,
            'use_field_mapping' => $config?->use_field_mapping ?? false,
            'field_mapping' => $this->normalizeFieldMapping($config?->field_mapping ?? []),
            'status_options' => ProblemStatus::options(),
            'has_active_token' => filled($config?->active_token),
            'active_token_expires_at' => $config?->active_token_expires_at?->toIso8601String(),
        ];
    }

    public function effectiveFieldMapping(InstitutionGisConfig $config): array
    {
        if (! $config->use_field_mapping) {
            return $this->normalizeFieldMapping([]);
        }

        return $this->normalizeFieldMapping($config->field_mapping ?? []);
    }

    public function updateForInstitution(Institution $institution, array $payload): ?InstitutionGisConfig
    {
        $payload = $payload === [] ? ['enabled' => false] : $payload;
        $enabled = filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $useFieldMapping = filter_var($payload['use_field_mapping'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload = $this->withDerivedServiceUrls($payload);

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
            'metadata_url',
            'metadata_layer_id',
            'wkid',
        ]);
        $fillable['enabled'] = $enabled;
        $fillable['use_field_mapping'] = $useFieldMapping;
        $fillable['field_mapping'] = $useFieldMapping
            ? $this->normalizeFieldMapping($payload['field_mapping'] ?? [])
            : null;

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

    public function metadataPayload(InstitutionGisConfig $config): array
    {
        if (! $config->enabled || blank($config->feature_server_url)) {
            throw ValidationException::withMessages([
                'gis' => 'GIS integracija neįjungta arba nenurodytas FeatureServer URL.',
            ]);
        }

        $urls = $this->deriveFeatureServerUrls($config->feature_server_url);
        $metadataLayerId = trim((string) $config->metadata_layer_id);
        if ($metadataLayerId !== '') {
            $urls['layer_id'] = $metadataLayerId;
            $urls['layer_url'] = $urls['service_url'].'/'.$metadataLayerId;
        }

        [$domainsUrl, $domainsQuery] = $this->metadataDomainsRequest($config, $urls);
        $layerJson = $this->getArcGisJson($config, $urls['layer_url'], ['f' => 'json']);
        $domainsJson = $this->getArcGisJson($config, $domainsUrl, $domainsQuery);

        $domainsByName = collect($domainsJson['domains'] ?? [])
            ->filter(fn ($domain) => is_array($domain) && filled($domain['name'] ?? null))
            ->keyBy(fn ($domain) => (string) $domain['name']);

        $fields = collect($layerJson['fields'] ?? [])
            ->filter(fn ($field) => is_array($field) && filled($field['name'] ?? null))
            ->map(function (array $field) use ($domainsByName) {
                $domain = is_array($field['domain'] ?? null) ? $field['domain'] : null;
                $domainName = $domain['name'] ?? null;
                $fullDomain = $domainName ? $domainsByName->get((string) $domainName) : null;
                if (is_array($fullDomain) && blank($domain['codedValues'] ?? null)) {
                    $domain = $fullDomain;
                }

                return [
                    'name' => (string) $field['name'],
                    'alias' => (string) ($field['alias'] ?? $field['name']),
                    'type' => (string) ($field['type'] ?? ''),
                    'domain_name' => $domainName ? (string) $domainName : null,
                    'domain_description' => is_array($domain) && isset($domain['description'])
                        ? (string) $domain['description']
                        : null,
                    'coded_values' => $this->codedValuesPayload(is_array($domain) ? $domain : null),
                ];
            })
            ->values()
            ->all();

        return [
            'layer_url' => $urls['layer_url'],
            'domains_url' => $domainsUrl,
            'layer_id' => $urls['layer_id'],
            'fields' => $fields,
            'domains' => $domainsByName
                ->map(fn (array $domain) => [
                    'name' => (string) $domain['name'],
                    'description' => (string) ($domain['description'] ?? ''),
                    'coded_values' => $this->codedValuesPayload($domain),
                ])
                ->values()
                ->all(),
        ];
    }

    private function payloadIsEmpty(array $payload): bool
    {
        $candidate = Arr::except($payload, ['enabled', 'technical_password', 'use_field_mapping', 'field_mapping']);
        foreach ($candidate as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return trim((string) ($payload['technical_password'] ?? '')) === '';
    }

    private function normalizeFieldMapping(array $mapping): array
    {
        $normalized = [];

        foreach (InstitutionGisConfig::FIELD_MAPPING_SOURCES as $source) {
            $default = InstitutionGisConfig::DEFAULT_FIELD_MAPPING[$source] ?? '';
            $value = trim((string) ($mapping[$source] ?? ''));
            $normalized[$source] = $value !== '' ? $value : $default;
        }

        $normalized[self::CATEGORY_VALUE_MAPPING_KEY] = $this->normalizeCategoryValueMapping(
            is_array($mapping[self::CATEGORY_VALUE_MAPPING_KEY] ?? null)
                ? $mapping[self::CATEGORY_VALUE_MAPPING_KEY]
                : []
        );
        $normalized[self::STATUS_VALUE_MAPPING_KEY] = $this->normalizeStatusValueMapping(
            is_array($mapping[self::STATUS_VALUE_MAPPING_KEY] ?? null)
                ? $mapping[self::STATUS_VALUE_MAPPING_KEY]
                : []
        );
        $normalized[self::REPORTER_VALUE_MODE_KEY] = $this->normalizeReporterValueMode(
            $mapping[self::REPORTER_VALUE_MODE_KEY] ?? 'full_name'
        );
        $normalized[self::REPORTER_DEFAULT_VALUE_KEY] = trim(
            (string) ($mapping[self::REPORTER_DEFAULT_VALUE_KEY] ?? '')
        );
        $normalized[self::ADDITIONAL_FIELDS_KEY] = $this->normalizeAdditionalFields(
            is_array($mapping[self::ADDITIONAL_FIELDS_KEY] ?? null)
                ? $mapping[self::ADDITIONAL_FIELDS_KEY]
                : []
        );

        return $normalized;
    }

    private function normalizeAdditionalFields(array $fields): array
    {
        $normalized = [];
        $seen = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldName = trim((string) ($field['field'] ?? ''));
            $value = trim((string) ($field['value'] ?? ''));
            if ($fieldName === '' || $value === '' || isset($seen[$fieldName])) {
                continue;
            }

            $normalized[] = [
                'field' => Str::limit($fieldName, 128, ''),
                'value' => Str::limit($value, 1000, ''),
            ];
            $seen[$fieldName] = true;

            if (count($normalized) >= 20) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeStatusValueMapping(array $mapping): array
    {
        $normalized = [];
        $allowedStatuses = ProblemStatus::values();

        foreach ($mapping as $localStatus => $gisValue) {
            $key = trim((string) $localStatus);
            if (! in_array($key, $allowedStatuses, true)) {
                continue;
            }

            if (is_array($gisValue)) {
                $code = trim((string) ($gisValue['code'] ?? ''));
                $name = trim((string) ($gisValue['name'] ?? '')) ?: $code;
            } else {
                $code = trim((string) $gisValue);
                $name = $code;
            }

            if ($code === '') {
                continue;
            }

            $normalized[$key] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return $normalized;
    }

    private function normalizeReporterValueMode(mixed $mode): string
    {
        $value = trim((string) $mode);

        return in_array($value, self::REPORTER_VALUE_MODES, true) ? $value : 'full_name';
    }

    private function normalizeCategoryValueMapping(array $mapping): array
    {
        $normalized = [];

        foreach ($mapping as $classifierValueId => $gisValue) {
            $key = (string) $classifierValueId;
            if (! ctype_digit($key) || (int) $key <= 0) {
                continue;
            }

            if (is_array($gisValue)) {
                $code = trim((string) ($gisValue['code'] ?? ''));
                $name = trim((string) ($gisValue['name'] ?? '')) ?: $code;
            } else {
                $code = trim((string) $gisValue);
                $name = $code;
            }

            if ($code === '') {
                continue;
            }

            $normalized[$key] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return $normalized;
    }

    private function codedValuesPayload(?array $domain): array
    {
        return collect($domain['codedValues'] ?? [])
            ->filter(fn ($item) => is_array($item) && array_key_exists('code', $item))
            ->map(fn (array $item) => [
                'name' => (string) ($item['name'] ?? $item['code']),
                'code' => (string) $item['code'],
            ])
            ->values()
            ->all();
    }

    private function metadataDomainsRequest(InstitutionGisConfig $config, array $urls): array
    {
        $metadataUrl = trim((string) $config->metadata_url);
        if ($metadataUrl === '') {
            return [
                $urls['domains_url'],
                array_filter([
                    'f' => 'json',
                    'layers' => $urls['layer_id'],
                ], fn ($value) => $value !== null && $value !== ''),
            ];
        }

        [$url, $query] = $this->splitUrlAndQuery($metadataUrl);
        $query['f'] ??= 'json';
        if (filled($urls['layer_id'])) {
            $query['layers'] = $urls['layer_id'];
        }

        return [$url, $query];
    }

    private function splitUrlAndQuery(string $url): array
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (! $scheme || ! $host) {
            return [$url, $query];
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';

        return [$scheme.'://'.$host.$port.$path, $query];
    }

    private function withDerivedServiceUrls(array $payload): array
    {
        $serviceBaseUrl = trim((string) ($payload['service_base_url'] ?? ''));
        $layerId = trim((string) ($payload['service_layer_id'] ?? ''));
        if ($serviceBaseUrl === '') {
            return $payload;
        }

        if ($layerId === '' && preg_match('~/FeatureServer/(?<layer>\d+)(?:/|$|[?#])~i', $serviceBaseUrl, $matches)) {
            $layerId = (string) $matches['layer'];
        }

        $normalizedBaseUrl = $this->normalizeServiceBaseUrl($serviceBaseUrl);
        if ($normalizedBaseUrl === '' || $layerId === '') {
            return $payload;
        }

        $featureServerUrl = $normalizedBaseUrl.'/FeatureServer/'.$layerId;
        $payload['feature_server_url'] = $featureServerUrl;
        $payload['attachment_url'] = $featureServerUrl;
        $payload['metadata_url'] = $normalizedBaseUrl.'/FeatureServer/queryDomains';
        $payload['metadata_layer_id'] = $layerId;

        return $payload;
    }

    private function normalizeServiceBaseUrl(string $url): string
    {
        $baseUrl = rtrim(preg_replace('~[?#].*$~', '', trim($url)), '/');
        $baseUrl = preg_replace('~/FeatureServer(?:/.*)?$~i', '', $baseUrl) ?: $baseUrl;

        return rtrim($baseUrl, '/');
    }

    private function serviceBaseUrlForPayload(?InstitutionGisConfig $config): string
    {
        if (! $config) {
            return '';
        }

        $source = $config->feature_server_url ?: $config->metadata_url;

        return $source ? $this->normalizeServiceBaseUrl((string) $source) : '';
    }

    private function serviceLayerIdForPayload(?InstitutionGisConfig $config): string
    {
        if (! $config) {
            return '';
        }

        if (filled($config->metadata_layer_id)) {
            return (string) $config->metadata_layer_id;
        }

        if (preg_match('~/FeatureServer/(?<layer>\d+)(?:/|$)~i', (string) $config->feature_server_url, $matches)) {
            return (string) $matches['layer'];
        }

        return '';
    }

    private function getArcGisJson(InstitutionGisConfig $config, string $url, array $query): array
    {
        $response = Http::timeout(30)->get($url, $query);
        $json = $response->json();

        if (($json['error']['code'] ?? null) === 498 || ($json['error']['code'] ?? null) === 499) {
            $token = $this->getValidToken($config);
            $response = Http::timeout(30)->get($url, array_merge($query, ['token' => $token]));
            $json = $response->json();
        }

        if (! $response->successful() || ! is_array($json) || isset($json['error'])) {
            $detail = is_array($json)
                ? json_encode($json['error'] ?? $json, JSON_UNESCAPED_UNICODE)
                : trim((string) $response->body());

            throw new RuntimeException('Nepavyko gauti GIS laukų JSON: '.Str::limit((string) $detail, 500));
        }

        return $json;
    }

    private function deriveFeatureServerUrls(string $featureServerUrl): array
    {
        $baseUrl = rtrim(preg_replace('~[?#].*$~', '', trim($featureServerUrl)), '/');
        $baseUrl = preg_replace('~/(applyEdits|query|queryAttachments)$~i', '', $baseUrl) ?: $baseUrl;

        if (! preg_match('~^(?<service>https?://.+?/FeatureServer)(?:/(?<layer>\d+))?/?$~i', $baseUrl, $matches)) {
            throw ValidationException::withMessages([
                'gis.feature_server_url' => 'FeatureServer URL turi baigtis /FeatureServer arba /FeatureServer/{sluoksnio_id}.',
            ]);
        }

        $serviceUrl = rtrim($matches['service'], '/');
        $layerId = $matches['layer'] ?? null;

        return [
            'service_url' => $serviceUrl,
            'layer_url' => $layerId !== null ? $serviceUrl.'/'.$layerId : $serviceUrl,
            'domains_url' => $serviceUrl.'/queryDomains',
            'layer_id' => $layerId,
        ];
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
