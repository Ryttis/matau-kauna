<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\InstitutionGisConfig;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemGisTransfer;
use App\Models\ProblemGisTransferAttachment;
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
    ) {}

    public function transferProblem(Problem $problem): array
    {
        $problem->loadMissing([
            'institution.gisConfig',
            'attachments',
            'reportedByUser:id,name,surname,email',
            'gisTransfer.attachmentTransfers',
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

        try {
            $featureCreated = false;

            if (! filled($transfer->external_object_id)) {
                [$externalObjectId, $externalGlobalId] = $this->createFeature($config, $problem);

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

            return [
                'transfer' => $transfer->fresh(),
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

            throw $e;
        }
    }

    public function syncFromGis(ProblemGisTransfer $transfer): array
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

        $attributes = $this->queryFeatureAttributes($config, $transfer);

        $statusChange = ['changed' => false, 'old' => null, 'new' => null];
        $deadlineChanged = false;

        if ($attributes !== []) {
            $statusChange = $this->applyStatusFromGis($transfer->problem, $attributes);
            $deadlineChanged = $this->applyDeadlineFromGis($transfer->problem, $attributes);
        }

        $attachmentResult = $this->pullAttachments($transfer);

        return [
            'attachments_downloaded' => $attachmentResult['downloaded'],
            'attachments_skipped' => $attachmentResult['skipped'],
            'status_changed' => $statusChange['changed'],
            'deadline_changed' => $deadlineChanged,
            'old_status' => $statusChange['old'],
            'new_status' => $statusChange['new'],
        ];
    }

    public function pullAttachments(ProblemGisTransfer $transfer): array
    {
        $transfer->loadMissing(['config', 'problem', 'attachmentTransfers']);

        $config = $transfer->config;
        if (! $config || ! $config->enabled) {
            return ['downloaded' => 0, 'skipped' => 0];
        }
        if (! filled($transfer->external_object_id)) {
            return ['downloaded' => 0, 'skipped' => 0];
        }

        $listing = $this->queryAttachmentMetadata($config, $transfer);
        if ($listing === []) {
            return ['downloaded' => 0, 'skipped' => 0];
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

        return ['downloaded' => $downloaded, 'skipped' => $skipped];
    }

    private function createFeature(InstitutionGisConfig $config, Problem $problem): array
    {
        [, $json] = $this->postWithTokenRetry(
            $config,
            $this->applyEditsUrl($config->feature_server_url),
            [
                'f' => 'json',
                'inSR' => 4326,
                'adds' => json_encode([$this->buildFeaturePayload($problem)], JSON_UNESCAPED_UNICODE),
            ]
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

        return [(string) $addResult['objectId'], filled($globalId) ? trim((string) $globalId, '{}') : null];
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

    private function queryFeatureAttributes(InstitutionGisConfig $config, ProblemGisTransfer $transfer): array
    {
        if (! filled($transfer->external_object_id)) {
            return [];
        }

        try {
            [, $json] = $this->postWithTokenRetry(
                $config,
                $this->queryUrl($config),
                [
                    'f' => 'json',
                    'objectIds' => $transfer->external_object_id,
                    'outFields' => 'busena,vykdymo_terminas,pranesimo_tipas,adresas,pranese',
                    'returnGeometry' => 'false',
                ]
            );
        } catch (\Throwable $e) {
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

    /**
     * @return array{changed: bool, old: ?string, new: ?string}
     */
    private function applyStatusFromGis(?Problem $problem, array $attributes): array
    {
        if (! $problem) {
            return ['changed' => false, 'old' => null, 'new' => null];
        }

        $rawStatus = (string) ($attributes['busena'] ?? '');
        $newStatus = $this->resolveStatusFromLabel($rawStatus);
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

        return ['changed' => true, 'old' => $oldStatus, 'new' => $newStatus->value];
    }

    private function applyDeadlineFromGis(?Problem $problem, array $attributes): bool
    {
        if (! $problem || ! array_key_exists('vykdymo_terminas', $attributes)) {
            return false;
        }

        $value = $attributes['vykdymo_terminas'];
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
            'atlikta' => ProblemStatus::RESOLVED,
            'uzbaigta' => ProblemStatus::RESOLVED,
            'atmesta' => ProblemStatus::INACTIVE,
        ];

        return $aliases[$normalized] ?? null;
    }

    private function normalizeStatusLabel(string $value): string
    {
        $ascii = Str::ascii($value);

        return Str::lower(trim((string) $ascii));
    }

    private function queryUrl(InstitutionGisConfig $config): string
    {
        return $this->layerBaseUrl($config).'/query';
    }

    private function queryAttachmentMetadata(InstitutionGisConfig $config, ProblemGisTransfer $transfer): array
    {
        $payload = [
            'f' => 'json',
            'objectIds' => $transfer->external_object_id,
        ];

        [, $json] = $this->postWithTokenRetry(
            $config,
            $this->queryAttachmentsUrl($config),
            $payload
        );

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

    private function buildFeaturePayload(Problem $problem): array
    {
        $reporterName = 'Gyventojas';
        if ($problem->reportedByUser) {
            $reporterName = trim(implode(' ', array_filter([
                $problem->reportedByUser->name,
                $problem->reportedByUser->surname,
            ]))) ?: $problem->reportedByUser->email ?: $reporterName;
        }

        $status = ProblemStatus::tryFrom((string) $problem->status);

        return [
            'attributes' => [
                'busena' => $status?->label() ?? (string) $problem->status,
                'pranesimo_tipas' => $problem->category ?? 'Problema',
                'registravimo_data' => $problem->created_at?->getTimestampMs() ?? now()->getTimestampMs(),
                'vykdymo_terminas' => $problem->completion_deadline?->startOfDay()->getTimestampMs(),
                'adresas' => $problem->address,
                'pranese' => $reporterName,
            ],
            'geometry' => [
                'x' => (float) $problem->lng,
                'y' => (float) $problem->lat,
                'spatialReference' => [
                    'wkid' => 4326,
                ],
            ],
        ];
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

    private function layerBaseUrl(InstitutionGisConfig $config): string
    {
        $baseUrl = rtrim((string) $config->feature_server_url, '/');
        $baseUrl = preg_replace('~/(applyEdits|queryAttachments)$~i', '', $baseUrl) ?: $baseUrl;

        return rtrim($baseUrl, '/');
    }
}
