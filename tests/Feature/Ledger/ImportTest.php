<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('import page renders for authenticated user', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create(['name' => 'Checking']);

    $ledger->importMappings()->create([
        'name' => 'My Mapping',
        'mapping' => [
            'date' => 'Date',
            'amount' => 'Amount',
        ],
    ]);

    $ledger->importRecords()->create([
        'filename' => 'statement.csv',
        'row_count' => 12,
        'imported_count' => 10,
        'skipped_count' => 2,
        'mapping_used' => ['date' => 'Date', 'amount' => 'Amount'],
        'imported_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.import.create', $ledger));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ledgers/import/index')
        ->where('currentLedger.id', $ledger->id)
        ->missing('accounts')
        ->missing('savedMappings')
        ->missing('importHistory')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('accounts', 1, fn (Assert $accountPage) => $accountPage
                ->where('name', $account->name)
                ->etc()
            )
            ->has('savedMappings', 1, fn (Assert $mappingPage) => $mappingPage
                ->where('name', 'My Mapping')
                ->etc()
            )
            ->has('importHistory', 1, fn (Assert $historyPage) => $historyPage
                ->where('filename', 'statement.csv')
                ->where('row_count', 12)
                ->etc()
            )
        )
    );
});

test('import page is inaccessible to unauthenticated users', function () {
    $ledger = Ledger::factory()->create();

    $response = $this->get(route('ledgers.import.create', $ledger));

    $response->assertRedirect(route('login'));
});

test('import page is inaccessible to users who do not own the ledger', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ledger = Ledger::factory()->for($otherUser)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('ledgers.import.create', $ledger));

    $response->assertForbidden();
});

test('parse endpoint returns headers and preview rows from valid CSV', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));
    $data = session('importParseResult');
    expect($data['headers'])->toBe(['date', 'amount', 'description']);
    expect($data['preview_rows'])->toHaveCount(2);
    expect($data['total_rows'])->toBe(2);
    expect($data['file_path'])->toStartWith('imports/temp/');
});

test('parse can be completed through web routes', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('importParseResult', function (array $result): bool {
            expect($result['headers'])->toBe(['date', 'amount', 'description']);
            expect($result['preview_rows'])->toHaveCount(2);
            expect($result['total_rows'])->toBe(2);
            expect($result['file_path'])->toStartWith('imports/temp/');

            return true;
        });
});

test('parse endpoint returns correct row count for larger CSV', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $rows = ['date,amount,description'];
    for ($i = 1; $i <= 15; $i++) {
        $rows[] = "2026-01-{$i},-10.00,Row {$i}";
    }
    $csv = implode("\n", $rows);
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));
    $data = session('importParseResult');
    expect($data['total_rows'])->toBe(15);
    expect($data['preview_rows'])->toHaveCount(10);
});

test('parse endpoint rejects non-csv files', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasErrors('file');
});

test('store imports transactions from mapped CSV', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $path = 'imports/temp/test.csv';
    Storage::disk('local')->put($path, $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => true,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));
    expect($ledger->transactions()->count())->toBe(2);

    $coffee = $ledger->transactions()->where('description', 'Coffee')->first();
    expect($coffee)->not->toBeNull();
    expect((float) $coffee->amount)->toBe(-25.0);
    expect($coffee->transaction_type)->toBe(TransactionType::Expense);
    expect($coffee->transaction_date->toDateString())->toBe('2026-01-01');
});

test('store can be completed through web routes', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $path = 'imports/temp/web-test.csv';
    Storage::disk('local')->put($path, $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => true,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Imported 2 transactions');

    expect($ledger->transactions()->count())->toBe(2);
    Storage::disk('local')->assertMissing($path);
});

test('store imports income transactions when amount is positive', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $csv = "date,amount,description\n2026-01-01,500.00,Salary";
    $path = 'imports/temp/income.csv';
    Storage::disk('local')->put($path, $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
            ],
            'skip_duplicates' => true,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));
    $transaction = $ledger->transactions()->first();
    expect($transaction->transaction_type)->toBe(TransactionType::Income);
    expect((float) $transaction->amount)->toBe(500.0);
});

test('store skips duplicate transactions when skip_duplicates is true', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    // Create an existing transaction
    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-01-01',
    ]);

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee\n2026-01-02,-30.00,Lunch";
    $path = 'imports/temp/dupes.csv';
    Storage::disk('local')->put($path, $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => true,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));

    // Should only add 1 new transaction (Lunch), not duplicate Coffee
    expect($ledger->transactions()->count())->toBe(2);
});

test('store imports all rows including duplicates when skip_duplicates is false', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    Transaction::factory()->for($ledger)->for($account)->create([
        'transaction_type' => TransactionType::Expense,
        'amount' => '-25.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-01-01',
    ]);

    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee";
    $path = 'imports/temp/no-skip.csv';
    Storage::disk('local')->put($path, $csv);

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
            ],
            'skip_duplicates' => false,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));
    expect($ledger->transactions()->count())->toBe(2);
});

test('store creates payees from CSV when payee column is mapped', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $csv = "date,amount,description,payee\n2026-01-01,-25.00,Coffee,Starbucks";
    $path = 'imports/temp/payees.csv';
    Storage::disk('local')->put($path, $csv);

    $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
                'description' => 'description',
                'payee' => 'payee',
            ],
            'skip_duplicates' => true,
        ]);

    $transaction = $ledger->transactions()->with('payee')->first();
    expect($transaction->payee)->not->toBeNull();
    expect($transaction->payee->name)->toBe('Starbucks');
});

test('store returns error when import file is not found', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    $response = $this
        ->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => 'imports/temp/nonexistent.csv',
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
            ],
            'skip_duplicates' => true,
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasErrors('file_path');
});

test('store rejects account ids from another ledger', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $foreignLedger = Ledger::factory()->create();
    $foreignAccountType = AccountType::factory()->for($foreignLedger)->create();
    $foreignAccount = Account::factory()->for($foreignLedger)->for($foreignAccountType)->create();

    $path = 'imports/temp/foreign-account.csv';
    Storage::disk('local')->put($path, "date,amount\n2026-01-01,-25.00");

    $response = $this->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $foreignAccount->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
            ],
        ]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasErrors('account_id');
    expect($ledger->transactions()->count())->toBe(0);
});

test('parse returns a validation error when uploaded file cannot be read', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $file = UploadedFile::fake()->createWithContent('import.csv', "date,amount\n2026-01-01,-25.00");
    $path = $file->getRealPath();

    if ($path !== false) {
        @unlink($path);
    }

    $response = $this->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHasErrors('file');
});

test('import temp files use the configured ledger storage disk', function () {
    config()->set('filesystems.ledger_disk', 's3');
    Storage::fake('s3');

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $csv = "date,amount,description\n2026-01-01,-25.00,Coffee";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this->actingAs($user)
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.parse', $ledger), ['file' => $file]);

    $response->assertRedirect(route('ledgers.import.create', $ledger));

    Storage::disk('s3')->assertExists(session('importParseResult.file_path'));
});
