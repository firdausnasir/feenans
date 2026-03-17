<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Ledger $ledger, Request $request): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/index');
    }

    public function create(Ledger $ledger): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/create');
    }

    public function store(StoreAccountRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('view', $ledger);

        $ledger->accounts()->create($request->validated());

        return to_route('ledgers.accounts.index', $ledger);
    }

    public function show(Request $request, Ledger $ledger, Account $account): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/show', [
            'accountId' => $account->id,
        ]);
    }

    public function export(Request $request, Ledger $ledger, Account $account): StreamedResponse
    {
        $this->authorize('view', $ledger);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = $account->transactions()
            ->with(['category', 'payee'])
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $filename = 'account-'.$account->name.'-transactions.csv';

        return response()->streamDownload(function () use ($query, $account, $dateFrom) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Description', 'Type', 'Category', 'Payee', 'Amount', 'Running Balance', 'Notes']);

            // Calculate running balance: start from initial_balance + sum of all transactions before date range
            $runningBalance = (float) $account->initial_balance;

            if ($dateFrom) {
                $priorSum = (float) $account->transactions()
                    ->where('transaction_date', '<', $dateFrom)
                    ->sum('amount');
                $runningBalance += $priorSum;
            }

            $query->chunk(500, function ($transactions) use ($handle, &$runningBalance) {
                foreach ($transactions as $t) {
                    $amount = (float) $t->amount;
                    $runningBalance += $amount;

                    fputcsv($handle, [
                        $t->transaction_date->toDateString(),
                        $t->description ?? '',
                        $t->transaction_type instanceof TransactionType ? $t->transaction_type->value : $t->transaction_type,
                        $t->category?->name ?? '',
                        $t->payee?->name ?? '',
                        number_format($amount, 2, '.', ''),
                        number_format($runningBalance, 2, '.', ''),
                        $t->notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function edit(Request $request, Ledger $ledger, Account $account): Response
    {
        $this->authorize('view', $ledger);

        return Inertia::render('ledgers/accounts/edit', [
            'accountId' => $account->id,
        ]);
    }

    public function update(UpdateAccountRequest $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->update($request->validated());

        return to_route('ledgers.accounts.show', [$ledger, $account]);
    }

    public function destroy(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('delete', $ledger);

        $account->delete();

        return to_route('ledgers.accounts.index', $ledger);
    }

    public function toggleVisibility(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $account->update(['is_hidden' => ! $account->is_hidden]);

        return back();
    }

    public function adjustBalance(Request $request, Ledger $ledger, Account $account): RedirectResponse
    {
        $this->authorize('update', $ledger);

        $validated = $request->validate([
            'new_balance' => ['required', 'numeric'],
        ]);

        $currentBalance = (float) $account->initial_balance + (float) $account->transactions()->sum('amount');
        $newBalance = (float) $validated['new_balance'];
        $difference = round($newBalance - $currentBalance, 2);

        if ($difference == 0) {
            return back();
        }

        $transactionType = $difference > 0 ? TransactionType::Income : TransactionType::Expense;

        $ledger->transactions()->create([
            'account_id' => $account->id,
            'transaction_type' => $transactionType,
            'amount' => $difference,
            'description' => 'Balance adjustment',
            'transaction_date' => CarbonImmutable::today()->toDateString(),
        ]);

        return back();
    }

    public function reorder(ReorderRequest $request, Ledger $ledger): RedirectResponse
    {
        $this->authorize('update', $ledger);

        foreach ($request->items as $item) {
            $ledger->accounts()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back();
    }
}
