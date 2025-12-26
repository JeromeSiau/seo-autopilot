<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    protected array $exemptRoutes = [
        'settings.billing',
        'billing.*',
        'logout',
        'stripe.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->currentTeam) {
            return $next($request);
        }

        $team = $user->currentTeam;

        // Allow if trial is active (not expired)
        if ($team->is_trial && !$team->isTrialExpired()) {
            return $next($request);
        }

        // Allow if has a billing plan (subscribed)
        if ($team->billingPlan) {
            return $next($request);
        }

        // Trial expired, no subscription - freeze account

        // Allow GET requests (read-only access)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Allow billing routes
        foreach ($this->exemptRoutes as $route) {
            if ($request->routeIs($route)) {
                return $next($request);
            }
        }

        // Block write actions - redirect to billing
        return redirect()->route('settings.billing')
            ->with('warning', 'Votre période d\'essai est terminée. Choisissez un plan pour continuer.');
    }
}
