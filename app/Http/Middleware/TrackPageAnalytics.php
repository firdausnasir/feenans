<?php

namespace App\Http\Middleware;

use App\Models\DailyPageAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageAnalytics
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            $this->recordHit($request);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            return false;
        }

        $route = $request->route();
        if ($route === null) {
            return false;
        }

        $routeName = $route->getName();
        if ($routeName === null) {
            return false;
        }

        if (str_starts_with($routeName, 'admin.')) {
            return false;
        }

        if ($request->is('api/*')) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        return true;
    }

    private function recordHit(Request $request): void
    {
        $routeName = $request->route()?->getName();
        if ($routeName === null) {
            return;
        }

        $user = $request->user();
        $audience = $user ? 'authenticated' : 'guest';
        $membershipTier = 'none';

        if ($user) {
            $membershipTier = $user->membership?->tier ?? 'none';
        }

        $today = now()->toDateString();

        $affected = DailyPageAnalytics::query()
            ->whereDate('metric_date', $today)
            ->where('page_key', $routeName)
            ->where('audience', $audience)
            ->where('membership_tier', $membershipTier)
            ->increment('hits');

        if ($affected === 0) {
            DailyPageAnalytics::query()->create([
                'metric_date' => $today,
                'page_key' => $routeName,
                'audience' => $audience,
                'membership_tier' => $membershipTier,
                'hits' => 1,
            ]);
        }
    }
}
