<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {

            RateLimiter::for('api', function (Request $request) {
                return Limit::perMinute(120)->by(
                    $request->user()?->id ?: $request->ip()
                );
            });

            RateLimiter::for('login', function (Request $request) {
                return Limit::perMinute(5)->by(
                    strtolower($request->input('email')).'|'.$request->ip()
                );
            });

        }
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // The Next.js client uses Sanctum bearer tokens, not cookie sessions.
        // Keep API authentication stateless so browser Origin headers do not
        // switch login and writes into Sanctum's session/CSRF workflow.

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Validation Error (422)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            ValidationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Authentication Error (401)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Authorization Error (403)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            AccessDeniedHttpException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                ], 403);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 404 Errors (Model Not Found & Route Not Found)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            NotFoundHttpException $e,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Resource not found.'
                    : 'Route not found.',
            ], 404);
        });

        /*
        |--------------------------------------------------------------------------
        | Other HTTP Exceptions (405, 429, etc.)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            HttpExceptionInterface $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode());
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Fallback Server Error (500)
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            Throwable $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'Server error.',
                ], 500);
            }
        });

    })
    ->create();
