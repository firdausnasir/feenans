<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    /**
     * Routes that should trigger the workspace redirect when the user has no ledgers.
     *
     * @var array<int, string>
     */
    private const PROTECTED_PATHS = [
        'dashboard',
        'notifications*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $request->is(self::PROTECTED_PATHS)
            && ! $user->ledgers()->exists()
            && ! $request->expectsJson()
        ) {
            return redirect()->route('ledgers.create');
        }

        return $next($request);
    }
}
