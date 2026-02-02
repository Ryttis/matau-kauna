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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $user ? $user->roles()->get()->map(fn ($r) => ['role' => $r->role, 'name' => $r->name])->toArray() : [],
                'permissions' => $user ? $user->permissions() : [],
                'isSystemAdmin' => $user && $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN),
            ],
        ];
    }
}
