<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $activeRoleId = $request->session()->get('active_role_id');
        $roles = $user ? $user->roles()->get()->map(fn ($r) => ['id' => $r->id, 'role' => $r->role, 'name' => $r->name])->toArray() : [];
        $permissions = [];
        $activeRole = null;

        if ($user && $activeRoleId) {
            $permissions = $user->permissionsForRole($activeRoleId);
            $activeRole = collect($roles)->firstWhere('id', $activeRoleId);
        }

        if ($user && ! $activeRoleId) {
            $permissions = $user->permissions();
            if (count($roles) === 1) {
                $activeRole = $roles[0];
                $request->session()->put('active_role_id', $roles[0]['id']);
            }
        }

        $isSystemAdmin = $activeRole && $activeRole['role'] === UserRole::ROLE_SYSTEM_ADMIN;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'permissions' => $permissions,
                'activeRoleId' => $activeRoleId,
                'activeRole' => $activeRole,
                'isSystemAdmin' => $isSystemAdmin,
            ],

            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
