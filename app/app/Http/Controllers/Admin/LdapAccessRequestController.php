<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LdapAccessRequest;
use App\Services\Ldap\LdapAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LdapAccessRequestController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);

        return LdapAccessRequest::query()
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('last_seen_at')->paginate(50);
    }

    public function approve(Request $request, LdapAccessRequest $ldapRequest, LdapAccessService $service)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('archived_at')],
            'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['exclude_with:user_id', 'required', 'email', 'max:255', 'unique:users,email'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'uuid', 'distinct', 'exists:user_role,id'],
        ]);

        return $service->approve($ldapRequest, $data, $request->user());
    }

    public function reject(Request $request, LdapAccessRequest $ldapRequest, LdapAccessService $service)
    {
        $service->reject($ldapRequest, $request->user());

        return response()->noContent();
    }
}
