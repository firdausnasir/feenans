<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $this->category = Category::factory()->for($this->ledger)->create();
    $this->payee = Payee::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists bills with relationships', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->for($this->category)->for($this->payee)->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'ledger_id', 'name', 'amount', 'recurrence_type',
                    'next_due_date', 'is_active', 'account', 'category', 'payee',
                ],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('it lists bills with recent transactions', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create();

    Transaction::factory()->for($this->ledger)->for($this->account)->count(7)->create([
        'bill_id' => $bill->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills?with_transactions=1");

    $response->assertSuccessful();

    $billData = $response->json('data.0');
    expect($billData)->toHaveKey('transactions');
    expect(count($billData['transactions']))->toBeLessThanOrEqual(5);
});

test('it lists only active bills', function () {
    Bill::factory()->for($this->ledger)->for($this->account)->create(['is_active' => true]);
    Bill::factory()->for($this->ledger)->for($this->account)->create(['is_active' => false]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills?active_only=1");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.is_active'))->toBeTrue();
});

test('it returns upcoming bills grouped', function () {
    $today = CarbonImmutable::today();

    // Upcoming: due within 30 days
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => $today->addDays(5),
        'is_active' => true,
    ]);

    // Due today
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => $today,
        'is_active' => true,
    ]);

    // Missed: past due, non-auto
    Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => $today->subDays(3),
        'is_active' => true,
        'auto_create' => false,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills?upcoming=1");

    $response->assertSuccessful()
        ->assertJsonStructure(['upcoming', 'due', 'missed']);

    expect(count($response->json('upcoming')))->toBe(1);
    expect(count($response->json('due')))->toBe(1);
    expect(count($response->json('missed')))->toBe(1);
});

test('it creates bill with validation', function () {
    $data = [
        'name' => 'Internet Bill',
        'transaction_type' => 'expense',
        'amount' => 99.99,
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'payee_id' => $this->payee->id,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => false,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/bills", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Internet Bill')
        ->assertJsonPath('data.amount', '99.99');

    $this->assertDatabaseHas('bills', [
        'ledger_id' => $this->ledger->id,
        'name' => 'Internet Bill',
    ]);
});

test('it updates bill', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create([
        'name' => 'Old Name',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}", [
            'name' => 'Updated Name',
            'amount' => 150.00,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.amount', '150.00');
});

test('it deletes bill', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}");

    $response->assertNoContent();

    expect(Bill::find($bill->id))->toBeNull();
});

test('it pays bill and creates transaction', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create([
        'name' => 'Monthly Rent',
        'amount' => 1200.00,
        'transaction_type' => 'expense',
        'next_due_date' => CarbonImmutable::today(),
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}/pay");

    $response->assertCreated()
        ->assertJsonPath('data.bill_id', $bill->id)
        ->assertJsonPath('data.amount', '-1200.00');

    $this->assertDatabaseHas('transactions', [
        'bill_id' => $bill->id,
        'ledger_id' => $this->ledger->id,
    ]);
});

test('it pays bill and advances next due date', function () {
    $dueDate = CarbonImmutable::today();

    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => $dueDate,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}/pay");

    $bill->refresh();

    expect($bill->next_due_date->toDateString())->toBe($dueDate->addMonth()->toDateString());
    expect($bill->occurrences_count)->toBe(1);
});

test('it toggles bill active status', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create([
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->patchJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}/toggle");

    $response->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    // Toggle back
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->patchJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}/toggle");

    $response->assertSuccessful()
        ->assertJsonPath('data.is_active', true);
});

test('it returns 422 for invalid recurrence', function () {
    $data = [
        'name' => 'Bad Bill',
        'transaction_type' => 'expense',
        'amount' => 50.00,
        'account_id' => $this->account->id,
        'recurrence_type' => 'invalid_type',
        'recurrence_interval' => 1,
        'next_due_date' => '2026-04-01',
        'auto_create' => false,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/bills", $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrence_type']);
});

test('it computes missed cycles', function () {
    $bill = Bill::factory()->for($this->ledger)->for($this->account)->create([
        'next_due_date' => CarbonImmutable::today()->subMonths(3),
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'is_active' => true,
        'auto_create' => false,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills?with_missed=1");

    $response->assertSuccessful();

    $billData = $response->json('data.0');
    expect($billData['missed_cycles'])->toBeGreaterThanOrEqual(3);
});
