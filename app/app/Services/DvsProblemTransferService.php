<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Enums\ProblemStatus;
use App\Models\InstitutionDvsConfig;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemDvsTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DvsProblemTransferService
{
    private const DVS_ACCEPT_HEADER = 'application/json;odata.metadata=minimal;odata.streaming=true';

    public function __construct(
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private IntegrationAuditService $integrationAuditService,
    ) {}

    public function transferProblem(Problem $problem, array $auditContext = []): array
    {
        $problem->loadMissing([
            'institution.dvsConfig',
            'attachments',
            'reportedByUser:id,name,surname,email,phone',
            'problemNature:id,name',
            'problemType:id,name',
            'dvsTransfer',
        ]);

        $config = null;
        $api = null;
        $transfer = null;
        $payload = null;
        $post = null;

        try {
            $config = $this->activeConfigForProblem($problem);
            $api = $this->selectedApi($config);

            $transfer = ProblemDvsTransfer::firstOrCreate(
                ['problem_id' => $problem->id],
                [
                    'institution_dvs_config_id' => $config->id,
                    'complaint_api' => $api,
                    'send_status' => ProblemDvsTransfer::STATUS_PENDING,
                ]
            );

            $transfer->forceFill([
                'institution_dvs_config_id' => $config->id,
                'complaint_api' => $api,
            ])->save();

            if (! filled($transfer->external_object_id)) {
                $payload = $this->buildPayload($problem, $config, $api);
                $post = $this->postJsonWithToken($config, $this->sendUrl($config, $api), $payload);
                $response = $post['json'];
                $data = $this->responseData($response);
                $objectId = $this->firstFilled($data, ['objectId', 'ObjectId', 'id', 'Id']);

                if (blank($objectId)) {
                    throw new RuntimeException('DVS API negrąžino sukurto objekto ID.');
                }

                $transfer->forceFill([
                    'external_object_id' => (string) $objectId,
                    'external_reg_nr' => $this->firstFilled($data, ['regNr', 'RegNr', 'registroNr', 'RegistroNr']),
                    'send_status' => ProblemDvsTransfer::STATUS_SENT,
                    'last_error' => null,
                    'sent_at' => now(),
                    'last_response_json' => $response,
                ])->save();
            }

            $freshTransfer = $transfer->fresh();
            $this->auditDvs(
                'integration.dvs.send',
                'outbound',
                $this->auditTrigger($auditContext),
                $this->dvsServiceName($api),
                AuditLogResult::Success,
                null,
                [
                    'request' => [
                        'method' => $post === null ? null : 'POST',
                        'url' => $this->sendUrl($config, $api),
                        'params' => $payload,
                    ],
                    'response' => [
                        'http_status' => $post['http_status'] ?? null,
                        'external_object_id' => $freshTransfer?->external_object_id,
                        'external_reg_nr' => $freshTransfer?->external_reg_nr,
                        'send_status' => $freshTransfer?->send_status,
                        'already_sent' => $post === null,
                    ],
                    'files' => $this->problemFiles($problem, 'outbound'),
                ],
                $auditContext,
                $problem,
                $freshTransfer?->id,
            );

            return [
                'transfer' => $freshTransfer,
                'created' => true,
            ];
        } catch (\Throwable $e) {
            if ($transfer) {
                $transfer->forceFill([
                    'send_status' => ProblemDvsTransfer::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                ])->save();
            }

            $this->auditDvs(
                'integration.dvs.send',
                'outbound',
                $this->auditTrigger($auditContext),
                $this->dvsServiceName($api),
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => [
                        'method' => 'POST',
                        'url' => $config && $api ? $this->sendUrl($config, $api) : null,
                        'params' => $payload,
                    ],
                    'response' => [
                        'http_status' => $post['http_status'] ?? null,
                        'send_status' => $transfer?->fresh()?->send_status,
                    ],
                    'files' => $this->problemFiles($problem, 'outbound'),
                    'error' => $this->errorPayload($e),
                ],
                $auditContext,
                $problem,
                $transfer?->id,
            );

            throw $e;
        }
    }

    public function syncFromDvs(ProblemDvsTransfer $transfer, array $auditContext = []): array
    {
        $transfer->loadMissing(['config', 'problem.reportedByUser']);
        $config = $transfer->config;
        $api = $config ? $this->selectedApi($config, $transfer->complaint_api) : $transfer->complaint_api;
        $url = $config && filled($transfer->external_object_id)
            ? $this->statusUrl($config, $api, $transfer->external_object_id)
            : null;
        $get = null;

        if (! $config || ! $config->enabled || ! filled($transfer->external_object_id)) {
            $result = [
                'status_changed' => false,
                'old_status' => null,
                'new_status' => null,
                'answer_found' => false,
            ];

            $this->auditDvs(
                'integration.dvs.pull_status',
                'inbound',
                $this->auditTrigger($auditContext),
                $this->dvsServiceName($api),
                AuditLogResult::Success,
                null,
                [
                    'request' => [
                        'method' => null,
                        'url' => $url,
                        'params' => ['external_object_id' => $transfer->external_object_id],
                    ],
                    'response' => array_merge($result, ['skipped' => true]),
                ],
                $auditContext,
                $transfer->problem,
                $transfer->id,
            );

            return $result;
        }

        try {
            $get = $this->getJsonWithToken($config, $url);
            $response = $get['json'];
            $data = $this->responseData($response);
        } catch (\Throwable $e) {
            $this->auditDvs(
                'integration.dvs.pull_status',
                'inbound',
                $this->auditTrigger($auditContext),
                $this->dvsServiceName($api),
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $url,
                        'params' => ['external_object_id' => $transfer->external_object_id],
                    ],
                    'response' => ['http_status' => $get['http_status'] ?? null],
                    'error' => $this->errorPayload($e),
                ],
                $auditContext,
                $transfer->problem,
                $transfer->id,
            );

            throw $e;
        }

        $answerDateValue = $api === InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI
            ? $this->firstFilled($data, ['atsakytaData', 'AtsakytaData', 'ivykdymoData', 'IvykdymoData'])
            : $this->firstFilled($data, ['atsakymoData', 'AtsakymoData']);
        $answerNotes = $api === InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI
            ? $this->firstFilled($data, ['atsakymoPastabos', 'AtsakymoPastabos', 'ivykdymoPastabos', 'IvykdymoPastabos'])
            : $this->firstFilled($data, ['atsakymoPastabos', 'AtsakymoPastabos']);
        $answerDate = $this->parseDate($answerDateValue);

        $statusChanged = false;
        $oldStatus = null;
        $newStatus = null;

        if ($answerDate && $transfer->problem && $transfer->problem->status !== ProblemStatus::RESOLVED->value) {
            $oldStatus = (string) $transfer->problem->status;
            $newStatus = ProblemStatus::RESOLVED->value;
            $transfer->problem->forceFill([
                'status' => $newStatus,
                'status_updated_at' => now(),
            ])->save();
            $statusChanged = true;

            $main = $transfer->problem->merged_into_problem_id
                ? Problem::find($transfer->problem->merged_into_problem_id)
                : $transfer->problem;

            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges(
                    $main,
                    $oldStatus,
                    $newStatus
                );
            }
        }

        $transfer->forceFill([
            'last_checked_at' => now(),
            'answer_date' => $answerDate,
            'answer_notes' => filled($answerNotes) ? (string) $answerNotes : $transfer->answer_notes,
            'last_response_json' => $response,
            'send_status' => $answerDate ? ProblemDvsTransfer::STATUS_RESOLVED : $transfer->send_status,
            'last_error' => null,
        ])->save();

        $result = [
            'status_changed' => $statusChanged,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'answer_found' => $answerDate !== null,
        ];

        $this->auditDvs(
            'integration.dvs.pull_status',
            'inbound',
            $this->auditTrigger($auditContext),
            $this->dvsServiceName($api),
            AuditLogResult::Success,
            null,
            [
                'request' => [
                    'method' => 'GET',
                    'url' => $url,
                    'params' => ['external_object_id' => $transfer->external_object_id],
                ],
                'response' => array_merge($result, [
                    'http_status' => $get['http_status'] ?? null,
                    'answer_date' => $answerDate?->toIso8601String(),
                    'answer_notes_present' => filled($answerNotes),
                    'send_status' => $transfer->fresh()->send_status,
                ]),
            ],
            $auditContext,
            $transfer->problem,
            $transfer->id,
        );

        return $result;
    }

    public function buildPayload(Problem $problem, InstitutionDvsConfig $config, ?string $api = null): array
    {
        $problem->loadMissing([
            'attachments',
            'reportedByUser:id,name,surname,email,phone',
            'problemNature:id,name',
            'problemType:id,name',
        ]);

        $api = $this->selectedApi($config, $api);

        return $api === InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI
            ? $this->buildGaunamiDokumentaiPayload($problem, $config)
            : $this->buildPilieciuLaiskaiPayload($problem, $config);
    }

    private function activeConfigForProblem(Problem $problem): InstitutionDvsConfig
    {
        if (! $problem->institution_id || ! $problem->institution?->dvsConfig?->enabled) {
            throw new RuntimeException('Šiai problemai nepriskirta aktyvi DVS integracija.');
        }

        return $problem->institution->dvsConfig;
    }

    private function buildGaunamiDokumentaiPayload(Problem $problem, InstitutionDvsConfig $config): array
    {
        foreach ([
            'gaunami_dokumentai_url' => 'GaunamiDokumentai URL',
            'rusis_kodas' => 'Rūšis',
            'gaunami_nukreipto_id' => 'Nukreipto ID',
            'gaunami_sudarytojas_id' => 'Sudarytojas ID',
        ] as $field => $label) {
            if (blank($config->{$field})) {
                throw new RuntimeException('DVS siuntimui trūksta lauko: '.$label.'.');
            }
        }

        if (blank($config->gaunami_registro_kodas) && blank($config->gaunami_registro_id)) {
            throw new RuntimeException('DVS siuntimui trūksta lauko: Registro Kodas.');
        }

        $registeredAt = now();
        $documentAt = $problem->created_at ?? $registeredAt;
        $payload = [
            'rusiesKodas' => (string) $config->rusis_kodas,
            'nukreiptoId' => (int) $config->gaunami_nukreipto_id,
            'autoriausId' => (int) $config->gaunami_sudarytojas_id,
            'regData' => $registeredAt->toIso8601String(),
            'dokData' => $documentAt->toIso8601String(),
            'dokNr' => $problem->internal_number,
            'patikslinimas' => $this->reporterName($problem),
            'antraste' => $this->dvsTitle($problem),
            'pastabos' => $this->descriptionWithProblemUrl($problem),
            'elektroninis' => true,
            'failai' => $this->attachmentsPayload($problem, $config->gaunami_failu_registro_kodas),
        ];

        $payload = array_merge($payload, $this->registryPayload($config->gaunami_registro_kodas, $config->gaunami_registro_id));

        if (! blank($problem->completion_deadline)) {
            $payload['terminoData'] = $problem->completion_deadline->toIso8601String();
        }

        return $this->stripEmpty($payload);
    }

    private function buildPilieciuLaiskaiPayload(Problem $problem, InstitutionDvsConfig $config): array
    {
        foreach ([
            'pilieciu_laiskai_url' => 'PilieciuLaiskai URL',
            'pilieciu_nukreipto_id' => 'Nukreipto ID',
        ] as $field => $label) {
            if (blank($config->{$field})) {
                throw new RuntimeException('DVS siuntimui trūksta lauko: '.$label.'.');
            }
        }

        if (blank($config->pilieciu_registro_kodas) && blank($config->pilieciu_registro_id)) {
            throw new RuntimeException('DVS siuntimui trūksta lauko: Registro Kodas.');
        }

        $registeredAt = now();
        $documentAt = $problem->created_at ?? $registeredAt;
        $payload = [
            'regData' => $registeredAt->toIso8601String(),
            'dokData' => $documentAt->toIso8601String(),
            'dokNr' => $problem->internal_number,
            'autorius' => $this->reporterName($problem) ?: 'Gyventojas',
            'elektroninisPastas' => $problem->reportedByUser?->email,
            'telefonoNumeris' => $problem->reportedByUser?->phone,
            'elektroninis' => true,
            'antraste' => $this->dvsTitle($problem),
            'pastabos' => $this->descriptionWithProblemUrl($problem),
            'nukreiptoId' => (int) $config->pilieciu_nukreipto_id,
            'failai' => $this->attachmentsPayload($problem, $config->pilieciu_failu_registro_kodas),
        ];

        $payload = array_merge($payload, $this->registryPayload($config->pilieciu_registro_kodas, $config->pilieciu_registro_id));

        if (! blank($problem->completion_deadline)) {
            $payload['terminoData'] = $problem->completion_deadline->toIso8601String();
        }

        return $this->stripEmpty($payload);
    }

    private function attachmentsPayload(Problem $problem, ?string $failuRegistroKodas): array
    {
        if ($problem->attachments->isEmpty()) {
            return [];
        }

        if (blank($failuRegistroKodas)) {
            throw new RuntimeException('DVS siuntimui su priedais trūksta lauko: Failų Registro Kodas.');
        }

        return $problem->attachments
            ->values()
            ->map(function (ProblemAttachment $attachment, int $index) use ($failuRegistroKodas) {
                $absolutePath = public_path(ltrim((string) $attachment->path, '/'));
                if (! is_file($absolutePath)) {
                    throw new RuntimeException('Nepavyko rasti DVS priedo failo serveryje.');
                }

                return [
                    'registroKodas' => (string) $failuRegistroKodas,
                    'failoPavadinimas' => $attachment->original_name ?: basename($absolutePath),
                    'failoDuomenys' => base64_encode((string) file_get_contents($absolutePath)),
                    'pagrindinis' => $index === 0,
                ];
            })
            ->values()
            ->all();
    }

    private function registryPayload(?string $registroKodas, ?string $registroId): array
    {
        if (! blank($registroKodas)) {
            return ['registroKodas' => (string) $registroKodas];
        }

        if (! blank($registroId)) {
            return [
                'registroId' => is_numeric($registroId)
                    ? (int) $registroId
                    : (string) $registroId,
            ];
        }

        return [];
    }

    private function reporterName(Problem $problem): string
    {
        $reporter = $problem->reportedByUser;
        if (! $reporter) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $reporter->name,
            $reporter->surname,
        ]))) ?: (string) $reporter->email;
    }

    private function descriptionWithProblemUrl(Problem $problem): string
    {
        $description = trim((string) $problem->description);
        $urlLine = 'Nuoroda: '.route('problems.show', $problem->id);

        return $description === ''
            ? $urlLine
            : $description."\n\n".$urlLine;
    }

    private function dvsTitle(Problem $problem): string
    {
        $number = trim((string) $problem->internal_number);
        $category = trim((string) (
            $problem->problemType?->name
            ?? $problem->category
            ?? 'Problema'
        ));
        $nature = trim((string) ($problem->problemNature?->name ?? ''));
        $address = trim((string) $problem->address);

        $title = trim(implode(' - ', array_filter([$number, $category])));
        if ($nature !== '') {
            $title .= ', '.$nature;
        }
        if ($address !== '') {
            $title .= ' Problemos adresas: '.$address;
        }

        return Str::limit($title !== '' ? $title : 'Problema', 250, '');
    }

    private function sendUrl(InstitutionDvsConfig $config, string $api): string
    {
        return $api === InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI
            ? (string) $config->gaunami_dokumentai_url
            : (string) $config->pilieciu_laiskai_url;
    }

    private function statusUrl(InstitutionDvsConfig $config, string $api, int|string $id): string
    {
        return rtrim($this->sendUrl($config, $api), '/').'/'.rawurlencode((string) $id);
    }

    private function selectedApi(InstitutionDvsConfig $config, ?string $api = null): string
    {
        $api = $api ?: $config->complaint_api ?: InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI;

        return in_array($api, [
            InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
            InstitutionDvsConfig::COMPLAINT_API_PILIECIAI_LAISKAI,
        ], true)
            ? $api
            : InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI;
    }

    private function postJsonWithToken(InstitutionDvsConfig $config, string $url, array $payload): array
    {
        $token = $this->getToken($config);
        $response = Http::withToken($token)
            ->withHeaders(['Accept' => self::DVS_ACCEPT_HEADER])
            ->timeout(90)
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('DVS API grąžino HTTP '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('DVS API grąžino netinkamą JSON atsakymą.');
        }

        return ['json' => $json, 'http_status' => $response->status()];
    }

    private function getJsonWithToken(InstitutionDvsConfig $config, string $url): array
    {
        $token = $this->getToken($config);
        $response = Http::withToken($token)
            ->withHeaders(['Accept' => self::DVS_ACCEPT_HEADER])
            ->timeout(90)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('DVS statuso API grąžino HTTP '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('DVS statuso API grąžino netinkamą JSON atsakymą.');
        }

        return ['json' => $json, 'http_status' => $response->status()];
    }

    private function getToken(InstitutionDvsConfig $config): string
    {
        foreach ([
            'token_url' => 'Token URL',
            'username' => 'User',
            'password' => 'Password',
            'client_id' => 'Client ID',
            'client_secret' => 'Client secret',
        ] as $field => $label) {
            if (blank($config->{$field})) {
                throw new RuntimeException('DVS prisijungimui trūksta lauko: '.$label.'.');
            }
        }

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

    private function responseData(array $response): array
    {
        $status = $response['status'] ?? $response['Status'] ?? null;
        $data = $response['data'] ?? $response['Data'] ?? null;

        if ($status !== null && ! in_array((string) $status, ['0', '1'], true)) {
            throw new RuntimeException('DVS API grąžino nesėkmingą statusą.');
        }

        return is_array($data) ? $data : $response;
    }

    private function firstFilled(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stripEmpty(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function auditDvs(
        string $action,
        string $direction,
        string $trigger,
        string $service,
        AuditLogResult $result,
        ?string $message,
        array $metadata,
        array $context,
        ?Problem $problem,
        int|string|null $transferId,
    ): void {
        $this->integrationAuditService->log(
            $action,
            'DVS',
            $direction,
            $trigger,
            $service,
            $result,
            $message,
            $metadata,
            $this->auditActor($context),
            $problem,
            $transferId,
        );
    }

    private function auditActor(array $context): ?User
    {
        return ($context['actor'] ?? null) instanceof User ? $context['actor'] : null;
    }

    private function auditTrigger(array $context): string
    {
        return (string) ($context['trigger'] ?? 'command');
    }

    private function dvsServiceName(?string $api): string
    {
        return $api === InstitutionDvsConfig::COMPLAINT_API_PILIECIAI_LAISKAI
            ? 'PilieciuLaiskai'
            : 'GaunamiDokumentai';
    }

    private function problemFiles(Problem $problem, string $direction): array
    {
        return $problem->attachments
            ->values()
            ->map(fn (ProblemAttachment $attachment) => [
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'direction' => $direction,
                'content' => '[pašalinta]',
            ])
            ->all();
    }

    private function errorPayload(\Throwable $e): array
    {
        return [
            'type' => $e::class,
            'message' => $e->getMessage(),
        ];
    }
}
