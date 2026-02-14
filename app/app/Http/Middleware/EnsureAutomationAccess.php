<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAutomationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) || $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN)) {
            return $next($request);
        }

        abort(403);
    }
}
