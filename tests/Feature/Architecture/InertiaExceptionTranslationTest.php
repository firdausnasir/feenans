<?php

use App\Actions\Bills\Queries\GetBillIndexPageQuery;
use App\Actions\Imports\Queries\GetImportPageQuery;
use App\Actions\Reports\Queries\GetSpendingReportPageQuery;
use App\Actions\Transactions\Queries\GetTransactionIndexPageQuery;
use App\Data\Bills\Output\Web\BillPageData;
use App\Data\Imports\Input\GetImportPageData;
use App\Data\Imports\Output\Web\ImportPageData;
use App\Data\Reports\Input\ReportFiltersData;
use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Data\Transactions\Output\Web\TransactionIndexPageData;
use App\Exceptions\Domain\DomainNotAllowed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Ledger;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('initial page get domain exception uses centralized inertia exception behavior', function () {
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

test('bill index get domain exception uses centralized inertia exception behavior', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    app()->bind(GetBillIndexPageQuery::class, fn () => new class extends GetBillIndexPageQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger): BillPageData
        {
            throw new class('Bill index query failed') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->get(route('ledgers.bills.index', $ledger))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 403)
        );
});

test('deferred or partial reload style request does not fall back to an ad hoc json error envelope', function () {
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

test('api proof tag domain exception renders json through centralized exception handling', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Proof exception from route',
        ]);
});

test('api proof tag domain exception denies outsider access before throwing', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $ledger = Ledger::factory()->for($owner)->create();

    $this->actingAs($outsider)
        ->getJson(route('api.v1.architecture.proof-tags.exception', $ledger))
        ->assertForbidden()
        ->assertJsonMissing([
            'message' => 'Proof exception from route',
        ]);
});

test('transaction index get domain exception uses centralized inertia exception behavior', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    app()->bind(GetTransactionIndexPageQuery::class, fn () => new class extends GetTransactionIndexPageQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger, GetTransactionIndexData $input): TransactionIndexPageData
        {
            throw new class('Transaction index query failed') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->get(route('ledgers.transactions.index', $ledger))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 403)
        );
});

test('import page partial reload uses centralized inertia exception behavior', function () {
    $user = User::factory()->create();
    $ledger = Ledger::factory()->for($user)->create();

    app()->bind(GetImportPageQuery::class, fn () => new class extends GetImportPageQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger, GetImportPageData $input): ImportPageData
        {
            throw new class('Import page query failed') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
            'X-Inertia-Partial-Component' => 'ledgers/import/index',
            'X-Inertia-Partial-Data' => 'accounts',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('ledgers.import.create', $ledger))
        ->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'error-page')
        ->assertJsonPath('props.status', 403);
});

test('report page get uses centralized inertia exception behavior', function () {
    $user = User::factory()->create();
    $user->membership()->update(['tier' => 'premium', 'status' => 'active']);
    $ledger = Ledger::factory()->for($user)->create();

    expect(class_exists(GetSpendingReportPageQuery::class))->toBeTrue();
    expect(class_exists(ReportFiltersData::class))->toBeTrue();

    app()->bind(GetSpendingReportPageQuery::class, fn () => new class extends GetSpendingReportPageQuery
    {
        public function __construct() {}

        public function __invoke(Ledger $ledger, ReportFiltersData $input): array
        {
            throw new class('Report page query failed') extends DomainNotAllowed {};
        }
    });

    $this->actingAs($user)
        ->get(route('ledgers.reports.index', $ledger))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error-page')
            ->where('status', 403)
        );
});
