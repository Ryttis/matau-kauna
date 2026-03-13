<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstitutionRequest;
use App\Http\Requests\Admin\UpdateInstitutionRequest;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

class InstitutionController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return Institution::query()
            ->notArchived()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);
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
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_email_only'] = $data['is_email_only'] ?? false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        return Institution::create($data);
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution): Institution
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $institution->update($data);

        return $institution->fresh();
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
