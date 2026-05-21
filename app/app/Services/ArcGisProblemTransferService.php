<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Enums\ProblemStatus;
use App\Models\InstitutionGisConfig;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemGisTransfer;
use App\Models\ProblemGisTransferAttachment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ArcGisProblemTransferService
{
    public function __construct(
        private InstitutionGisConfigService $gisConfigService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ProblemAutoPublishService $autoPublishService,
        private IntegrationAuditService $integrationAuditService,
    ) {}

    public function transferProblem(Problem $problem, array $auditContext = []): array
    {
        $problem->loadMissing([
            'institution.gisConfig',
            'attachments',
            'reportedByUser:id,name,surname,email',
            'gisTransfer.attachmentTransfers',
            'problemType:id,name,parent_id',
            'problemNature:id,name,parent_id',
        ]);

        if (! $problem->institution_id || ! $problem->institution?->gisConfig?->enabled) {
            throw ValidationException::withMessages([
                'gis' => 'Šiai problemai nepriskirta aktyvi GIS integracija.',
            ]);
        }

        if ($problem->lat === null || $problem->lng === null) {
            throw ValidationException::withMessages([
                'gis' => 'Problema neturi koordinačių ir negali būti perduota į GIS.',
            ]);
        }

        $config = $problem->institution->gisConfig;

        $transfer = ProblemGisTransfer::firstOrCreate(
            ['problem_id' => $problem->id],
            [
                'institution_gis_config_id' => $config->id,
                'send_status' => ProblemGisTransfer::STATUS_PENDING,
            ]
        );

        $transfer->institution_gis_config_id = $config->id;
        $transfer->save();

        $featureAudit = null;

        try {
            $featureCreated = false;

            if (! filled($transfer->external_object_id)) {
                [$externalObjectId, $externalGlobalId, $featureAudit] = $this->createFeature($config, $problem);

                $transfer->forceFill([
                    'external_object_id' => (string) $externalObjectId,
                    'external_global_id' => filled($externalGlobalId) ? (string) $externalGlobalId : null,
                    'send_status' => ProblemGisTransfer::STATUS_FEATURE_CREATED,
                    'last_error' => null,
                    'sent_at' => now(),
                ])->save();

                $featureCreated = true;
            }

            $attachmentSummary = $this->syncAttachments($config, $transfer->fresh(), $problem);

            $transfer->refresh();
            if ($attachmentSummary['failed'] > 0) {
                $transfer->forceFill([
                    'send_status' => ProblemGisTransfer::STATUS_ATTACHMENT_FAILED,
                    'last_error' => $attachmentSummary['message'],
                ])->save();
            } else {
                $transfer->forceFill([
                    'send_status' => ProblemGisTransfer::STATUS_SENT,
                    'last_error' => null,
                ])->save();
            }

            $freshTransfer = $transfer->fresh();
            $this->auditGis(
                'integration.gis.send',
                'outbound',
                $this->auditTrigger($auditContext),
                'ArcGIS applyEdits/addAttachment',
                $attachmentSummary['failed'] > 0 ? AuditLogResult::Error : AuditLogResult::Success,
                $attachmentSummary['message'],
                [
                    'request' => [
                        'operations' => array_filter([
                            $featureAudit['request'] ?? null,
                            [
                                'method' => $problem->attachments->isEmpty() ? null : 'POST',
                                'url' => filled($freshTransfer->external_object_id) ? $this->attachmentEndpoint($config, $freshTransfer, 'addAttachment') : null,
                                'params' => ['f' => 'json'],
                            ],
                        ]),
                    ],
                    'response' => [
                        'feature_created' => $featureCreated,
                        'http_status' => $featureAudit['response']['http_status'] ?? null,
                        'external_object_id' => $freshTransfer->external_object_id,
                        'external_global_id' => $freshTransfer->external_global_id,
                        'send_status' => $freshTransfer->send_status,
                        'attachments_uploaded' => $attachmentSummary['uploaded'],
                        'attachments_failed' => $attachmentSummary['failed'],
                    ],
                    'files' => $this->problemFiles($problem, 'outbound'),
                ],
                $auditContext,
                $problem,
                $freshTransfer->id,
            );

            return [
                'transfer' => $freshTransfer,
                'feature_created' => $featureCreated,
                'attachments_uploaded' => $attachmentSummary['uploaded'],
                'attachments_failed' => $attachmentSummary['failed'],
                'message' => $attachmentSummary['message'],
            ];
        } catch (\Throwable $e) {
            $transfer->forceFill([
                'send_status' => filled($transfer->external_object_id)
                    ? ProblemGisTransfer::STATUS_ATTACHMENT_FAILED
                    : ProblemGisTransfer::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            $this->auditGis(
                'integration.gis.send',
                'outbound',
                $this->auditTrigger($auditContext),
                'ArcGIS applyEdits/addAttachment',
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => ['operations' => array_filter([$featureAudit['request'] ?? null])],
                    'response' => [
                        'http_status' => $featureAudit['response']['http_status'] ?? null,
                        'send_status' => $transfer->fresh()->send_status,
                    ],
                    'files' => $this->problemFiles($problem, 'outbound'),
                    'error' => $this->errorPayload($e),
                ],
                $auditContext,
                $problem,
                $transfer->id,
            );

            throw $e;
        }
    }

    public function syncFromGis(ProblemGisTransfer $transfer, array $auditContext = []): array
    {
        $transfer->loadMissing(['config', 'problem']);

        $config = $transfer->config;
        if (! $config || ! $config->enabled) {
            return [
                'attachments_downloaded' => 0,
                'attachments_skipped' => 0,
                'status_changed' => false,
                'deadline_changed' => false,
                'old_status' => null,
                'new_status' => null,
            ];
        }

        $queryAudit = null;
        $result = null;

        try {
            $attributes = $this->queryFeatureAttributes($config, $transfer, $queryAudit);

            $statusChange = ['changed' => false, 'old' => null, 'new' => null];
            $deadlineChanged = false;

            if ($attributes !== []) {
                $mapping = $this->gisConfigService->effectiveFieldMapping($config);
                $statusChange = $this->applyStatusFromGis($transfer->problem, $attributes, $mapping);
                $deadlineChanged = $this->applyDeadlineFromGis($transfer->problem, $attributes, $mapping['completion_deadline']);
            }

            $attachmentResult = $this->pullAttachments($transfer, $auditContext);

            $result = [
                'attachments_downloaded' => $attachmentResult['downloaded'],
                'attachments_skipped' => $attachmentResult['skipped'],
                'status_changed' => $statusChange['changed'],
                'deadline_changed' => $deadlineChanged,
                'old_status' => $statusChange['old'],
                'new_status' => $statusChange['new'],
            ];

            $this->auditGis(
                'integration.gis.pull_status',
                'inbound',
                $this->auditTrigger($auditContext),
                'ArcGIS query',
                ($queryAudit['error'] ?? null) ? AuditLogResult::Error : AuditLogResult::Success,
                $queryAudit['error']['message'] ?? null,
                [
                    'request' => $queryAudit['request'] ?? null,
                    'response' => array_merge($result, [
                        'http_status' => $queryAudit['response']['http_status'] ?? null,
                        'attributes_received' => $attributes !== [],
                    ]),
                    'error' => $queryAudit['error'] ?? null,
                ],
                $auditContext,
                $transfer->problem,
                $transfer->id,
            );

            return $result;
        } catch (\Throwable $e) {
            $this->auditGis(
                'integration.gis.pull_status',
                'inbound',
                $this->auditTrigger($auditContext),
                'ArcGIS query',
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => $queryAudit['request'] ?? null,
                    'response' => ['http_status' => $queryAudit['response']['http_status'] ?? null],
                    'error' => $this->errorPayload($e),
                ],
                $auditContext,
                $transfer->problem,
                $transfer->id,
            );

            throw $e;
        }
    }

    public function pullAttachments(ProblemGisTransfer $transfer, array $auditContext = []): array
    {
        $transfer->loadMissing(['config', 'problem', 'attachmentTransfers']);

        $config = $transfer->config;
        if (! $config || ! $config->enabled) {
            return ['downloaded' => 0, 'skipped' => 0];
        }
        if (! filled($transfer->external_object_id)) {
            return ['downloaded' => 0, 'skipped' => 0];
        }

        $listingAudit = null;
        try {
            $listing = $this->queryAttachmentMetadata($config, $transfer, $listingAudit);
        } catch (\Throwable $e) {
            $listingAudit['error'] = $this->errorPayload($e);
            $this->auditPullAttachments($transfer, $auditContext, ['downloaded' => 0, 'skipped' => 0], $listingAudit, $e->getMessage());

            throw $e;
        }
        if ($listing === []) {
            $result = ['downloaded' => 0, 'skipped' => 0];
            $this->auditPullAttachments($transfer, $auditContext, $result, $listingAudit);

            return $result;
        }

        $knownExternalIds = $transfer->attachmentTransfers
            ->pluck('external_attachment_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $downloaded = 0;
        $skipped = 0;

        foreach ($listing as $item) {
            $externalId = isset($item['id']) ? (string) $item['id'] : '';
            if ($externalId === '' || in_array($externalId, $knownExternalIds, true)) {
                $skipped++;

                continue;
            }

            try {
                $this->downloadAttachment($config, $transfer, $item);
                $downloaded++;
            } catch (\Throwable $e) {
                Log::warning('ArcGIS pullAttachments failed', [
                    'transfer_id' => $transfer->id,
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $result = ['downloaded' => $downloaded, 'skipped' => $skipped];
        $this->auditPullAttachments($transfer, $auditContext, $result, $listingAudit);

        return $result;
    }

    private function createFeature(InstitutionGisConfig $config, Problem $problem): array
    {
        $featurePayload = $this->buildFeaturePayload($config, $problem);
        $url = $this->applyEditsUrl($config->feature_server_url);
        $payload = [
            'f' => 'json',
            'inSR' => 4326,
            'adds' => json_encode([$featurePayload], JSON_UNESCAPED_UNICODE),
        ];

        [$response, $json] = $this->postWithTokenRetry(
            $config,
            $url,
            $payload
        );

        $addResult = Arr::first($json['addResults'] ?? []);
        if (! is_array($addResult) || ! ($addResult['success'] ?? false) || ! isset($addResult['objectId'])) {
            throw new RuntimeException('ArcGIS applyEdits negrąžino sėkmingo objekto ID.');
        }

        $globalId = $addResult['globalId'] ?? null;
        if (! filled($globalId)) {
            Log::info('ArcGIS applyEdits returned no globalId, falling back to OBJECTID only', [
                'object_id' => $addResult['objectId'],
            ]);
        }

        return [
            (string) $addResult['objectId'],
            filled($globalId) ? trim((string) $globalId, '{}') : null,
            [
                'request' => [
                    'method' => 'POST',
                    'url' => $url,
                    'params' => array_merge($payload, ['adds' => [$featurePayload]]),
                ],
                'response' => [
                    'http_status' => $response->status(),
                    'object_id' => $addResult['objectId'] ?? null,
                    'global_id' => $globalId,
                ],
            ],
        ];
    }

    private function syncAttachments(InstitutionGisConfig $config, ProblemGisTransfer $transfer, Problem $problem): array
    {
        $uploaded = 0;
        $failed = 0;
        $messages = [];

        foreach ($problem->attachments as $attachment) {
            if ($attachment->source === 'gis') {
                continue;
            }

            $attachmentTransfer = ProblemGisTransferAttachment::firstOrCreate(
                [
                    'problem_gis_transfer_id' => $transfer->id,
                    'problem_attachment_id' => $attachment->id,
                ],
                [
                    'status' => ProblemGisTransferAttachment::STATUS_PENDING,
                    'direction' => ProblemGisTransferAttachment::DIRECTION_OUTBOUND,
                ]
            );

            if ($attachmentTransfer->status === ProblemGisTransferAttachment::STATUS_SENT) {
                continue;
            }

            try {
                [$externalAttachmentId, $externalGlobalId] = $this->uploadAttachment($config, $transfer, $attachment);

                $attachmentTransfer->forceFill([
                    'external_attachment_id' => $externalAttachmentId,
                    'external_global_id' => $externalGlobalId,
                    'status' => ProblemGisTransferAttachment::STATUS_SENT,
                    'direction' => ProblemGisTransferAttachment::DIRECTION_OUTBOUND,
                    'last_error' => null,
                    'sent_at' => now(),
                ])->save();

                $uploaded++;
            } catch (\Throwable $e) {
                $attachmentTransfer->forceFill([
                    'status' => ProblemGisTransferAttachment::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                ])->save();

                $failed++;
                $messages[] = ($attachment->original_name ?: 'Priedas').': '.$e->getMessage();
            }
        }

        return [
            'uploaded' => $uploaded,
            'failed' => $failed,
            'message' => $failed > 0 ? implode(' | ', $messages) : null,
        ];
    }

    private function uploadAttachment(InstitutionGisConfig $config, ProblemGisTransfer $transfer, ProblemAttachment $attachment): array
    {
        $absolutePath = public_path(ltrim($attachment->path, '/'));
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Nepavyko rasti priedo failo serveryje.');
        }

        [, $json] = $this->postWithTokenRetry(
            $config,
            $this->attachmentEndpoint($config, $transfer, 'addAttachment'),
            ['f' => 'json'],
            function (string $token, string $url, array $payload) use ($absolutePath, $attachment): Response {
                return Http::timeout(30)
                    ->attach(
                        'attachment',
                        file_get_contents($absolutePath),
                        $attachment->original_name ?: basename($absolutePath)
                    )
                    ->post($url, array_merge($payload, ['token' => $token]));
            }
        );

        $result = $json['addAttachmentResult'] ?? null;
        if (! is_array($result) || ! ($result['success'] ?? false)) {
            $detail = is_array($result) && isset($result['error'])
                ? (is_array($result['error']) ? json_encode($result['error'], JSON_UNESCAPED_UNICODE) : (string) $result['error'])
                : json_encode($json, JSON_UNESCAPED_UNICODE);

            throw new RuntimeException('ArcGIS addAttachment klaida: '.Str::limit((string) $detail, 500));
        }

        $externalId = isset($result['objectId']) ? (string) $result['objectId'] : '';
        $globalId = isset($result['globalId']) ? trim((string) $result['globalId'], '{}') : null;

        return [$externalId, filled($globalId) ? $globalId : null];
    }

    private function queryFeatureAttributes(InstitutionGisConfig $config, ProblemGisTransfer $transfer, ?array &$audit = null): array
    {
        if (! filled($transfer->external_object_id)) {
            return [];
        }

        $url = $this->queryUrl($config);
        $payload = [
            'f' => 'json',
            'objectIds' => $transfer->external_object_id,
            'outFields' => $this->queryOutFields($config),
            'returnGeometry' => 'false',
        ];
        $audit = [
            'request' => [
                'method' => 'POST',
                'url' => $url,
                'params' => $payload,
            ],
        ];

        try {
            [$response, $json] = $this->postWithTokenRetry(
                $config,
                $url,
                $payload
            );
            $audit['response'] = ['http_status' => $response->status()];
        } catch (\Throwable $e) {
            $audit['error'] = $this->errorPayload($e);
            Log::warning('ArcGIS query attributes failed', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $first = Arr::first($json['features'] ?? []);

        return is_array($first) && isset($first['attributes']) && is_array($first['attributes'])
            ? $first['attributes']
            : [];
    }

    private function queryOutFields(InstitutionGisConfig $config): string
    {
        if (! $config->use_field_mapping) {
            return 'busena,vykdymo_terminas,pranesimo_tipas,adresas,pranese';
        }

        $mapping = $this->gisConfigService->effectiveFieldMapping($config);
        $fields = array_filter([
            $mapping['status'] ?? null,
            $mapping['completion_deadline'] ?? null,
            $mapping['category'] ?? null,
            $mapping['created_at'] ?? null,
            $mapping['address'] ?? null,
            $mapping['reporter'] ?? null,
            $mapping['internal_number'] ?? null,
            $mapping['description'] ?? null,
        ]);

        return implode(',', array_values(array_unique($fields))) ?: '*';
    }

    /**
     * @return array{changed: bool, old: ?string, new: ?string}
     */
    private function applyStatusFromGis(?Problem $problem, array $attributes, array $mapping): array
    {
        if (! $problem) {
            return ['changed' => false, 'old' => null, 'new' => null];
        }

        $statusField = (string) ($mapping['status'] ?? '');
        $rawStatus = (string) ($attributes[$statusField] ?? '');
        $newStatus = $this->resolveStatusFromMappedValue(
            $rawStatus,
            is_array($mapping[InstitutionGisConfigService::STATUS_VALUE_MAPPING_KEY] ?? null)
                ? $mapping[InstitutionGisConfigService::STATUS_VALUE_MAPPING_KEY]
                : []
        ) ?? $this->resolveStatusFromLabel($rawStatus);
        if ($newStatus === null) {
            return ['changed' => false, 'old' => null, 'new' => null];
        }

        $oldStatus = (string) $problem->status;
        if ($oldStatus === $newStatus->value) {
            return ['changed' => false, 'old' => $oldStatus, 'new' => $newStatus->value];
        }

        $problem->forceFill([
            'status' => $newStatus->value,
            'status_updated_at' => now(),
        ])->save();

        $main = $problem->merged_into_problem_id
            ? Problem::find($problem->merged_into_problem_id)
            : $problem;

        if ($main) {
            $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges(
                $main,
                $oldStatus,
                $newStatus->value
            );
        }

        $this->autoAssignmentService->applyRules($problem);
        $this->autoPublishService->applyRules($problem);

        return ['changed' => true, 'old' => $oldStatus, 'new' => $newStatus->value];
    }

    private function resolveStatusFromMappedValue(string $raw, array $valueMapping): ?ProblemStatus
    {
        $value = trim($raw);
        if ($value === '' || $valueMapping === []) {
            return null;
        }

        $normalized = $this->normalizeStatusLabel($value);
        foreach ($valueMapping as $localStatus => $gisValue) {
            $status = ProblemStatus::tryFrom((string) $localStatus);
            if (! $status) {
                continue;
            }

            $code = is_array($gisValue) ? (string) ($gisValue['code'] ?? '') : (string) $gisValue;
            $name = is_array($gisValue) ? (string) ($gisValue['name'] ?? '') : (string) $gisValue;
            foreach ([$code, $name] as $candidate) {
                if (trim($candidate) !== '' && $this->normalizeStatusLabel($candidate) === $normalized) {
                    return $status;
                }
            }
        }

        return null;
    }

    private function applyDeadlineFromGis(?Problem $problem, array $attributes, string $deadlineField): bool
    {
        if (! $problem || ! array_key_exists($deadlineField, $attributes)) {
            return false;
        }

        $value = $attributes[$deadlineField];
        if ($value === null || $value === '') {
            if ($problem->completion_deadline === null) {
                return false;
            }
            $problem->forceFill(['completion_deadline' => null])->save();

            return true;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $newDeadline = Carbon::createFromTimestampMs((int) $value)->startOfDay();
        $oldDeadline = $problem->completion_deadline?->copy()->startOfDay();

        if ($oldDeadline && $oldDeadline->equalTo($newDeadline)) {
            return false;
        }

        $problem->forceFill(['completion_deadline' => $newDeadline])->save();

        return true;
    }

    private function resolveStatusFromLabel(string $raw): ?ProblemStatus
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if ($status = ProblemStatus::tryFrom($value)) {
            return $status;
        }

        $normalized = $this->normalizeStatusLabel($value);
        foreach (ProblemStatus::cases() as $case) {
            if ($this->normalizeStatusLabel($case->label()) === $normalized) {
                return $case;
            }
        }

        // Common aliases used in ArcGIS layer ("Registruojama" → "Nauja").
        $aliases = [
            'registruojama' => ProblemStatus::NEW,
            'priimta' => ProblemStatus::NEW,
            'vykdoma' => ProblemStatus::IN_REVIEW,
            'atlikti darbai' => ProblemStatus::RESOLVED,
            'atliktas darbas' => ProblemStatus::RESOLVED,
            'atlikta' => ProblemStatus::RESOLVED,
            'ivykdyta' => ProblemStatus::RESOLVED,
            'uzdaryta' => ProblemStatus::RESOLVED,
            'baigta' => ProblemStatus::RESOLVED,
            'uzbaigta' => ProblemStatus::RESOLVED,
            'atmesta' => ProblemStatus::INACTIVE,
        ];

        return $aliases[$normalized] ?? null;
    }

    private function normalizeStatusLabel(string $value): string
    {
        $ascii = Str::ascii($value);

        return preg_replace('/\s+/', ' ', Str::lower(trim((string) $ascii))) ?? '';
    }

    private function queryUrl(InstitutionGisConfig $config): string
    {
        return $this->layerBaseUrl($config).'/query';
    }

    private function queryAttachmentMetadata(InstitutionGisConfig $config, ProblemGisTransfer $transfer, ?array &$audit = null): array
    {
        $payload = [
            'f' => 'json',
            'objectIds' => $transfer->external_object_id,
        ];
        $url = $this->queryAttachmentsUrl($config);

        $audit = [
            'request' => [
                'method' => 'POST',
                'url' => $url,
                'params' => $payload,
            ],
        ];

        [$response, $json] = $this->postWithTokenRetry(
            $config,
            $url,
            $payload
        );
        $audit['response'] = ['http_status' => $response->status()];

        $groups = $json['attachmentGroups'] ?? [];
        $items = [];
        foreach ($groups as $group) {
            foreach (($group['attachmentInfos'] ?? []) as $info) {
                $items[] = $info;
            }
        }

        return $items;
    }

    private function downloadAttachment(InstitutionGisConfig $config, ProblemGisTransfer $transfer, array $info): void
    {
        $externalId = (string) ($info['id'] ?? '');
        $name = (string) ($info['name'] ?? ('gis_'.$externalId));
        $mime = (string) ($info['contentType'] ?? 'application/octet-stream');

        $token = $this->gisConfigService->getValidToken($config);

        $downloadUrl = $this->attachmentDownloadUrl($config, $transfer, $externalId);
        $response = Http::timeout(60)->get($downloadUrl, ['token' => $token]);

        if ($response->status() === 498 || $response->status() === 499) {
            $token = $this->gisConfigService->getValidToken($config->fresh(), true);
            $response = Http::timeout(60)->get($downloadUrl, ['token' => $token]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Nepavyko atsisiųsti priedo iš ArcGIS (HTTP '.$response->status().').');
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('ArcGIS grąžino tuščią priedo turinį.');
        }

        $kind = Str::startsWith($mime, 'video/') ? 'video' : 'photo';
        $sub = $kind === 'photo' ? 'images' : 'videos';
        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/i', $ext) ? strtolower($ext) : 'bin';

        $problemId = $transfer->problem_id;
        $relativeDir = 'files/problems/'.$problemId.'/'.$sub;
        $absoluteDir = public_path($relativeDir);
        File::ensureDirectoryExists($absoluteDir);

        $storedName = Str::uuid()->toString().'.'.$safeExt;
        $absolutePath = $absoluteDir.'/'.$storedName;
        File::put($absolutePath, $body);

        $attachment = ProblemAttachment::create([
            'problem_id' => $problemId,
            'path' => $relativeDir.'/'.$storedName,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => strlen($body),
            'kind' => $kind,
            'source' => 'gis',
        ]);

        $globalId = isset($info['globalId']) ? trim((string) $info['globalId'], '{}') : null;

        ProblemGisTransferAttachment::create([
            'problem_gis_transfer_id' => $transfer->id,
            'problem_attachment_id' => $attachment->id,
            'external_attachment_id' => $externalId,
            'external_global_id' => filled($globalId) ? $globalId : null,
            'status' => ProblemGisTransferAttachment::STATUS_SENT,
            'direction' => ProblemGisTransferAttachment::DIRECTION_INBOUND,
            'sent_at' => now(),
        ]);
    }

    private function buildFeaturePayload(InstitutionGisConfig $config, Problem $problem): array
    {
        $reporterName = 'Gyventojas';
        if ($problem->reportedByUser) {
            $reporterName = trim(implode(' ', array_filter([
                $problem->reportedByUser->name,
                $problem->reportedByUser->surname,
            ]))) ?: $problem->reportedByUser->email ?: $reporterName;
        }

        $values = [
            'status' => $this->resolveOutgoingStatusValue($config, $problem),
            'category' => $this->resolveCategoryValue($config, $problem),
            'created_at' => $problem->created_at?->getTimestampMs() ?? now()->getTimestampMs(),
            'completion_deadline' => $problem->completion_deadline?->startOfDay()->getTimestampMs(),
            'address' => $problem->address,
            'reporter' => $this->resolveReporterValue($config, $reporterName),
            'internal_number' => $problem->internal_number,
            'description' => $problem->description,
            'problem_url' => route('problems.show', $problem->id),
        ];
        $mapping = $this->gisConfigService->effectiveFieldMapping($config);
        $attributes = [];
        foreach (InstitutionGisConfig::FIELD_MAPPING_SOURCES as $source) {
            $fieldName = $mapping[$source] ?? '';
            if (! is_string($fieldName) || $fieldName === '') {
                continue;
            }
            $attributes[$fieldName] = $values[$source] ?? null;
        }

        $additionalFields = $mapping[InstitutionGisConfigService::ADDITIONAL_FIELDS_KEY] ?? [];
        if (is_array($additionalFields)) {
            foreach ($additionalFields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldName = trim((string) ($field['field'] ?? ''));
                $value = trim((string) ($field['value'] ?? ''));
                if ($fieldName === '' || $value === '' || array_key_exists($fieldName, $attributes)) {
                    continue;
                }

                $attributes[$fieldName] = $value;
            }
        }

        return [
            'attributes' => $attributes,
            'geometry' => [
                'x' => (float) $problem->lng,
                'y' => (float) $problem->lat,
                'spatialReference' => [
                    'wkid' => 4326,
                ],
            ],
        ];
    }

    private function resolveOutgoingStatusValue(InstitutionGisConfig $config, Problem $problem): string
    {
        $status = ProblemStatus::tryFrom((string) $problem->status);
        $fallback = $status?->label() ?? (string) $problem->status;
        if (! $config->use_field_mapping) {
            return $fallback;
        }

        $mapping = $this->gisConfigService->effectiveFieldMapping($config);
        $valueMapping = $mapping[InstitutionGisConfigService::STATUS_VALUE_MAPPING_KEY] ?? [];
        if (! is_array($valueMapping)) {
            return $fallback;
        }

        $mappedValue = $valueMapping[(string) $problem->status] ?? null;
        if (is_array($mappedValue) && filled($mappedValue['code'] ?? null)) {
            return (string) $mappedValue['code'];
        }
        if (is_string($mappedValue) && filled($mappedValue)) {
            return $mappedValue;
        }

        return $fallback;
    }

    private function resolveCategoryValue(InstitutionGisConfig $config, Problem $problem): string
    {
        $fallback = $problem->category ?? 'Problema';
        if (! $config->use_field_mapping) {
            return $fallback;
        }

        $mapping = $this->gisConfigService->effectiveFieldMapping($config);
        $valueMapping = $mapping[InstitutionGisConfigService::CATEGORY_VALUE_MAPPING_KEY] ?? [];
        if (! is_array($valueMapping) || $valueMapping === []) {
            return $fallback;
        }

        foreach ([$problem->problem_nature_id, $problem->problem_type_id] as $classifierValueId) {
            if (! $classifierValueId) {
                continue;
            }

            $mappedValue = $valueMapping[(string) $classifierValueId] ?? null;
            if (is_array($mappedValue) && filled($mappedValue['code'] ?? null)) {
                return (string) $mappedValue['code'];
            }
            if (is_string($mappedValue) && filled($mappedValue)) {
                return $mappedValue;
            }
        }

        return $fallback;
    }

    private function resolveReporterValue(InstitutionGisConfig $config, string $defaultReporterName): string
    {
        if (! $config->use_field_mapping) {
            return $defaultReporterName;
        }

        $mapping = $this->gisConfigService->effectiveFieldMapping($config);
        $mode = $mapping[InstitutionGisConfigService::REPORTER_VALUE_MODE_KEY] ?? 'full_name';

        return match ($mode) {
            'resident' => 'Gyventojas',
            'custom' => trim((string) ($mapping[InstitutionGisConfigService::REPORTER_DEFAULT_VALUE_KEY] ?? '')) ?: 'Gyventojas',
            default => $defaultReporterName,
        };
    }

    private function postWithTokenRetry(
        InstitutionGisConfig $config,
        string $url,
        array $payload,
        ?callable $requestFactory = null,
    ): array {
        $requestFactory ??= function (string $token, string $requestUrl, array $requestPayload): Response {
            return Http::asForm()
                ->timeout(30)
                ->post($requestUrl, array_merge($requestPayload, ['token' => $token]));
        };

        $token = $this->gisConfigService->getValidToken($config);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $requestFactory($token, $url, $payload);
            $json = $response->json();

            if (($json['error']['code'] ?? null) === 498 && $attempt === 0) {
                $token = $this->gisConfigService->getValidToken($config->fresh(), true);

                continue;
            }

            if (! is_array($json)) {
                throw new RuntimeException('ArcGIS grąžino netinkamą atsakymą.');
            }

            if (isset($json['error'])) {
                $detail = json_encode($json['error'], JSON_UNESCAPED_UNICODE);
                throw new RuntimeException('ArcGIS klaida: '.Str::limit($detail, 500));
            }

            return [$response, $json];
        }

        throw new RuntimeException('Nepavyko įvykdyti ArcGIS užklausos.');
    }

    private function applyEditsUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        return Str::endsWith(Str::lower($baseUrl), '/applyedits')
            ? $baseUrl
            : $baseUrl.'/applyEdits';
    }

    private function queryAttachmentsUrl(InstitutionGisConfig $config): string
    {
        return $this->layerBaseUrl($config).'/queryAttachments';
    }

    private function attachmentEndpoint(InstitutionGisConfig $config, ProblemGisTransfer $transfer, string $operation): string
    {
        $baseUrl = rtrim($config->attachment_url, '/');
        $segment = (string) $transfer->external_object_id;

        if (preg_match('~/[^/]+/'.$operation.'$~i', $baseUrl)) {
            return $baseUrl;
        }

        if (Str::endsWith(Str::lower($baseUrl), '/'.Str::lower($operation))) {
            return preg_replace('~/'.$operation.'$~i', '/'.$segment.'/'.$operation, $baseUrl) ?: $baseUrl;
        }

        return $baseUrl.'/'.$segment.'/'.$operation;
    }

    private function attachmentDownloadUrl(InstitutionGisConfig $config, ProblemGisTransfer $transfer, string $externalId): string
    {
        $layerUrl = $this->layerBaseUrl($config);
        $segment = (string) $transfer->external_object_id;

        return $layerUrl.'/'.$segment.'/attachments/'.$externalId;
    }

    private function auditPullAttachments(
        ProblemGisTransfer $transfer,
        array $context,
        array $result,
        ?array $listingAudit,
        ?string $message = null,
    ): void {
        $transfer->loadMissing('problem');
        $this->auditGis(
            'integration.gis.pull_attachments',
            'inbound',
            $this->auditTrigger($context),
            'ArcGIS queryAttachments',
            ($listingAudit['error'] ?? null) ? AuditLogResult::Error : AuditLogResult::Success,
            $message,
            [
                'request' => $listingAudit['request'] ?? null,
                'response' => array_merge($result, [
                    'http_status' => $listingAudit['response']['http_status'] ?? null,
                ]),
                'error' => $listingAudit['error'] ?? null,
            ],
            $context,
            $transfer->problem,
            $transfer->id,
        );
    }

    private function auditGis(
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
            'GIS',
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

    private function layerBaseUrl(InstitutionGisConfig $config): string
    {
        $baseUrl = rtrim((string) $config->feature_server_url, '/');
        $baseUrl = preg_replace('~/(applyEdits|queryAttachments)$~i', '', $baseUrl) ?: $baseUrl;

        return rtrim($baseUrl, '/');
    }
}
