<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        if ($user = $request->user()) {
            if ($user->locale) {
                return $user->locale;
            }
        }

        if ($cookie = $request->cookie('locale')) {
            return $cookie;
        }

        $browserLocale = $request->getPreferredLanguage(['en', 'fr', 'es']);

        return $browserLocale ?: 'en';
    }
}
