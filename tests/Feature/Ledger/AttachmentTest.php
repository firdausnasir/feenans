<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('store uploads attachment and returns created response', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    $this->from(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $response = $this
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $attachment = $transaction->attachments()->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->filename)->toBe('receipt.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf');

    Storage::disk('local')->assertExists($attachment->path);
});

test('store supports multiple attachments', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $files = [
        UploadedFile::fake()->create('receipt1.pdf', 256, 'application/pdf'),
        UploadedFile::fake()->image('photo.jpg', 100, 100),
    ];

    $this->from(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $response = $this
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'attachments' => $files,
        ]);

    $response->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    expect($transaction->attachments()->count())->toBe(2);
});

test('store validates file type and rejects unsupported mimes', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('script.exe', 128, 'application/octet-stream');

    $response = $this
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'attachments' => [$file],
        ]);

    $response->assertSessionHasErrors('attachments.0');
});

test('store validates max file size', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf');

    $response = $this
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'attachments' => [$file],
        ]);

    $response->assertSessionHasErrors('attachments.0');
});

test('destroy deletes attachment record and file from disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    $this
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'file' => $file,
        ])
        ->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $attachment = $transaction->fresh()->attachments()->first();

    expect($attachment)->not->toBeNull();

    Storage::disk('local')->assertExists($attachment->path);

    $response = $this
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->actingAs($user)
        ->delete("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments/{$attachment->id}");

    $response->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    expect($transaction->fresh()->attachments()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('show returns file content for authorized user', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    $this
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->actingAs($user)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'file' => $file,
        ])
        ->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $attachment = $transaction->fresh()->attachments()->first();

    $response = $this
        ->actingAs($user)
        ->get("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments/{$attachment->id}");

    $response->assertSuccessful();
});

test('show returns forbidden for unauthorized user', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    $this
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->actingAs($owner)
        ->post("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments", [
            'file' => $file,
        ])
        ->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $attachment = $transaction->fresh()->attachments()->first();

    $response = $this
        ->actingAs($user)
        ->get("/ledgers/{$ledger->id}/transactions/{$transaction->id}/attachments/{$attachment->id}");

    $response->assertForbidden();
});

test('attachment uploads use the configured filesystem disk', function () {
    config()->set('app.attachment_disk', 's3');
    Storage::fake('s3');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $transaction = Transaction::factory()->for($ledger)->for($account)->create();

    $file = UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf');

    $this->actingAs($user)
        ->from(route('ledgers.transactions.edit', [$ledger, $transaction]))
        ->post(route('ledgers.transactions.attachments.store', [$ledger, $transaction]), [
            'file' => $file,
        ])
        ->assertRedirect(route('ledgers.transactions.edit', [$ledger, $transaction]));

    $attachment = $transaction->fresh()->attachments()->first();

    Storage::disk('s3')->assertExists($attachment->path);
});
