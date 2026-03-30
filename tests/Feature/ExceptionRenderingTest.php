<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function runWithProductionEnvironment(callable $callback): mixed
{
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        return $callback();
    } finally {
        app()['env'] = $originalEnvironment;
    }
}

test('api routes render json errors in production', function () {
    $request = Request::create('/api/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironment(
        fn () => TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new NotFoundHttpException('Missing route')),
            $request,
        )
    );

    $response->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('web requests still render the inertia error page in production', function () {
    $request = Request::create('/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironment(
        fn () => TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new NotFoundHttpException('Missing route')),
            $request,
        )
    );

    $response->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 404)
        );
});
