<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\InstitutionDvsConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstitutionDvsConfigService
{
    private const FIELDS = [
        'complaint_api',
        'token_url',
        'username',
        'client_id',
        'gaunami_dokumentai_url',
        'pilieciu_laiskai_url',
        'rusis_url',
        'rusis_kodas',
        'rastines_id',
        'registras_id',
        'darbuotojo_kodas',
        'sudarytojas_kodas',
        'gaunami_registro_kodas',
        'gaunami_registro_id',
        'gaunami_nukreipto_id',
        'gaunami_sudarytojas_id',
        'pilieciu_registro_kodas',
        'pilieciu_registro_id',
        'pilieciu_nukreipto_id',
        'pilieciu_sudarytojas_id',
    ];

    public function settingsPayload(?InstitutionDvsConfig $config): array
    {
        return [
            'enabled' => (bool) ($config?->enabled ?? false),
            'complaint_api' => $config?->complaint_api ?: InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
            'token_url' => $config?->token_url ?? '',
            'username' => $config?->username ?? '',
            'password' => '',
            'has_password' => filled($config?->password),
            'client_id' => $config?->client_id ?? '',
            'client_secret' => '',
            'has_client_secret' => filled($config?->client_secret),
            'gaunami_dokumentai_url' => $config?->gaunami_dokumentai_url ?? '',
            'pilieciu_laiskai_url' => $config?->pilieciu_laiskai_url ?? '',
            'rusis_url' => $config?->rusis_url ?? '',
            'rusis_kodas' => $config?->rusis_kodas ?? '',
            'rusys_options' => $this->normalizeRusysOptions($config?->rusys_options ?? []),
            'rastines_id' => $config?->rastines_id ?? '',
            'registras_id' => $config?->registras_id ?? '',
            'darbuotojo_kodas' => $config?->darbuotojo_kodas ?? '',
            'sudarytojas_kodas' => $config?->sudarytojas_kodas ?? '',
            'gaunami_registro_kodas' => $config?->gaunami_registro_kodas ?? '',
            'gaunami_registro_id' => $config?->gaunami_registro_id ?? '',
            'gaunami_nukreipto_id' => $config?->gaunami_nukreipto_id ?? '',
            'gaunami_sudarytojas_id' => $config?->gaunami_sudarytojas_id ?? '',
            'pilieciu_registro_kodas' => $config?->pilieciu_registro_kodas ?? '',
            'pilieciu_registro_id' => $config?->pilieciu_registro_id ?? '',
            'pilieciu_nukreipto_id' => $config?->pilieciu_nukreipto_id ?? '',
            'pilieciu_sudarytojas_id' => $config?->pilieciu_sudarytojas_id ?? '',
        ];
    }

    public function updateForInstitution(Institution $institution, array $payload): ?InstitutionDvsConfig
    {
        $config = $institution->dvsConfig()->first();

        if (! $config && $this->payloadIsEmpty($payload)) {
            return null;
        }

        $config ??= new InstitutionDvsConfig([
            'institution_id' => $institution->id,
        ]);
        $isNewConfig = ! $config->exists;

        $fillable = [
            'enabled' => filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ...Arr::only($payload, self::FIELDS),
        ];
        if ($isNewConfig && blank($fillable['complaint_api'] ?? null)) {
            $fillable['complaint_api'] = InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI;
        }
        foreach ($fillable as $key => $value) {
            if ($key === 'enabled') {
                continue;
            }
            if ($key === 'complaint_api') {
                $fillable[$key] = filled($value) ? trim((string) $value) : InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI;

                continue;
            }
            $fillable[$key] = filled($value) ? trim((string) $value) : null;
        }

        $incomingPassword = trim((string) ($payload['password'] ?? ''));
        if ($incomingPassword !== '') {
            $fillable['password'] = $incomingPassword;
        }

        $incomingClientSecret = trim((string) ($payload['client_secret'] ?? ''));
        if ($incomingClientSecret !== '') {
            $fillable['client_secret'] = $incomingClientSecret;
        }

        $config->fill($fillable);
        $config->save();

        return $config->fresh();
    }

    public function fetchRusysForInstitution(Institution $institution): array
    {
        $config = $institution->dvsConfig()->first();

        if (! $config) {
            throw new RuntimeException('Įstaiga neturi DVS integracijos nustatymų.');
        }

        foreach ([
            'token_url' => 'Token URL',
            'username' => 'User',
            'password' => 'Password',
            'client_id' => 'Client ID',
            'client_secret' => 'Client secret',
            'rusis_url' => 'Rūšis URL',
        ] as $field => $label) {
            if (blank($config->{$field})) {
                throw new RuntimeException('DVS Rūšis gavimui trūksta lauko: '.$label.'.');
            }
        }

        $token = $this->getToken($config);
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json;odata.metadata=minimal;odata.streaming=true',
            ])
            ->timeout(90)
            ->get($config->rusis_url);

        if (! $response->successful()) {
            throw new RuntimeException('DVS Rūšis API grąžino HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('DVS Rūšis API grąžino netinkamą JSON atsakymą.');
        }

        $status = $payload['status'] ?? $payload['Status'] ?? null;
        $data = $payload['data'] ?? $payload['Data'] ?? null;
        if (! in_array((string) $status, ['0', '1'], true) || ! is_array($data)) {
            throw new RuntimeException('DVS Rūšis API atsakyme nerasta sėkmingų data reikšmių.');
        }

        $options = $this->normalizeRusysOptions($data);
        if ($options === []) {
            throw new RuntimeException('DVS Rūšis API negrąžino tinkamų rūšių.');
        }

        $codes = array_column($options, 'kodas');
        if ($config->rusis_kodas && ! in_array($config->rusis_kodas, $codes, true)) {
            $config->rusis_kodas = null;
        }

        $config->rusys_options = $options;
        $config->save();

        return $this->settingsPayload($config->fresh());
    }

    private function getToken(InstitutionDvsConfig $config): string
    {
        try {
            $response = Http::asForm()
                ->timeout(60)
                ->post($config->token_url, [
                    'grant_type' => 'password',
                    'UserName' => $config->username,
                    'Password' => $config->password,
                    'client_id' => $config->client_id,
                    'client_secret' => $config->client_secret,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Nepavyko prisijungti prie DVS Token API.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('DVS Token API grąžino HTTP '.$response->status().'.');
        }

        $token = $response->json('access_token');
        if (blank($token)) {
            throw new RuntimeException('DVS Token API negrąžino access_token.');
        }

        return (string) $token;
    }

    private function normalizeRusysOptions(array $items): array
    {
        $options = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $kodas = trim((string) ($item['kodas'] ?? $item['Kodas'] ?? ''));
            $pavadinimas = trim((string) ($item['pavadinimas'] ?? $item['Pavadinimas'] ?? ''));
            if ($kodas === '' || $pavadinimas === '') {
                continue;
            }

            $options[] = [
                'id' => $item['id'] ?? $item['Id'] ?? null,
                'pavadinimas' => $pavadinimas,
                'kodas' => $kodas,
            ];
        }

        return array_values($options);
    }

    private function payloadIsEmpty(array $payload): bool
    {
        if (filter_var($payload['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        foreach (array_merge(self::FIELDS, ['password', 'client_secret']) as $field) {
            if ($field === 'complaint_api') {
                continue;
            }
            if (filled($payload[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
