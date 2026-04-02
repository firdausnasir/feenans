<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function importApiRoute(string $name, mixed $parameters): string
{
    expect(app('router')->has($name))->toBeTrue();

    return route($name, $parameters);
}

test('import api parse requires sanctum authentication', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this
        ->withHeader('Accept', 'application/json')
        ->post(importApiRoute('api.v1.ledgers.import.parse', $ledger), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', "date,amount\n2026-01-01,-25.00"),
        ])
        ->assertUnauthorized();
});

test('import api parse returns preview data and opaque pending handle', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post(importApiRoute('api.v1.ledgers.import.parse', $ledger), [
            'file' => UploadedFile::fake()->createWithContent(
                'maybank.csv',
                "Transaction Date,Description,Debit,Credit\n2026-01-01,Coffee,25.00,\n"
            ),
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.headers.0', 'Transaction Date')
        ->assertJsonPath('data.preview_rows.0.0', '2026-01-01')
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.detected_bank', 'Maybank')
        ->assertJsonPath('data.suggested_mapping.date', 'Transaction Date');

    expect($response->json('data.pending_import_handle'))->toBeString()->not->toBe('')
        ->and($response->json('data.file_path'))->toBeNull()
        ->and(Storage::disk('local')->allFiles('imports/temp'))->toHaveCount(1);
});

test('import api execute uses pending handle without browser session and resolves category payee history and cleanup', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create(['name' => 'Groceries']);

    Sanctum::actingAs($user, ['*']);

    $parseResponse = $this
        ->withHeader('Accept', 'application/json')
        ->post(importApiRoute('api.v1.ledgers.import.parse', $ledger), [
            'file' => UploadedFile::fake()->createWithContent(
                'import.csv',
                "date,amount,description,category,payee\n2026-01-01,-25.00,Coffee,Groceries,Starbucks\n"
            ),
        ]);

    $parseResponse->assertSuccessful();

    $handle = $parseResponse->json('data.pending_import_handle');

    expect($handle)->toBeString()->not->toBe('')
        ->and(Storage::disk('local')->allFiles('imports/temp'))->toHaveCount(1);

    $this->postJson(importApiRoute('api.v1.ledgers.import.execute', $ledger), [
        'pending_import_handle' => $handle,
        'account_id' => $account->id,
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'category' => 'category',
            'payee' => 'payee',
        ],
        'skip_duplicates' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.imported_count', 1)
        ->assertJsonPath('data.skipped_count', 0)
        ->assertJsonPath('data.message', 'Imported 1 transactions');

    $transaction = $ledger->transactions()->with(['category', 'payee'])->sole();

    expect($transaction->category?->id)->toBe($category->id)
        ->and($transaction->payee?->name)->toBe('Starbucks')
        ->and($ledger->importRecords()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('imports/temp'))->toHaveCount(0);
});

test('import api execute requires pending import handle instead of raw file path', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(importApiRoute('api.v1.ledgers.import.execute', $ledger), [
        'file_path' => 'imports/temp/raw.csv',
        'account_id' => $account->id,
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
        ],
        'skip_duplicates' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pending_import_handle']);
});

test('import api mapping store returns created mapping json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    Sanctum::actingAs($user, ['*']);

    $this->postJson(importApiRoute('api.v1.ledgers.import.mappings.store', $ledger), [
        'name' => 'Maybank Format',
        'mapping' => [
            'date' => 'Transaction Date',
            'amount' => 'Debit',
            'description' => 'Description',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Maybank Format')
        ->assertJsonPath('data.mapping.date', 'Transaction Date');

    expect($ledger->importMappings()->where('name', 'Maybank Format')->exists())->toBeTrue();
});

test('import api mapping destroy deletes mapping and returns json', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $mapping = ImportMapping::factory()->for($ledger)->create([
        'name' => 'Delete Me',
        'mapping' => [
            'date' => 'Date',
            'amount' => 'Amount',
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(importApiRoute('api.v1.ledgers.import.mappings.destroy', [$ledger, $mapping]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $mapping->id)
        ->assertJsonPath('data.name', 'Delete Me');

    expect(ImportMapping::query()->whereKey($mapping->id)->exists())->toBeFalse();
});
