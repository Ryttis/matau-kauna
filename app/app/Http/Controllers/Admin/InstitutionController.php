<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        return Institution::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);
    }

    public function list()
    {
        return Institution::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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
        ]);

        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_email_only'] = $data['is_email_only'] ?? false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        return Institution::create($data);
    }

    public function update(Request $request, Institution $institution)
    {
        $data = $request->validate([
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
        ]);

        $data['updated_by'] = $request->user()->id;
        $institution->update($data);

        return $institution->fresh();
    }
}
