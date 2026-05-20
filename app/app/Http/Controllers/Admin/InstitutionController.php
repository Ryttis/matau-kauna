<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstitutionRequest;
use App\Http\Requests\Admin\UpdateInstitutionRequest;
use App\Models\Institution;
use App\Services\InstitutionDvsConfigService;
use App\Services\InstitutionGisConfigService;
use App\Services\IntegrationAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class InstitutionController extends Controller
{
    public function __construct(
        private InstitutionGisConfigService $gisConfigService,
        private InstitutionDvsConfigService $dvsConfigService,
        private IntegrationAuditService $integrationAuditService,
    ) {}

    public function index(): LengthAwarePaginator
    {
        $paginator = Institution::query()
            ->notArchived()
            ->with(['gisConfig', 'dvsConfig'])
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);

        $paginator->getCollection()->transform(function (Institution $institution) {
            $institution->setAttribute('gis', $this->gisConfigService->settingsPayload($institution->gisConfig));
            $institution->setAttribute('dvs', $this->dvsConfigService->settingsPayload($institution->dvsConfig));

            return $institution;
        });

        return $paginator;
    }

    public function list(): Collection
    {
        return Institution::query()
            ->notArchived()
            ->orderBy('name')
            ->get(['id', 'name', 'is_email_only']);
    }

    public function store(StoreInstitutionRequest $request): Institution
    {
        $validated = $request->validated();
        $gisPayload = Arr::pull($validated, 'gis', []);
        $dvsPayload = Arr::pull($validated, 'dvs', []);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_email_only'] = $validated['is_email_only'] ?? false;
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $institution = Institution::create($validated);

        $this->gisConfigService->updateForInstitution($institution, $gisPayload);
        $this->dvsConfigService->updateForInstitution($institution, $dvsPayload);

        $institution->load(['gisConfig', 'dvsConfig']);
        $institution->setAttribute('gis', $this->gisConfigService->settingsPayload($institution->gisConfig));
        $institution->setAttribute('dvs', $this->dvsConfigService->settingsPayload($institution->dvsConfig));

        return $institution;
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution): Institution
    {
        $validated = $request->validated();
        $gisPayload = Arr::pull($validated, 'gis', []);
        $dvsPayload = Arr::pull($validated, 'dvs', []);

        $validated['updated_by'] = $request->user()->id;
        $institution->update($validated);

        $this->gisConfigService->updateForInstitution($institution, $gisPayload);
        $this->dvsConfigService->updateForInstitution($institution, $dvsPayload);

        $fresh = $institution->fresh(['gisConfig', 'dvsConfig']);
        $fresh->setAttribute('gis', $this->gisConfigService->settingsPayload($fresh->gisConfig));
        $fresh->setAttribute('dvs', $this->dvsConfigService->settingsPayload($fresh->dvsConfig));

        return $fresh;
    }

    public function gisMetadata(Institution $institution): JsonResponse
    {
        $institution->loadMissing('gisConfig');

        if (! $institution->gisConfig) {
            return response()->json([
                'message' => 'Įstaiga neturi GIS integracijos nustatymų.',
            ], 422);
        }

        try {
            $payload = $this->gisConfigService->metadataPayload($institution->gisConfig);
            $this->integrationAuditService->log(
                'integration.gis.fetch_metadata',
                'GIS',
                'inbound',
                'admin_config',
                'ArcGIS metadata',
                AuditLogResult::Success,
                null,
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $institution->gisConfig->metadata_url ?: $institution->gisConfig->feature_server_url,
                        'params' => [
                            'institution_id' => $institution->id,
                            'layer_id' => $payload['layer_id'] ?? null,
                        ],
                    ],
                    'response' => [
                        'fields_count' => count($payload['fields'] ?? []),
                        'domains_count' => count($payload['domains'] ?? []),
                    ],
                ],
                request()->user(),
            );

            return response()->json($payload);
        } catch (RuntimeException $e) {
            $this->integrationAuditService->log(
                'integration.gis.fetch_metadata',
                'GIS',
                'inbound',
                'admin_config',
                'ArcGIS metadata',
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $institution->gisConfig->metadata_url ?: $institution->gisConfig->feature_server_url,
                        'params' => ['institution_id' => $institution->id],
                    ],
                    'error' => ['type' => $e::class, 'message' => $e->getMessage()],
                ],
                request()->user(),
            );

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dvsRusys(Institution $institution): JsonResponse
    {
        try {
            $payload = $this->dvsConfigService->fetchRusysForInstitution($institution);
            $this->integrationAuditService->log(
                'integration.dvs.fetch_rusys',
                'DVS',
                'inbound',
                'admin_config',
                'DVS Rūšis',
                AuditLogResult::Success,
                null,
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $institution->dvsConfig?->rusis_url,
                        'params' => ['institution_id' => $institution->id],
                    ],
                    'response' => [
                        'options_count' => count($payload['rusys_options'] ?? []),
                    ],
                ],
                request()->user(),
            );

            return response()->json($payload);
        } catch (RuntimeException $e) {
            $this->integrationAuditService->log(
                'integration.dvs.fetch_rusys',
                'DVS',
                'inbound',
                'admin_config',
                'DVS Rūšis',
                AuditLogResult::Error,
                $e->getMessage(),
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $institution->dvsConfig?->rusis_url,
                        'params' => ['institution_id' => $institution->id],
                    ],
                    'error' => ['type' => $e::class, 'message' => $e->getMessage()],
                ],
                request()->user(),
            );

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $message = 'Nepavyko gauti DVS rūšių.';
            $this->integrationAuditService->log(
                'integration.dvs.fetch_rusys',
                'DVS',
                'inbound',
                'admin_config',
                'DVS Rūšis',
                AuditLogResult::Error,
                $message,
                [
                    'request' => [
                        'method' => 'GET',
                        'url' => $institution->dvsConfig?->rusis_url,
                        'params' => ['institution_id' => $institution->id],
                    ],
                    'error' => ['type' => $e::class, 'message' => $e->getMessage()],
                ],
                request()->user(),
            );

            return response()->json(['message' => $message], 422);
        }
    }

    public function destroy(Institution $institution): JsonResponse
    {
        try {
            $institution->update(['archived_at' => now()]);

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(
                ['message' => 'Nepavyko pašalinti įstaigos. Patikrinkite, ar nėra susijusių duomenų.'],
                422
            );
        }
    }
}
