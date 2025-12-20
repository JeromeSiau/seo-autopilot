<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'current_team' => $user->currentTeam ? [
                        'id' => $user->currentTeam->id,
                        'name' => $user->currentTeam->name,
                        'articles_limit' => $user->currentTeam->articles_limit,
                        'articles_generated_count' => $user->currentTeam->articles_generated_count,
                        'plan' => $user->currentTeam->plan,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => $locale,
            'translations' => [
                'app' => $this->loadTranslations($locale, 'app'),
            ],
        ];
    }

    /**
     * Load translations from JSON file.
     */
    private function loadTranslations(string $locale, string $file): array
    {
        $path = lang_path("{$locale}/{$file}.json");

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }

        // Fallback to English
        $fallbackPath = lang_path("en/{$file}.json");

        return file_exists($fallbackPath)
            ? json_decode(file_get_contents($fallbackPath), true) ?? []
            : [];
    }
}
