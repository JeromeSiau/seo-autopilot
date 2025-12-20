<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LandingController extends Controller
{
    protected array $supportedLocales = ['en', 'fr', 'es'];
    protected string $defaultLocale = 'en';

    public function index(Request $request, ?string $locale = null)
    {
        // Determine locale
        $locale = $this->resolveLocale($request, $locale);

        // Set application locale
        App::setLocale($locale);

        // Load translations
        $translations = $this->loadTranslations($locale);

        return Inertia::render('Landing', [
            'locale' => $locale,
            'translations' => $translations,
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    }

    protected function resolveLocale(Request $request, ?string $locale): string
    {
        // If locale is provided and supported, use it
        if ($locale && in_array($locale, $this->supportedLocales)) {
            return $locale;
        }

        // Try to detect from browser
        $browserLocale = $request->getPreferredLanguage($this->supportedLocales);

        if ($browserLocale && in_array($browserLocale, $this->supportedLocales)) {
            return $browserLocale;
        }

        return $this->defaultLocale;
    }

    protected function loadTranslations(string $locale): array
    {
        $path = resource_path("lang/{$locale}/landing.json");

        if (File::exists($path)) {
            return json_decode(File::get($path), true);
        }

        // Fallback to English
        $fallbackPath = resource_path("lang/en/landing.json");

        if (File::exists($fallbackPath)) {
            return json_decode(File::get($fallbackPath), true);
        }

        return [];
    }

    public function redirect(Request $request)
    {
        $locale = $this->resolveLocale($request, null);
        return redirect("/{$locale}");
    }
}
