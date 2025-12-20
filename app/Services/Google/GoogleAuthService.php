<?php

namespace App\Services\Google;

use App\Models\Site;
use App\Services\Google\DTOs\GoogleTokens;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    // Scopes needed for Search Console and GA4
    private const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id') ?? '';
        $this->clientSecret = config('services.google.client_secret') ?? '';
        $this->redirectUri = config('services.google.redirect') ?? '';
    }

    /**
     * Generate the OAuth authorization URL.
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent', // Force consent to get refresh token
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCode(string $code): GoogleTokens
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            Log::error('Google OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to exchange authorization code: ' . $response->body());
        }

        return GoogleTokens::fromOAuthResponse($response->json());
    }

    /**
     * Refresh an access token using a refresh token.
     */
    public function refreshToken(string $refreshToken): GoogleTokens
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('Google OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to refresh token: ' . $response->body());
        }

        $data = $response->json();

        // Refresh token is not returned on refresh, keep the old one
        $data['refresh_token'] = $refreshToken;

        return GoogleTokens::fromOAuthResponse($data);
    }

    /**
     * Get valid tokens for a site, refreshing if necessary.
     */
    public function getValidTokensForSite(Site $site): ?GoogleTokens
    {
        if (!$site->gsc_token) {
            return null;
        }

        $tokens = GoogleTokens::fromArray([
            'access_token' => $site->gsc_token,
            'refresh_token' => $site->gsc_refresh_token,
            'expires_at' => $site->gsc_token_expires_at?->timestamp,
        ]);

        // Refresh if expired
        if ($tokens->isExpired() && $tokens->refreshToken) {
            try {
                $tokens = $this->refreshToken($tokens->refreshToken);
                $this->saveTokensToSite($site, $tokens);
            } catch (\Exception $e) {
                Log::warning('Failed to refresh Google tokens for site', [
                    'site_id' => $site->id,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return $tokens;
    }

    /**
     * Save tokens to a site.
     */
    public function saveTokensToSite(Site $site, GoogleTokens $tokens): void
    {
        $site->update([
            'gsc_token' => $tokens->accessToken,
            'gsc_refresh_token' => $tokens->refreshToken ?? $site->gsc_refresh_token,
            'gsc_token_expires_at' => $tokens->expiresAt
                ? now()->setTimestamp($tokens->expiresAt)
                : null,
        ]);
    }

    /**
     * Revoke tokens (disconnect).
     */
    public function revokeToken(string $token): bool
    {
        $response = Http::post('https://oauth2.googleapis.com/revoke', [
            'token' => $token,
        ]);

        return $response->successful();
    }

    /**
     * Get user info from access token.
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get user info');
        }

        return $response->json();
    }
}
