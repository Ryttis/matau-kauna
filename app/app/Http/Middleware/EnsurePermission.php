<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
