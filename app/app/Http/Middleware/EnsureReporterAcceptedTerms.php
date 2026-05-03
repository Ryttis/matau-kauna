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

        if (! $user->hasAssignedRole(UserRole::ROLE_REPORTER)) {
            return $next($request);
        }

        if ($request->routeIs('reporter.terms', 'reporter.terms.store')) {
            return $next($request);
        }

        if ($user->terms_accepted_at !== null) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'Reikia patvirtinti naudojimo taisykles.',
                'code' => 'reporter_terms_required',
                'terms_web_url' => url('/reporter/terms'),
            ], 403);
        }

        return redirect()->route('reporter.terms');
    }
}
