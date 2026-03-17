<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('it lists tags with transaction counts', function () {
    $tag = Tag::factory()->for($this->ledger)->create();
    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();
    $category = Category::factory()->for($this->ledger)->create();
    $transaction = Transaction::factory()->for($this->ledger)->for($account)->for($category)->create();
    $transaction->tags()->attach($tag);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/tags?with_counts=1");

    $response->assertSuccessful()
        ->assertJsonPath('data.0.transactions_count', 1);
});

test('it creates a tag with validation', function () {
    $data = [
        'name' => 'Urgent',
        'color' => '#ff0000',
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/tags", $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Urgent')
        ->assertJsonPath('data.color', '#ff0000');

    $this->assertDatabaseHas('tags', [
        'ledger_id' => $this->ledger->id,
        'name' => 'Urgent',
    ]);
});

test('it returns 422 for invalid tag data', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/tags", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('it returns 422 for invalid tag color format', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/tags", [
            'name' => 'Bad Color',
            'color' => 'not-a-hex',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['color']);
});

test('it updates a tag', function () {
    $tag = Tag::factory()->for($this->ledger)->create(['name' => 'Old Tag']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->putJson("/api/v1/ledgers/{$this->ledger->id}/tags/{$tag->id}", [
            'name' => 'New Tag',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Tag');
});

test('it deletes a tag', function () {
    $tag = Tag::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/tags/{$tag->id}");

    $response->assertNoContent();

    expect(Tag::find($tag->id))->toBeNull();
});

test('it returns 401 when unauthenticated for tags', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/tags");

    $response->assertUnauthorized();
});
