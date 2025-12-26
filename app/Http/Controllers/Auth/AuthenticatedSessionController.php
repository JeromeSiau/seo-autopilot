<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Sync cookie preferences to user if they don't have preferences set
        $user = Auth::user();
        $needsSave = false;

        if (!$user->locale && $locale = $request->cookie('locale')) {
            $user->locale = $locale;
            $needsSave = true;
        }

        if (!$user->theme && $theme = $request->cookie('theme')) {
            $user->theme = $theme;
            $needsSave = true;
        }

        if ($needsSave) {
            $user->save();
        }

        // Check for pending invitation
        if ($token = session('pending_invitation')) {
            session()->forget('pending_invitation');
            return redirect()->route('invitations.accept', $token);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
