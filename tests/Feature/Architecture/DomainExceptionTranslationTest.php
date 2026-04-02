<?php

use App\Actions\Bills\UseCases\PayBillAction;
use App\Actions\Imports\UseCases\ExecuteImportAction;
use App\Actions\Reports\Queries\GetFinancialHealthReportDataQuery;
use App\Actions\Transactions\Queries\SelectAllTransactionIdsQuery;
use App\Actions\Transactions\UseCases\StoreTransactionAction;
use App\Data\Bills\Input\PayBillData;
use App\Data\Imports\Input\StoreImportData;
use App\Data\Reports\Input\GetFinancialHealthPageData;
use App\Data\Reports\Output\Web\FinancialHealthReportData;
use App\Data\Transactions\Input\StoreTransactionData;
use App\Exceptions\Domain\DomainNotAllowed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function runWithProductionEnvironmentForDomainExceptions(callable $callback): mixed
{
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        return $callback();
    } finally {
        app()['env'] = $originalEnvironment;
    }
}

test('web mutation request maps a domain exception to redirect and flash', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('architecture.proof-tags.index', $ledger))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('architecture.proof-tags.store', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]), [
            'name' => 'travel',
            'color' => '#22c55e',
        ]);

    $response->assertRedirect(route('architecture.proof-tags.index', $ledger))
        ->assertSessionHas('error', 'Proof exception from action');
});

test('web get maps a domain exception to centralized inertia error handling', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('architecture.proof-tags.index', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 403)
        );
});

test('api request maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Proof exception from route',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('bill web mutation maps a domain exception to redirect and flash', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    app()->bind(PayBillAction::class, fn () => new class extends PayBillAction
    {
        public function __construct() {}

        public function __invoke(PayBillData $data): never
        {
            throw new class('Unsafe bill payment detail', 'Recurring transaction cannot be paid right now.') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->from(route('ledgers.bills.index', $ledger))
        ->post(route('ledgers.bills.pay', [$ledger, $bill]))
        ->assertRedirect(route('ledgers.bills.index', $ledger))
        ->assertSessionHas('error', 'Recurring transaction cannot be paid right now.');
});

test('bill api mutation maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $bill = Bill::factory()->for($ledger)->for($account)->create();

    app()->bind(PayBillAction::class, fn () => new class extends PayBillAction
    {
        public function __construct() {}

        public function __invoke(PayBillData $data): never
        {
            throw new class('Unsafe bill payment detail', 'Recurring transaction cannot be paid right now.') extends DomainNotAllowed {};
        }
    });

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.bills.pay', [$ledger, $bill]))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Recurring transaction cannot be paid right now.',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('transaction web mutation maps a domain exception to redirect and flash', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    app()->bind(StoreTransactionAction::class, fn () => new class extends StoreTransactionAction
    {
        public function __construct() {}

        public function __invoke(StoreTransactionData $data): never
        {
            throw new class('Unsafe transaction detail', 'Transaction cannot be saved right now.') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->from(route('ledgers.transactions.index', $ledger))
        ->post(route('ledgers.transactions.store', $ledger), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'amount' => 25.00,
            'transaction_date' => '2026-03-13',
        ])
        ->assertRedirect(route('ledgers.transactions.index', $ledger))
        ->assertSessionHas('error', 'Transaction cannot be saved right now.');
});

test('transaction api mutation maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = Category::factory()->for($ledger)->create();

    app()->bind(StoreTransactionAction::class, fn () => new class extends StoreTransactionAction
    {
        public function __construct() {}

        public function __invoke(StoreTransactionData $data): never
        {
            throw new class('Unsafe transaction detail', 'Transaction cannot be saved right now.') extends DomainNotAllowed {};
        }
    });

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.store', $ledger), [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_type' => 'expense',
        'amount' => 25.00,
        'transaction_date' => '2026-03-13',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Transaction cannot be saved right now.',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('transaction api select-all query maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    app()->bind(SelectAllTransactionIdsQuery::class, fn () => new class extends SelectAllTransactionIdsQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger, array $filters): never
        {
            throw new class('Unsafe transaction filter detail', 'Transaction ids cannot be selected right now.') extends DomainNotAllowed {};
        }
    });

    Sanctum::actingAs($user, ['*']);

    $this->postJson(route('api.v1.ledgers.transactions.select-all', $ledger), [])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Transaction ids cannot be selected right now.',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('import web mutation maps a domain exception to redirect and flash', function () {
    Storage::fake('local');

    expect(class_exists(ExecuteImportAction::class))->toBeTrue();
    expect(class_exists(StoreImportData::class))->toBeTrue();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $path = 'imports/temp/domain-import-web.csv';

    Storage::disk('local')->put($path, "date,amount\n2026-01-01,-25.00");

    app()->bind(ExecuteImportAction::class, fn () => new class extends ExecuteImportAction
    {
        public function __construct() {}

        public function __invoke(StoreImportData $data): never
        {
            throw new class('Unsafe import detail', 'Import cannot be executed right now.') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->withSession(["ledger-imports.{$ledger->id}.file_path" => $path])
        ->from(route('ledgers.import.create', $ledger))
        ->post(route('ledgers.import.execute', $ledger), [
            'file_path' => $path,
            'account_id' => $account->id,
            'mapping' => [
                'date' => 'date',
                'amount' => 'amount',
            ],
            'skip_duplicates' => true,
        ])
        ->assertRedirect(route('ledgers.import.create', $ledger))
        ->assertSessionHas('error', 'Import cannot be executed right now.');
});

test('import api mutation maps a domain exception to structured json 4xx', function () {
    Storage::fake('local');

    expect(class_exists(ExecuteImportAction::class))->toBeTrue();
    expect(class_exists(StoreImportData::class))->toBeTrue();
    expect(app('router')->has('api.v1.ledgers.import.parse'))->toBeTrue();
    expect(app('router')->has('api.v1.ledgers.import.execute'))->toBeTrue();

    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();

    app()->bind(ExecuteImportAction::class, fn () => new class extends ExecuteImportAction
    {
        public function __construct() {}

        public function __invoke(StoreImportData $data): never
        {
            throw new class('Unsafe import detail', 'Import cannot be executed right now.') extends DomainNotAllowed {};
        }
    });

    Sanctum::actingAs($user, ['*']);

    $parseResponse = $this
        ->withHeader('Accept', 'application/json')
        ->post(route('api.v1.ledgers.import.parse', $ledger), [
            'file' => UploadedFile::fake()->createWithContent('import.csv', "date,amount\n2026-01-01,-25.00"),
        ]);

    $parseResponse->assertSuccessful();

    $handle = $parseResponse->json('data.pending_import_handle');

    expect($handle)->toBeString()->not->toBe('');

    $this->postJson(route('api.v1.ledgers.import.execute', $ledger), [
        'pending_import_handle' => $handle,
        'account_id' => $account->id,
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
        ],
        'skip_duplicates' => true,
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Import cannot be executed right now.',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('report api read maps a domain exception to structured json 4xx', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    expect(class_exists(GetFinancialHealthReportDataQuery::class))->toBeTrue();
    expect(class_exists(GetFinancialHealthPageData::class))->toBeTrue();
    expect(class_exists(FinancialHealthReportData::class))->toBeTrue();
    expect(app('router')->has('api.v1.ledgers.reports.financial-health'))->toBeTrue();

    app()->bind(GetFinancialHealthReportDataQuery::class, fn () => new class extends GetFinancialHealthReportDataQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger, GetFinancialHealthPageData $input): FinancialHealthReportData
        {
            throw new class('Unsafe report detail', 'Financial health report is not available right now.') extends DomainNotAllowed {};
        }
    });

    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ledgers.reports.financial-health', $ledger))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Financial health report is not available right now.',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
        ]);
});

test('api domain exception rendering uses safe message stable code and optional context', function () {
    $request = Request::create('/api/_tests/domain', 'GET');

    $exception = new class('Unsafe internal detail', 'Safe client message', ['ledger_id' => 123]) extends DomainNotAllowed {};

    $response = TestResponse::fromBaseResponse(
        app(ExceptionHandler::class)->render($request, $exception),
        $request,
    );

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Safe client message',
            'code' => 'domain_not_allowed',
            'type' => 'domain_not_allowed',
            'context' => [
                'ledger_id' => 123,
            ],
        ])
        ->assertJsonMissing([
            'message' => 'Unsafe internal detail',
        ]);
});

