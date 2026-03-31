<?php

use App\Exceptions\Domain\DomainNotAllowed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function runWithProductionEnvironmentForDomainExceptions(callable $callback): mixed
{
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        return $callback();
    } finally {
        app()['env'] = $originalEnvironment;
    }
}

test('web mutation request maps a domain exception to redirect and flash', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('architecture.proof-tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('architecture.proof-tags.store', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]), [
            'name' => 'travel',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('architecture.proof-tags.index', $ledger))
        ->assertSessionHas('error', 'Proof exception from action');
});

test('web get maps a domain exception to centralized inertia error handling', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('architecture.proof-tags.index', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 403)
        );
});

test('api request maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Proof exception from route',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('api domain exception rendering uses safe message stable code and optional context', function () {
    $request = Request::create('/api/_tests/domain', 'GET');

    $exception = new class('Unsafe internal detail', 'Safe client message', ['ledger_id' => 123]) extends DomainNotAllowed {};

    $response = TestResponse::fromBaseResponse(
        app(ExceptionHandler::class)->render($request, $exception),
        $request,
    );

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Safe client message',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
            'context' => [
                'ledger_id' => 123,
            ],
        ])
        ->assertJsonMissing([
            'message' => 'Unsafe internal detail',
        ]);
});

test('existing normal 404 rendering for web still uses the inertia error page in production', function () {
    $request = Request::create('/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironmentForDomainExceptions(
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

test('existing normal 404 rendering for api still uses the default json shape in production', function () {
    $request = Request::create('/api/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironmentForDomainExceptions(
        fn () => TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new NotFoundHttpException('Missing route')),
            $request,
        )
    );

    $response->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('inertia partial reload request uses the centralized inertia error payload for domain exceptions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
            'X-Inertia-Partial-Component' => 'ledgers/tags/index',
            'X-Inertia-Partial-Data' => 'tags',
        ])
        ->get(route('architecture.proof-tags.index', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]));

    $response->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonMissing(['message' => 'Proof exception from route'])
        ->assertJsonPath('component', 'error-page')
        ->assertJsonPath('props.status', 403);
});

test('non-json web mutation fallback uses the safe message', function () {
    $request = Request::create('/_tests/domain-action', 'POST', server: [
        'HTTP_REFERER' => 'http://localhost/previous',
    ]);
    $session = app(Store::class);
    $session->start();
    $request->setLaravelSession($session);

    $exception = new class('Unsafe internal detail', 'Safe client message') extends DomainNotAllowed {};

    $response = TestResponse::fromBaseResponse(
        app(ExceptionHandler::class)->render($request, $exception),
        $request,
    );

    $response->assertRedirect();

    expect($session->get('error'))->toBe('Safe client message');
    expect($session->get('error'))->not->toBe('Unsafe internal detail');
});
