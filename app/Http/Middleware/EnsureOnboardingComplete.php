<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
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
            && $user->onboarding_step !== null
            && ! $request->is('onboarding*')
            && ! $request->is('admin*')
            && ! $request->is('logout')
            && ! $request->expectsJson()
        ) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
