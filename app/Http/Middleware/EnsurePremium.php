<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremium
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPremium()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('premium');
        }

        return $next($request);
    }
}