test('existing normal 404 rendering for web still uses the inertia error page in production', function () {
    $request = Request::create('/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironmentForDomainExceptions(
        fn () => TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new NotFoundHttpException('Missing route')),
            $request,
        )
    );

    $response->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 404)
        );
});

test('existing normal 404 rendering for api still uses the default json shape in production', function () {
    $request = Request::create('/api/_tests/hardening/missing', 'GET');

    $response = runWithProductionEnvironmentForDomainExceptions(
        fn () => TestResponse::fromBaseResponse(
            app(ExceptionHandler::class)->render($request, new NotFoundHttpException('Missing route')),
            $request,
        )
    );

    $response->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('inertia partial reload request uses the centralized inertia error payload for domain exceptions', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
            'X-Inertia-Partial-Component' => 'ledgers/tags/index',
            'X-Inertia-Partial-Data' => 'tags',
        ])
        ->get(route('architecture.proof-tags.index', [
            'ledger' => $ledger,
            'throw' => 'domain',
        ]));

    $response->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonMissing(['message' => 'Proof exception from route'])
        ->assertJsonPath('component', 'error-page')
        ->assertJsonPath('props.status', 403);
});

test('non-json web mutation fallback uses the safe message', function () {
    $request = Request::create('/_tests/domain-action', 'POST', server: [
        'HTTP_REFERER' => 'http://localhost/previous',
    ]);
    $session = app(Store::class);
    $session->start();
    $request->setLaravelSession($session);

    $exception = new class('Unsafe internal detail', 'Safe client message') extends DomainNotAllowed {};

    $response = TestResponse::fromBaseResponse(
        app(ExceptionHandler::class)->render($request, $exception),
        $request,
    );

    $response->assertRedirect();

    expect($session->get('error'))->toBe('Safe client message');
    expect($session->get('error'))->not->toBe('Unsafe internal detail');
});
