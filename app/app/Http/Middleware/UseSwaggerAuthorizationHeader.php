<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseSwaggerAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $swaggerAuthorization = $request->headers->get('X-Authorization');

            if (is_string($swaggerAuthorization) && $swaggerAuthorization !== '') {
                $request->headers->set('Authorization', $swaggerAuthorization);
            }
        }

        return $next($request);
    }
}
