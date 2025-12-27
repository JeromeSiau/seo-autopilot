<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasTeam
{
    /**
     * Handle an incoming request.
     *
     * Redirect to team creation if user has no current team.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->currentTeam) {
            return redirect()->route('teams.create');
        }

        return $next($request);
    }
}
