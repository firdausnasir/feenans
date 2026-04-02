<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Dashboard\Queries\GetDashboardPageQuery;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Ledger $ledger,
        Request $request,
        GetDashboardPageQuery $getDashboardPage,
    ): Response {
        $this->authorize('view', $ledger);
        $request->session()->put('current_ledger_id', $ledger->id);

        $data = $getDashboardPage($ledger, $request->integer('offset', 0));

        return Inertia::render('ledgers/dashboard', [
            'cycle' => $data->cycle,
            'summary' => fn () => $data->summary,
            'accounts' => fn () => $data->accounts,
            'dailyTrend' => Inertia::defer($data->dailyTrend),
            'topCategories' => Inertia::defer($data->topCategories),
            'recentTransactions' => Inertia::defer($data->recentTransactions),
            'uncategorizedCount' => Inertia::defer($data->uncategorizedCount),
            'upcomingBills' => Inertia::defer($data->upcomingBills),
            'topBudgets' => Inertia::defer($data->topBudgets),
        ]);
    }
}
