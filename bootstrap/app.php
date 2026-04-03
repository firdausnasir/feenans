<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureHasWorkspace;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsurePremium;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;
use Inertia\Middleware as InertiaMiddleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
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

        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            Authenticate::class,
            SubstituteBindings::class,
            HandleAppearance::class,
            InertiaMiddleware::class,
            Authorize::class,
        ]);

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

        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $payload = [
                    'code' => $exception->codeName(),
                    'message' => $exception->safeMessage(),
                    'type' => $exception->type(),
                ];

                if ($exception->context() !== []) {
                    $payload['context'] = $exception->context();
                }

                return response()->json($payload, $exception->status());
            }

            if ($request->isMethodSafe()) {
                return Inertia::render('error-page', ['status' => $exception->status()])
                    ->toResponse($request)
                    ->setStatusCode($exception->status());
            }

            return back()->with('error', $exception->safeMessage());
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
