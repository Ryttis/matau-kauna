<?php

namespace App\Http\Middleware;

use App\Support\TokenExpiration;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ExtendTokenExpiration
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $rememberSession = TokenExpiration::tokenHasRememberSessionAbility($token->abilities ?? []);
            $minutes = $rememberSession ? TokenExpiration::mobileDays() * 24 * 60 : TokenExpiration::minutes();
            $threshold = now()->addMinutes(max(1, $minutes - 5));

            if (! $token->expires_at || $token->expires_at->lt($threshold)) {
                $token->forceFill(['expires_at' => TokenExpiration::nextForRememberSession($rememberSession)])->save();
            }
        }

        $response = $next($request);

        if ($token instanceof PersonalAccessToken && $token->expires_at) {
            $response->headers->set('X-Session-Expires-At', $token->expires_at->toIso8601String());
        }

        return $response;
    }
}
