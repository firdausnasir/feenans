<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('category index returns categories with children', function () {
    Category::factory()->for($this->ledger)->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ledger_id', 'name', 'transaction_type', 'color', 'position'],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('category show returns a single category', function () {
    $category = Category::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/categories/{$category->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $category->id);
});

test('payee index returns all payees', function () {
    Payee::factory()->for($this->ledger)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/payees");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('payee show returns a single payee', function () {
    $payee = Payee::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/payees/{$payee->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $payee->id)
        ->assertJsonPath('data.name', $payee->name);
});

test('bill index returns all bills with relations', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    Bill::factory()->for($this->ledger)->for($account)->count(2)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'ledger_id', 'name', 'amount', 'recurrence_type',
                    'next_due_date', 'is_active', 'account',
                ],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('bill show returns a single bill', function () {
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $bill = Bill::factory()->for($this->ledger)->for($account)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/bills/{$bill->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $bill->id);
});

test('tag index returns all tags', function () {
    Tag::factory()->for($this->ledger)->count(3)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/tags");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ledger_id', 'name', 'color'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('tag show returns a single tag', function () {
    $tag = Tag::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/tags/{$tag->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $tag->id)
        ->assertJsonPath('data.name', $tag->name);
});
