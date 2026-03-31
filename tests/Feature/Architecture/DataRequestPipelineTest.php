<?php

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('injected data validation returns web session errors on an inertia form request', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('architecture.proof-tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('architecture.proof-tags.store', $ledger), [
            'name' => '',
            'color' => 'invalid-color',
        ]);

    $response->assertRedirect(route('architecture.proof-tags.index', $ledger))
        ->assertSessionHasErrors([
            'name' => 'Please enter a proof tag name.',
            'color' => 'Please enter a valid proof tag color.',
        ]);
});

test('successful web post uses injected proof tag data and redirects with flash', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('architecture.proof-tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('architecture.proof-tags.store', $ledger), [
            'name' => 'travel',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('architecture.proof-tags.index', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Proof tag accepted for ledger '.$ledger->id.' by user '.$user->id.'.');

    $this->flushHeaders();

    $this->actingAs($user)
        ->get(route('architecture.proof-tags.index', $ledger))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('architecture/proof-tags/index')
            ->where('currentLedger.id', $ledger->id)
            ->where('proof.user_id', $user->id)
            ->where('proof.ledger_id', $ledger->id)
            ->where('flash.success', 'Proof tag accepted for ledger '.$ledger->id.' by user '.$user->id.'.')
        );
});

test('injected data validation returns json 422 on an api request', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.architecture.proof-tags.store', $ledger), [
            'name' => '',
            'color' => 'invalid-color',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'color'])
        ->assertJsonPath('errors.name.0', 'Please enter a proof tag name.')
        ->assertJsonPath('errors.color.0', 'Please enter a valid proof tag color.');
});

test('injected data authorization denies based on ledger policy', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($outsider)
        ->postJson(route('api.v1.architecture.proof-tags.store', $ledger), [
            'name' => 'travel',
            'color' => '#22c55e',
        ])
        ->assertForbidden();
});

test('injected data can read route model and current user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.architecture.proof-tags.store', $ledger), [
            'name' => 'travel',
            'color' => '#22c55e',
        ])
        ->assertCreated()
        ->assertJsonPath('data.ledger_id', $ledger->id)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.name', 'travel')
        ->assertJsonPath('data.color', '#22c55e');
});

test('custom validation messages still appear', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.architecture.proof-tags.store', $ledger), [
            'name' => '',
            'color' => '#22c55e',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Please enter a proof tag name.');
});

test('file upload payloads still validate correctly', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('architecture.proof-tags.index', $ledger))
        ->post(route('architecture.proof-tags.store', $ledger), [
            'name' => 'receipt',
            'color' => '#22c55e',
            'icon' => UploadedFile::fake()->create('receipt.pdf', 32, 'application/pdf'),
        ]);

    $response->assertRedirect(route('architecture.proof-tags.index', $ledger))
        ->assertSessionHasErrors([
            'icon' => 'The icon field must be an image.',
        ]);
});

test('valid image upload payload is accepted by injected data', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.architecture.proof-tags.store', $ledger), [
            'name' => 'receipt',
            'color' => '#22c55e',
            'icon' => UploadedFile::fake()->image('icon.png', 32, 32),
        ])
        ->assertCreated()
        ->assertJsonPath('data.icon_uploaded', true);
});
