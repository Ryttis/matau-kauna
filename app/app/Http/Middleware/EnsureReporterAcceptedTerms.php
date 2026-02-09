<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReporterAcceptedTerms
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->hasRole(UserRole::ROLE_REPORTER)) {
            return $next($request);
        }

        if ($request->routeIs('reporter.terms', 'reporter.terms.store')) {
            return $next($request);
        }

        if ($user->terms_accepted_at !== null) {
            return $next($request);
        }

        return redirect()->route('reporter.terms');
    }
}
