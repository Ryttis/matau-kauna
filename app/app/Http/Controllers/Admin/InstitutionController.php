<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    private function institutionValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'integration_email' => ['nullable', 'string', 'max:255'],
            'integration_dvs' => ['nullable', 'string', 'max:255'],
            'integration_gis' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_email_only' => ['boolean'],
        ];
    }

    public function store(Request $request): Institution
    {
        $data = $request->validate($this->institutionValidationRules());
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_email_only'] = $data['is_email_only'] ?? false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        return Institution::create($data);
    }

    public function update(Request $request, Institution $institution): Institution
    {
        $data = $request->validate($this->institutionValidationRules());
        $data['updated_by'] = $request->user()->id;
        $institution->update($data);

        return $institution->fresh();
    }

    public function destroy(Institution $institution): JsonResponse
    {
        try {
            $institution->update(['archived_at' => now()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(
                ['message' => 'Nepavyko pašalinti įstaigos. Patikrinkite, ar nėra susijusių duomenų.'],
                422
            );
        }
    }
}
