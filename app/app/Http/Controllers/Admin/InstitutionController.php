<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstitutionRequest;
use App\Http\Requests\Admin\UpdateInstitutionRequest;
use App\Models\Institution;
use App\Services\InstitutionDvsConfigService;
use App\Services\InstitutionGisConfigService;
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
            return response()->json($this->gisConfigService->metadataPayload($institution->gisConfig));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dvsRusys(Institution $institution): JsonResponse
    {
        try {
            return response()->json($this->dvsConfigService->fetchRusysForInstitution($institution));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Nepavyko gauti DVS rūšių.'], 422);
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
