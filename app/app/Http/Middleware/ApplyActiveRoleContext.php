<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActiveRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyActiveRoleContext
{
    public function __construct(
        private ActiveRoleService $activeRoleService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->activeRoleService->resolveAndBind($request, $user);
        }

        return $next($request);
    }
}
