<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstitutionRequest;
use App\Http\Requests\Admin\UpdateInstitutionRequest;
use App\Models\Institution;
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
    ) {}

    public function index(): LengthAwarePaginator
    {
        $paginator = Institution::query()
            ->notArchived()
            ->with('gisConfig')
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);

        $paginator->getCollection()->transform(function (Institution $institution) {
            $institution->setAttribute('gis', $this->gisConfigService->settingsPayload($institution->gisConfig));

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

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_email_only'] = $validated['is_email_only'] ?? false;
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $institution = Institution::create($validated);

        $this->gisConfigService->updateForInstitution($institution, $gisPayload);

        $institution->load('gisConfig');
        $institution->setAttribute('gis', $this->gisConfigService->settingsPayload($institution->gisConfig));

        return $institution;
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution): Institution
    {
        $validated = $request->validated();
        $gisPayload = Arr::pull($validated, 'gis', []);

        $validated['updated_by'] = $request->user()->id;
        $institution->update($validated);

        $this->gisConfigService->updateForInstitution($institution, $gisPayload);

        $fresh = $institution->fresh(['gisConfig']);
        $fresh->setAttribute('gis', $this->gisConfigService->settingsPayload($fresh->gisConfig));

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
