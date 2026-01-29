<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole(\App\Models\UserRole::ROLE_ADMIN)) {
            abort(403);
        }

        return $next($request);
    }
}
