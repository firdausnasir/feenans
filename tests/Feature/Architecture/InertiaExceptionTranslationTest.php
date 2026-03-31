<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('initial page get domain exception uses centralized inertia exception behavior', function () {
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

test('deferred or partial reload style request does not fall back to an ad hoc json error envelope', function () {
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

test('api proof tag domain exception renders json through centralized exception handling', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Proof exception from route',
        ]);
});

test('api proof tag domain exception denies outsider access before throwing', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($outsider)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJsonMissing([
            'message' => 'Proof exception from route',
        ]);
});
