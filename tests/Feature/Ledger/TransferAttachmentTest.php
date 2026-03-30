<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('transfer store attaches files to both outgoing and incoming transactions', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $files = [
        UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf'),
        UploadedFile::fake()->image('photo.jpg', 100, 100),
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 100.00,
            'description' => 'Transfer with attachments',
            'transaction_date' => '2026-03-17',
            'attachments' => $files,
        ]);

    $response->assertRedirect();

    $transactions = $ledger->transactions()->get();
    expect($transactions)->toHaveCount(2);

    $outgoing = $transactions->firstWhere('amount', '<', 0);
    $incoming = $transactions->firstWhere('amount', '>', 0);

    expect($outgoing->attachments()->count())->toBe(2)
        ->and($incoming->attachments()->count())->toBe(2);

    $outgoing->load('attachments');
    $incoming->load('attachments');

    $outgoingFilenames = $outgoing->attachments->pluck('filename')->sort()->values()->toArray();
    $incomingFilenames = $incoming->attachments->pluck('filename')->sort()->values()->toArray();

    expect($outgoingFilenames)->toBe(['photo.jpg', 'receipt.pdf'])
        ->and($incomingFilenames)->toBe(['photo.jpg', 'receipt.pdf']);

    foreach ($outgoing->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }

    foreach ($incoming->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }
});

test('transfer store syncs tags to both outgoing and incoming transactions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $tag1 = Tag::factory()->for($ledger)->create(['name' => 'savings']);
    $tag2 = Tag::factory()->for($ledger)->create(['name' => 'monthly']);

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 200.00,
            'description' => 'Transfer with tags',
            'transaction_date' => '2026-03-17',
            'tag_ids' => [$tag1->id, $tag2->id],
        ]);

    $response->assertRedirect();

    $transactions = $ledger->transactions()->get();
    $outgoing = $transactions->firstWhere('amount', '<', 0);
    $incoming = $transactions->firstWhere('amount', '>', 0);

    $expectedTagIds = collect([$tag1->id, $tag2->id])->sort()->values()->toArray();

    expect($outgoing->tags()->pluck('tags.id')->sort()->values()->toArray())->toBe($expectedTagIds)
        ->and($incoming->tags()->pluck('tags.id')->sort()->values()->toArray())->toBe($expectedTagIds);
});

test('transfer store works without attachments or tags', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $fromAccount = Account::factory()->for($ledger)->for($accountType)->create();
    $toAccount = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 50.00,
            'description' => 'Simple transfer',
            'transaction_date' => '2026-03-17',
        ]);

    $response->assertRedirect();

    $transactions = $ledger->transactions()->get();
    expect($transactions)->toHaveCount(2);

    $outgoing = $transactions->firstWhere('amount', '<', 0);
    $incoming = $transactions->firstWhere('amount', '>', 0);

    expect($outgoing->attachments()->count())->toBe(0)
        ->and($incoming->attachments()->count())->toBe(0)
        ->and($outgoing->tags()->count())->toBe(0)
        ->and($incoming->tags()->count())->toBe(0);
});
