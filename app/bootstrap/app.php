<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'automation' => \App\Http\Middleware\EnsureAutomationAccess::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'reporter.terms' => \App\Http\Middleware\EnsureReporterAcceptedTerms::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failas per didelis.'], 413);
            }

            return back()->with('error', 'Failas per didelis.');
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Puslapis ar resursas nerastas.',
                    'code' => 'not_found',
                ], 404);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Prieiga uždrausta.',
                    'code' => 'forbidden',
                ], 403);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Reikalinga autentifikacija.',
                    'code' => 'unauthenticated',
                ], 401);
            }

            if ($e instanceof HttpException) {
                $status = $e->getStatusCode();
                if ($status >= 400 && $status < 500) {
                    $defaults = [
                        403 => 'Prieiga uždrausta.',
                        404 => 'Puslapis ar resursas nerastas.',
                    ];

                    return response()->json([
                        'message' => $e->getMessage() ?: ($defaults[$status] ?? 'Užklausa negalima.'),
                        'code' => 'http_error',
                    ], $status);
                }
                if ($status >= 500 && $status < 600) {
                    return response()->json([
                        'message' => $e->getMessage() ?: 'Vidinė serverio klaida.',
                        'code' => 'server_error',
                    ], $status);
                }
            }

            return response()->json([
                'message' => 'Vidinė serverio klaida.',
                'code' => 'server_error',
            ], 500);
        });
    })->create();
