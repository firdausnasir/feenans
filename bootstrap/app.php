<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureHasWorkspace;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsurePremium;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->statefulApi();

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureOnboardingComplete::class,
            EnsureHasWorkspace::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'premium' => EnsurePremium::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $exception): bool {
            return $request->expectsJson() || $request->is('api/*');
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            if (! app()->environment(['local', 'testing']) && in_array($status, [500, 503, 404, 403])) {
                return Inertia::render('error-page', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            if ($response->getStatusCode() === 419) {
                return back()->with([
                    'error' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();
