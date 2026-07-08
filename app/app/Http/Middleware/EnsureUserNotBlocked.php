<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserBlockingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isBlocked()) {
            return $next($request);
        }

        $user->currentAccessToken()?->delete();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => UserBlockingService::BLOCKED_MESSAGE,
                'code' => 'account_blocked',
            ], 403);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->withErrors([
            'email' => UserBlockingService::BLOCKED_MESSAGE,
        ]);
    }
}
