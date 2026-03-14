<?php

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\SaveOnboardingStepRequest;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

// ─── StoreBillRequest ────────────────────────────────────────────────────────

test('StoreBillRequest passes with valid data', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'name' => 'Rent',
        'transaction_type' => 'expense',
        'amount' => 1200.00,
        'account_id' => $account->id,
        'category_id' => null,
        'payee_id' => null,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'recurrence_day' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => true,
        'end_type' => null,
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});

test('StoreBillRequest fails when name is missing', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'amount' => 1200.00,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreBillRequest fails when amount is below minimum', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'name' => 'Rent',
        'amount' => 0,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('amount'))->toBeTrue();
});

test('StoreBillRequest fails when recurrence_type is invalid', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'name' => 'Rent',
        'amount' => 100.00,
        'account_id' => $account->id,
        'recurrence_type' => 'fortnightly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('recurrence_type'))->toBeTrue();
});

test('StoreBillRequest requires end_date when end_type is on_date', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'name' => 'Rent',
        'amount' => 100.00,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'end_type' => 'on_date',
        'end_date' => null,
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('end_date'))->toBeTrue();
});

test('StoreBillRequest requires end_after_occurrences when end_type is after_occurrences', function () {
    $ledger = Ledger::factory()->create();
    $account = Account::factory()->for($ledger)->create();

    $data = [
        'name' => 'Rent',
        'amount' => 100.00,
        'account_id' => $account->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'end_type' => 'after_occurrences',
        'end_after_occurrences' => null,
    ];

    $request = new StoreBillRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('end_after_occurrences'))->toBeTrue();
});

// ─── ReorderRequest ──────────────────────────────────────────────────────────

test('ReorderRequest passes with valid items array', function () {
    $data = [
        'items' => [
            ['id' => 1, 'position' => 0],
            ['id' => 2, 'position' => 1],
            ['id' => 3, 'position' => 2],
        ],
    ];

    $request = new ReorderRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});

test('ReorderRequest fails when items is missing', function () {
    $data = [];

    $request = new ReorderRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items'))->toBeTrue();
});

test('ReorderRequest fails when item id is missing', function () {
    $data = [
        'items' => [
            ['position' => 0],
        ],
    ];

    $request = new ReorderRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.id'))->toBeTrue();
});

test('ReorderRequest fails when item position is missing', function () {
    $data = [
        'items' => [
            ['id' => 1],
        ],
    ];

    $request = new ReorderRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.position'))->toBeTrue();
});

test('ReorderRequest fails when item position is negative', function () {
    $data = [
        'items' => [
            ['id' => 1, 'position' => -1],
        ],
    ];

    $request = new ReorderRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.position'))->toBeTrue();
});

// ─── UpdateSettingsRequest ───────────────────────────────────────────────────

test('UpdateSettingsRequest passes with valid data', function () {
    $data = [
        'name' => 'My Budget',
        'cycle_start_day' => 15,
    ];

    $request = new UpdateSettingsRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});

test('UpdateSettingsRequest fails when name is missing', function () {
    $data = [
        'cycle_start_day' => 15,
    ];

    $request = new UpdateSettingsRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('UpdateSettingsRequest fails when cycle_start_day is 0', function () {
    $data = [
        'name' => 'My Budget',
        'cycle_start_day' => 0,
    ];

    $request = new UpdateSettingsRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('cycle_start_day'))->toBeTrue();
});

test('UpdateSettingsRequest fails when cycle_start_day is 32', function () {
    $data = [
        'name' => 'My Budget',
        'cycle_start_day' => 32,
    ];

    $request = new UpdateSettingsRequest;
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('cycle_start_day'))->toBeTrue();
});

// ─── SaveOnboardingStepRequest ───────────────────────────────────────────────

test('SaveOnboardingStepRequest step 1 passes with valid data', function () {
    $data = [
        'name' => 'Personal Budget',
        'cycle_start_day' => 1,
        'seed_categories' => true,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '1');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});

test('SaveOnboardingStepRequest step 1 fails when name is missing', function () {
    $data = [
        'cycle_start_day' => 1,
        'seed_categories' => true,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '1');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('SaveOnboardingStepRequest step 1 fails when cycle_start_day is out of range', function () {
    $data = [
        'name' => 'Personal Budget',
        'cycle_start_day' => 32,
        'seed_categories' => false,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '1');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('cycle_start_day'))->toBeTrue();
});

test('SaveOnboardingStepRequest step 2 passes with valid data', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $data = [
        'name' => 'Main Wallet',
        'account_type_id' => $accountType->id,
        'initial_balance' => 500.00,
        'statement_day' => null,
        'include_in_totals' => true,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '2');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});

test('SaveOnboardingStepRequest step 2 fails when account_type_id does not exist', function () {
    $data = [
        'name' => 'Main Wallet',
        'account_type_id' => 99999,
        'initial_balance' => 500.00,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '2');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('account_type_id'))->toBeTrue();
});

test('SaveOnboardingStepRequest step 2 fails when name is missing', function () {
    $ledger = Ledger::factory()->create();
    $accountType = AccountType::factory()->for($ledger)->create();

    $data = [
        'account_type_id' => $accountType->id,
        'initial_balance' => 500.00,
    ];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '2');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('SaveOnboardingStepRequest step 3 requires no validation', function () {
    $data = [];

    $request = new SaveOnboardingStepRequest;
    $request->setRouteResolver(fn () => tap(new Route(['POST'], '/onboarding/{step}', []), function ($route) {
        $route->bind(request());
        $route->setParameter('step', '3');
    }));

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
});
