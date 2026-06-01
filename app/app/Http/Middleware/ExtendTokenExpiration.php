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
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $minutes = TokenExpiration::minutes();
            $threshold = now()->addMinutes(max(1, $minutes - 5));

            if (! $token->expires_at || $token->expires_at->lt($threshold)) {
                $token->forceFill(['expires_at' => TokenExpiration::next()])->save();
            }
        }

        return $response;
    }
}
