<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordPolicyController extends Controller
{
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

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'require_lowercase' => ['boolean'],
            'require_uppercase' => ['boolean'],
            'require_special' => ['boolean'],
            'two_factor_can_enable' => ['boolean'],
            'prohibit_name_email' => ['boolean'],
        ]);

        $row = PasswordPolicy::query()->first();
        if ($row) {
            $row->update($validated);
        } else {
            PasswordPolicy::create($validated);
        }
        PasswordPolicy::clearCache();

        return response()->json(['ok' => true]);
    }
}
