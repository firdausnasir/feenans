<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ledger = Ledger::factory()->for($this->user)->create();
    $this->accountType = AccountType::factory()->for($this->ledger)->create();
    $this->token = $this->user->createToken('test');
});

test('it parses csv and returns preview', function () {
    $csvContent = "Date,Description,Amount\n2025-01-15,Groceries,-50.00\n2025-01-16,Salary,3000.00\n";
    $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/import/parse", [
            'file' => $file,
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['headers', 'preview_rows', 'total_rows', 'file_path'])
        ->assertJsonPath('headers', ['Date', 'Description', 'Amount'])
        ->assertJsonPath('total_rows', 2);
});

test('it imports transactions from csv', function () {
    $disk = config('filesystems.ledger_disk', config('filesystems.default', 'local'));
    Storage::fake($disk);

    $account = Account::factory()->for($this->ledger)->for($this->accountType)->create();

    $csvContent = "Date,Description,Amount\n2025-01-15,Groceries,-50.00\n2025-01-16,Salary,3000.00\n";
    $filePath = 'imports/temp/test.csv';
    Storage::disk($disk)->put($filePath, $csvContent);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/import/execute", [
            'file_path' => $filePath,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'Date',
                'amount' => 'Amount',
                'description' => 'Description',
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['imported', 'skipped', 'errors'])
        ->assertJsonPath('imported', 2);

    $this->assertDatabaseHas('imports', [
        'ledger_id' => $this->ledger->id,
        'imported_count' => 2,
    ]);
});

test('it returns validation errors for bad mappings', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/import/execute", [
            'file_path' => '',
            'account_id' => '',
            'mapping' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file_path', 'account_id', 'mapping.date', 'mapping.amount']);
});

test('it saves and retrieves column mappings', function () {
    // Save a mapping
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->postJson("/api/v1/ledgers/{$this->ledger->id}/import/mappings", [
            'name' => 'Maybank Format',
            'mapping' => [
                'date' => 'Transaction Date',
                'amount' => 'Debit',
                'description' => 'Description',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Maybank Format');

    // Retrieve mappings
    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/import/mappings");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Maybank Format');
});

test('it deletes column mapping', function () {
    $mapping = ImportMapping::factory()->for($this->ledger)->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->deleteJson("/api/v1/ledgers/{$this->ledger->id}/import/mappings/{$mapping->id}");

    $response->assertNoContent();

    expect(ImportMapping::find($mapping->id))->toBeNull();
});

test('it returns import history', function () {
    $this->ledger->importRecords()->create([
        'filename' => 'test.csv',
        'row_count' => 10,
        'imported_count' => 8,
        'skipped_count' => 2,
        'mapping_used' => ['date' => 'Date', 'amount' => 'Amount'],
        'imported_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token->plainTextToken}")
        ->getJson("/api/v1/ledgers/{$this->ledger->id}/import/history");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'test.csv');
});

test('it returns 401 when unauthenticated for import', function () {
    $response = $this->getJson("/api/v1/ledgers/{$this->ledger->id}/import/history");

    $response->assertUnauthorized();
});
