<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyPageAnalytics;
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
        $totalUsers = User::query()->count();
        $verifiedUsers = User::query()->whereNotNull('email_verified_at')->count();

        $membershipsByTier = UserMembership::query()
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier')
            ->toArray();

        $membershipsByStatus = UserMembership::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $todayHits = DailyPageAnalytics::query()
            ->whereDate('metric_date', now())
            ->sum('hits');

        $last30DaysHits = DailyPageAnalytics::query()
            ->whereDate('metric_date', '>=', now()->subDays(30))
            ->sum('hits');

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'verified' => $verifiedUsers,
            ],
            'memberships' => [
                'by_tier' => $membershipsByTier,
                'by_status' => $membershipsByStatus,
            ],
            'analytics' => [
                'today_hits' => (int) $todayHits,
                'last_30_days_hits' => (int) $last30DaysHits,
            ],
        ]);
    }
}
