<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\ImportMapping;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// ─── Import Mappings ────────────────────────────────────────────────

test('mappings endpoint returns saved mappings for the ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    ImportMapping::factory()->for($ledger)->create(['name' => 'My Bank']);
    ImportMapping::factory()->for($ledger)->create(['name' => 'Another']);

    // Create mapping for a different ledger to ensure isolation
    ImportMapping::factory()->create(['name' => 'Other Ledger']);

    $response = $this
        ->actingAs($user)
        ->getJson(route('api.v1.ledgers.import.mappings', $ledger));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect(collect($data)->pluck('name')->sort()->values()->all())->toBe(['Another', 'My Bank']);
});

test('save mapping creates a new import mapping', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('api.v1.ledgers.import.mappings.store', $ledger), [
            'name' => 'Maybank Format',
            'mapping' => [
                'date' => 'Transaction Date',
                'amount' => 'Debit',
                'description' => 'Description',
            ],
        ]);

    $response->assertCreated();
    expect($ledger->importMappings()->count())->toBe(1);

    $mapping = $ledger->importMappings()->first();
    expect($mapping->name)->toBe('Maybank Format');
    expect($mapping->mapping)->toBe([
        'date' => 'Transaction Date',
        'amount' => 'Debit',
        'description' => 'Description',
    ]);
});

test('save mapping validates required fields', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('api.v1.ledgers.import.mappings.store', $ledger), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'mapping']);
});

test('destroy mapping deletes the mapping', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $mapping = ImportMapping::factory()->for($ledger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.import.mappings.destroy', [$ledger, $mapping]));

    $response->assertNoContent();
    expect(ImportMapping::query()->find($mapping->id))->toBeNull();
});

test('destroy mapping returns 404 for mapping from another ledger', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $otherLedger = Ledger::factory()->for($user)->create();
    $mapping = ImportMapping::factory()->for($otherLedger)->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('api.v1.ledgers.import.mappings.destroy', [$ledger, $mapping]));

    $response->assertNotFound();
});

test('unauthenticated users cannot access mapping endpoints', function () {
    $ledger = Ledger::factory()->create();

    $this->getJson(route('api.v1.ledgers.import.mappings', $ledger))
        ->assertUnauthorized();

    $this->postJson(route('api.v1.ledgers.import.mappings.store', $ledger), [
        'name' => 'Test',
        'mapping' => ['date' => 'date'],
    ])->assertUnauthorized();
});

// ─── Bank Detection ─────────────────────────────────────────────────

test('parse detects Maybank CSV format and returns suggested mapping', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "Transaction Date,Description,Debit,Credit\n01/01/2026,Coffee,25.00,\n02/01/2026,Salary,,5000.00";
    $file = UploadedFile::fake()->createWithContent('maybank.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertOk();
    $data = $response->json();

    expect($data)->toHaveKey('detected_bank');
    expect($data['detected_bank'])->toBe('Maybank');
    expect($data)->toHaveKey('suggested_mapping');
    expect($data['suggested_mapping']['date'])->toBe('Transaction Date');
    expect($data['suggested_mapping']['amount'])->toBe('Debit');
    expect($data['suggested_mapping']['description'])->toBe('Description');
});

test('parse detects CIMB CSV format', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "Date,Description,Amount(DR),Amount(CR)\n01/01/2026,Groceries,50.00,";
    $file = UploadedFile::fake()->createWithContent('cimb.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertOk();
    expect($response->json('detected_bank'))->toBe('CIMB');
});

test('parse detects RHB CSV format', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "Transaction Date,Transaction Description,Debit Amount,Credit Amount\n01/01/2026,Food,10.00,";
    $file = UploadedFile::fake()->createWithContent('rhb.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertOk();
    expect($response->json('detected_bank'))->toBe('RHB');
});

test('parse detects Public Bank CSV format', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "Date,Particulars,Withdrawal,Deposit\n01/01/2026,Transfer,100.00,";
    $file = UploadedFile::fake()->createWithContent('publicbank.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertOk();
    expect($response->json('detected_bank'))->toBe('Public Bank');
});

test('parse does not return detected_bank for unrecognized formats', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee";
    $file = UploadedFile::fake()->createWithContent('generic.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertOk();
    expect($response->json())->not->toHaveKey('detected_bank');
    expect($response->json())->not->toHaveKey('suggested_mapping');
});

// ─── Import History ─────────────────────────────────────────────────

test('store creates import record after successful import', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $path = 'imports/temp/history-test.csv';
    Storage::disk('local')->put($path, $csv);

    $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => true,
        ]);

    expect($ledger->importRecords()->count())->toBe(1);

    $record = $ledger->importRecords()->first();
    expect($record->filename)->toBe('history-test.csv');
    expect($record->row_count)->toBe(2);
    expect($record->imported_count)->toBe(2);
    expect($record->skipped_count)->toBe(0);
    expect($record->mapping_used)->toBe([
        'date' => 'date',
        'amount' => 'amount',
        'description' => 'description',
    ]);
    expect($record->imported_at)->not->toBeNull();
});

test('import record tracks skipped duplicates correctly', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    // Create an existing transaction to trigger skip
    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-01-01',
    ]);

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $path = 'imports/temp/skip-history.csv';
    Storage::disk('local')->put($path, $csv);

    $this
        ->actingAs($user)
        ->post(route('api.v1.ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => true,
        ]);

    $record = $ledger->importRecords()->first();
    expect($record->imported_count)->toBe(1);
    expect($record->skipped_count)->toBe(1);
});

test('create page renders successfully', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.import.create', $ledger));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/import/index')
        ->missing('accounts')
        ->missing('importHistory')
        ->missing('savedMappings')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('accounts')
            ->has('importHistory')
            ->has('savedMappings')
        )
    );
});
