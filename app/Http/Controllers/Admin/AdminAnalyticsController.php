<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyPageAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = $request->query('days', '30');
        $days = is_numeric($days) ? (int) $days : 30;
        $days = min(max($days, 1), 90);

        $startDate = now()->subDays($days)->toDateString();

        $dailyTrend = DailyPageAnalytics::query()
            ->where('metric_date', '>=', $startDate)
            ->selectRaw('metric_date, sum(hits) as total_hits')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->metric_date,
                'hits' => (int) $row->total_hits,
            ]);

        $topPages = DailyPageAnalytics::query()
            ->where('metric_date', '>=', $startDate)
            ->selectRaw('page_key, sum(hits) as total_hits')
            ->groupBy('page_key')
            ->orderByDesc('total_hits')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'page_key' => $row->page_key,
                'hits' => (int) $row->total_hits,
            ]);

        $byAudience = DailyPageAnalytics::query()
            ->where('metric_date', '>=', $startDate)
            ->selectRaw('audience, sum(hits) as total_hits')
            ->groupBy('audience')
            ->pluck('total_hits', 'audience')
            ->map(fn ($val) => (int) $val);

        return response()->json([
            'days' => $days,
            'daily_trend' => $dailyTrend,
            'top_pages' => $topPages,
            'by_audience' => $byAudience,
        ]);
    }
}
