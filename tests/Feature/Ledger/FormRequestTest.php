<?php

use App\Http\Controllers\Ledger\AttachmentController;
use App\Http\Controllers\Ledger\ImportController as LedgerImportController;
use App\Http\Controllers\Ledger\PayeeController as LedgerPayeeController;
use App\Http\Controllers\Ledger\SettingsController as LedgerSettingsController;
use App\Http\Controllers\Ledger\TransactionController as LedgerTransactionController;
use App\Http\Requests\AdjustBalanceRequest;
use App\Http\Requests\BulkDestroyTransactionsRequest;
use App\Http\Requests\BulkUpdateTransactionsRequest;
use App\Http\Requests\DestroyCategoryRequest;
use App\Http\Requests\ParseImportRequest;
use App\Http\Requests\PayBillRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\SaveOnboardingStepRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreImportMappingRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\TagRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAccountTypeRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateLedgerRequest;
use App\Http\Requests\UpdatePayeeRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

function ledgerFormRequest(string $requestClass, User $user, Ledger $ledger, string $method)
{
    $request = new $requestClass;
    $request->setMethod($method);
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(fn () => tap(new Route([$method], '/ledgers/{ledger}', []), function (Route $route) use ($ledger) {
        $route->bind(request());
        $route->setParameter('ledger', $ledger);
    }));

    return $request;
}

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

dataset('controller form request signatures', [
    'ledger payee store' => [LedgerPayeeController::class, 'store', UpdatePayeeRequest::class],
    'ledger payee update' => [LedgerPayeeController::class, 'update', UpdatePayeeRequest::class],
    'ledger attachment store' => [AttachmentController::class, 'store', StoreAttachmentRequest::class],
    'ledger import mapping store' => [LedgerImportController::class, 'storeMapping', StoreImportMappingRequest::class],
    'ledger settings account type update' => [LedgerSettingsController::class, 'updateAccountType', UpdateAccountTypeRequest::class],
    'ledger transaction bulk destroy' => [LedgerTransactionController::class, 'bulkDestroy', BulkDestroyTransactionsRequest::class],
]);

test('controllers use form requests for refactored validation endpoints', function (string $controller, string $method, string $requestClass) {
    $reflection = new ReflectionMethod($controller, $method);

    $parameterType = $reflection->getParameters()[0]->getType();

    expect($parameterType)->not->toBeNull();
    expect($parameterType->getName())->toBe($requestClass);
})->with('controller form request signatures');

dataset('ledger authorization requests', [
    'adjust balance' => [AdjustBalanceRequest::class, 'POST'],
    'bulk destroy transactions' => [BulkDestroyTransactionsRequest::class, 'POST'],
    'bulk update transactions' => [BulkUpdateTransactionsRequest::class, 'POST'],
    'destroy category' => [DestroyCategoryRequest::class, 'DELETE'],
    'parse import' => [ParseImportRequest::class, 'POST'],
    'pay bill' => [PayBillRequest::class, 'POST'],
    'reorder' => [ReorderRequest::class, 'POST'],
    'store account' => [StoreAccountRequest::class, 'POST'],
    'store account type' => [StoreAccountTypeRequest::class, 'POST'],
    'store attachment' => [StoreAttachmentRequest::class, 'POST'],
    'store bill' => [StoreBillRequest::class, 'POST'],
    'store budget' => [StoreBudgetRequest::class, 'POST'],
    'store category' => [StoreCategoryRequest::class, 'POST'],
    'store import mapping' => [StoreImportMappingRequest::class, 'POST'],
    'store payee' => [UpdatePayeeRequest::class, 'POST'],
    'store transaction' => [StoreTransactionRequest::class, 'POST'],
    'store tag' => [TagRequest::class, 'POST'],
    'update account' => [UpdateAccountRequest::class, 'PUT'],
    'update account type' => [UpdateAccountTypeRequest::class, 'PUT'],
    'update bill' => [UpdateBillRequest::class, 'PUT'],
    'update budget' => [UpdateBudgetRequest::class, 'PUT'],
    'update category' => [UpdateCategoryRequest::class, 'PATCH'],
    'update ledger' => [UpdateLedgerRequest::class, 'PATCH'],
    'update payee' => [UpdatePayeeRequest::class, 'PATCH'],
    'update settings' => [UpdateSettingsRequest::class, 'PUT'],
    'update tag' => [TagRequest::class, 'PATCH'],
    'update transaction' => [UpdateTransactionRequest::class, 'PUT'],
]);

test('ledger-scoped form requests require access to the current ledger', function (string $requestClass, string $method) {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $ownerRequest = ledgerFormRequest($requestClass, $owner, $ledger, $method);
    $outsiderRequest = ledgerFormRequest($requestClass, $outsider, $ledger, $method);

    expect($ownerRequest->authorize())->toBeTrue()
        ->and($outsiderRequest->authorize())->toBeFalse();
})->with('ledger authorization requests');
