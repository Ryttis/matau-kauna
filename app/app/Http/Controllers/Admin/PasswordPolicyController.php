<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordPolicyRequest;
use App\Models\PasswordPolicy;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class PasswordPolicyController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function show(): JsonResponse
    {
        $policy = PasswordPolicy::get();

        return response()->json([
            'min_length' => $policy->min_length,
            'require_lowercase' => $policy->require_lowercase,
            'require_uppercase' => $policy->require_uppercase,
            'require_special' => $policy->require_special,
            'two_factor_can_enable' => $policy->two_factor_can_enable,
            'prohibit_name_email' => $policy->prohibit_name_email,
        ]);
    }

    public function update(UpdatePasswordPolicyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $row = PasswordPolicy::query()->first();
        if ($row) {
            $row->update($validated);
        } else {
            $row = PasswordPolicy::create($validated);
        }
        PasswordPolicy::clearCache();
        $this->auditLogService->logUserAction($request->user(), 'password_policy.update', 'PasswordPolicy', $row->id, 'success');

        return response()->json(['ok' => true]);
    }
}
