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

        $userPayload = null;
        if ($model) {
            $model->loadMissing('acceptedTermsVersion:id,version,title,body,published_at');
            $userPayload = $model->toArray();
            $userPayload['accepted_terms_version'] = $model->acceptedTermsVersion ? [
                'id' => $model->acceptedTermsVersion->id,
                'version' => $model->acceptedTermsVersion->version,
                'title' => $model->acceptedTermsVersion->title,
                'body' => $model->acceptedTermsVersion->body,
                'published_at' => $model->acceptedTermsVersion->published_at?->toIso8601String(),
            ] : null;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userPayload ?? $user,
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
