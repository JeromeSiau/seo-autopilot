<?php

namespace App\Http\Middleware;

use App\Models\Article;
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
                    'current_team_id' => $user->current_team_id,
                    'current_team' => $user->currentTeam ? [
                        'id' => $user->currentTeam->id,
                        'name' => $user->currentTeam->name,
                        'articles_limit' => $user->currentTeam->articles_limit,
                        'articles_generated_count' => $user->currentTeam->articles_generated_count,
                        'plan' => $user->currentTeam->plan,
                    ] : null,
                    'teams' => $user->teams->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'role' => $team->id === $user->currentTeam?->owner_id
                            ? 'owner'
                            : $team->pivot->role,
                    ]),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => $locale,
            'theme' => $this->resolveTheme($request),
            'availableLocales' => ['en', 'fr', 'es'],
            'translations' => [
                'app' => $this->loadTranslations($locale, 'app'),
            ],
            'generatingArticles' => $this->getGeneratingArticles($user),
        ];
    }

    /**
     * Resolve the current theme preference.
     */
    private function resolveTheme(Request $request): string
    {
        if ($user = $request->user()) {
            return $user->theme ?? 'system';
        }

        return $request->cookie('theme', 'system');
    }

    /**
     * Get articles currently being generated for the user's team.
     */
    private function getGeneratingArticles($user): array
    {
        if (!$user || !$user->currentTeam) {
            return [];
        }

        return Article::whereHas('site', function ($query) use ($user) {
            $query->where('team_id', $user->currentTeam->id);
        })
            ->where('status', 'generating')
            ->select('id', 'title', 'site_id')
            ->with('site:id,name,domain')
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'site_name' => $article->site?->name,
            ])
            ->toArray();
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
