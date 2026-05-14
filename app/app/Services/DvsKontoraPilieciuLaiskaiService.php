<?php

namespace App\Services;

use App\Models\Problem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DvsKontoraPilieciuLaiskaiService
{
    public function buildPayload(Problem $problem, array $overrides = []): array
    {
        $problem->loadMissing('reportedByUser:id,name,surname,email,phone');

        $config = config('services.dvs_kontora.pilieciu_laiskai', []);
        $registroId = $overrides['registroId'] ?? $config['registro_id'] ?? null;
        $registroKodas = $overrides['registroKodas'] ?? $config['registro_kodas'] ?? null;
        $nukreiptoId = $overrides['nukreiptoId'] ?? $config['nukreipto_id'] ?? null;

        if (blank($registroId) && blank($registroKodas)) {
            throw new InvalidArgumentException('DVS PilieciuLaiskai needs registroId or registroKodas.');
        }

        if (blank($nukreiptoId)) {
            throw new InvalidArgumentException('DVS PilieciuLaiskai needs nukreiptoId.');
        }

        $reporter = $problem->reportedByUser;
        $author = $reporter
            ? trim(implode(' ', array_filter([$reporter->name, $reporter->surname]))) ?: $reporter->email
            : 'Gyventojas';

        $createdAt = $problem->created_at ?? now();
        $title = trim(implode(' - ', array_filter([
            $problem->internal_number,
            $problem->category,
        ]))) ?: 'Piliecio laiskas';

        $payload = [
            'regData' => $createdAt->toIso8601String(),
            'dokData' => $createdAt->toIso8601String(),
            'dokNr' => $problem->internal_number,
            'autorius' => $author,
            'adresas' => $problem->address,
            'elektroninisPastas' => $reporter?->email,
            'telefonoNumeris' => $reporter?->phone,
            'elektroninis' => true,
            'antraste' => Str::limit($title, 250, ''),
            'pastabos' => $problem->description,
            'nukreiptoId' => (int) $nukreiptoId,
        ];

        if (! blank($registroId)) {
            $payload['registroId'] = (int) $registroId;
        } else {
            $payload['registroKodas'] = (string) $registroKodas;
        }

        if (! blank($problem->completion_deadline)) {
            $payload['terminoData'] = $problem->completion_deadline->toIso8601String();
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    public function createFromProblem(Problem $problem, array $overrides = []): array
    {
        $config = config('services.dvs_kontora');
        $payload = $this->buildPayload($problem, $overrides);
        $token = $this->getToken($config);

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json;odata.metadata=minimal;odata.streaming=true',
            ])
            ->timeout(90)
            ->post($config['base_url'].'/api/PilieciuLaiskai', $payload);

        $response->throw();

        return $response->json();
    }

    public function getById(int|string $id): array
    {
        $config = config('services.dvs_kontora');
        $token = $this->getToken($config);

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json;odata.metadata=minimal;odata.streaming=true',
            ])
            ->timeout(90)
            ->get($config['base_url'].'/api/PilieciuLaiskai/'.$id);

        $response->throw();

        return $response->json();
    }

    private function getToken(array $config): string
    {
        $tokenRequest = [
            'grant_type' => 'password',
            'UserName' => $config['username'],
            'Password' => $config['password'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ];

        if (! blank($config['ad_domain_id'])) {
            $tokenRequest['adDomainId'] = $config['ad_domain_id'];
        }

        $response = Http::asForm()
            ->timeout(60)
            ->post($config['base_url'].'/Token', $tokenRequest);

        $response->throw();

        return (string) $response->json('access_token');
    }
}
