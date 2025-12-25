<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Google\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuth,
    ) {}

    /**
     * Redirect to Google OAuth.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
        ]);

        $site = Site::findOrFail($request->site_id);

        // Verify user owns this site
        if ($site->team_id !== $request->user()->team_id) {
            abort(403);
        }

        // Store site_id in session for callback
        $state = encrypt([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
        ]);

        $url = $this->googleAuth->getAuthorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            Log::warning('Google OAuth error', [
                'error' => $request->error,
                'description' => $request->error_description,
            ]);

            return redirect()->route('sites.index')
                ->with('error', 'Google connection failed: ' . $request->error_description);
        }

        if (!$request->has('code') || !$request->has('state')) {
            return redirect()->route('sites.index')
                ->with('error', 'Invalid OAuth response');
        }

        try {
            // Decrypt state
            $state = decrypt($request->state);
            $site = Site::findOrFail($state['site_id']);

            // Exchange code for tokens
            $tokens = $this->googleAuth->exchangeCode($request->code);

            // Save tokens to site (both GSC and GA4 use the same OAuth token)
            $this->googleAuth->saveTokensToSite($site, $tokens);

            // Also save to GA4 fields (same token works for both APIs)
            // Reset property IDs to force re-selection (in case user changed Google account)
            $site->update([
                'ga4_token' => $tokens->accessToken,
                'ga4_refresh_token' => $tokens->refreshToken ?? $site->ga4_refresh_token,
                'ga4_token_expires_at' => $tokens->expiresAt
                    ? now()->setTimestamp($tokens->expiresAt)
                    : null,
                'gsc_property_id' => null,
                'ga4_property_id' => null,
            ]);

            Log::info('Google connected successfully', [
                'site_id' => $site->id,
                'user_id' => $state['user_id'],
            ]);

            // Always redirect to onboarding wizard for property selection
            return redirect()->route('onboarding.resume', $site)
                ->with('success', 'Google connecté avec succès ! Sélectionnez vos propriétés.');
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            // Redirect back to wizard on error if onboarding not completed
            if (isset($state) && isset($site) && !$site->onboarding_completed_at) {
                return redirect()->route('onboarding.resume', $site)
                    ->with('error', 'Échec de connexion Google: ' . $e->getMessage());
            }

            return redirect()->route('sites.index')
                ->with('error', 'Failed to connect Google: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Google from a site.
     */
    public function disconnect(Request $request, Site $site): RedirectResponse
    {
        // Verify user owns this site
        if ($site->team_id !== $request->user()->team_id) {
            abort(403);
        }

        // Revoke token if we have one
        if ($site->gsc_token) {
            try {
                $this->googleAuth->revokeToken($site->gsc_token);
            } catch (\Exception $e) {
                // Ignore revocation errors
            }
        }

        // Clear tokens
        $site->update([
            'gsc_token' => null,
            'gsc_refresh_token' => null,
            'gsc_token_expires_at' => null,
        ]);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Google disconnected successfully');
    }
}
