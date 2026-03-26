<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $now = now();

        $totalUsers = User::query()->count();
        $verifiedUsers = User::query()->whereNotNull('email_verified_at')->count();
        $newToday = User::query()->whereDate('created_at', $now)->count();
        $newThisWeek = User::query()->where('created_at', '>=', $now->copy()->startOfWeek())->count();
        $activeLast7d = User::query()->where('updated_at', '>=', $now->copy()->subDays(7))->count();

        $membershipsByTier = UserMembership::query()
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier')
            ->toArray();

        $totalLedgers = Ledger::query()->count();

        $txCreatedToday = Transaction::query()->whereDate('created_at', $now)->count();
        $txCreatedThisWeek = Transaction::query()->where('created_at', '>=', $now->copy()->startOfWeek())->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'verified' => $verifiedUsers,
                'new_today' => $newToday,
                'new_this_week' => $newThisWeek,
                'active_last_7d' => $activeLast7d,
            ],
            'memberships' => [
                'by_tier' => $membershipsByTier,
            ],
            'ledgers' => [
                'total' => $totalLedgers,
            ],
            'transactions' => [
                'created_today' => $txCreatedToday,
                'created_this_week' => $txCreatedThisWeek,
            ],
        ]);
    }
}
