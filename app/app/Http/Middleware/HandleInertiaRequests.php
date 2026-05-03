<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActiveRoleService;
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
        $model = $user instanceof User ? $user : null;
        $authContext = app(ActiveRoleService::class)->resolveAndBind($request, $model);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $authContext['roles'],
                'permissions' => $authContext['permissions'],
                'activeRoleId' => $authContext['activeRoleId'],
                'activeRole' => $authContext['activeRole'],
                'isSystemAdmin' => $authContext['isSystemAdmin'],
            ],

            'flash' => [
                'status' => fn () => $request->session()->pull('status'),
                'success' => fn () => $request->session()->pull('success'),
                'error' => fn () => $request->session()->pull('error'),
            ],
        ];
    }
}
