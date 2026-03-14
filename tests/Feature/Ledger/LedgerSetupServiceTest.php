<?php

use App\Models\User;
use App\Services\LedgerSetupService;

test('ledger setup service creates a ledger with default account types and seeded categories', function () {
    $user = User::factory()->create();

    $ledger = app(LedgerSetupService::class)->createForUser($user, [
        'name' => 'Personal',
        'currency_code' => 'MYR',
        'uses_seeded_categories' => true,
    ]);

    expect($ledger->user->is($user))->toBeTrue()
        ->and($ledger->accountTypes()->count())->toBe(7)
        ->and($ledger->categories()->count())->toBeGreaterThan(0);
});

test('ledger setup service can create a blank ledger without seeded categories', function () {
    $user = User::factory()->create();

    $ledger = app(LedgerSetupService::class)->createForUser($user, [
        'name' => 'Work',
        'currency_code' => 'USD',
        'uses_seeded_categories' => false,
    ]);

    expect($ledger->categories()->count())->toBe(0)
        ->and($ledger->accountTypes()->count())->toBe(7);
});

test('ledger setup service accepts cycle_start_day parameter', function () {
    $user = User::factory()->create();

    $ledger = app(LedgerSetupService::class)->createForUser($user, [
        'name' => 'Test',
        'currency_code' => 'MYR',
        'uses_seeded_categories' => false,
        'cycle_start_day' => 15,
    ]);

    expect($ledger->cycle_start_day)->toBe(15);
});
