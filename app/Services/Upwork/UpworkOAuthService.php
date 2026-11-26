<?php

namespace App\Services\Upwork;

use App\Enums\PlatformIntegrationProvider;
use App\Models\User;
use App\Services\Integrations\PlatformIntegrationService;
use App\Services\Upwork\Client\UpworkAuthenticationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Upwork OAuth2 authorization-code flow. App credentials (client id/secret)
 * come from the environment; the account's tokens are stored encrypted in
 * platform_integrations and never leave the backend.
 */
class UpworkOAuthService
{
    private const REFRESH_MARGIN_SECONDS = 300;

    public function __construct(private PlatformIntegrationService $integrations) {}

    public function usesFixtures(): bool
    {
        return config('upwork.driver') === 'fake';
    }

    public function appConfigured(): bool
    {
        return filled(config('upwork.client_id')) && filled(config('upwork.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('upwork.redirect_uri') ?: route('admin.upwork.oauth.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return config('upwork.authorize_url').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('upwork.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * Exchanges the consent code for tokens and stores them.
     */
    public function completeAuthorization(string $code, User $user): void
    {
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        $this->integrations->storeUpworkTokens($tokens, $user, $this->accountName($tokens['access_token']));
    }

    /**
     * Fixture mode has no consent screen: it records a clearly labelled
     * connection so the pipeline can be exercised end to end.
     */
    public function connectFixtures(User $user): void
    {
        $this->integrations->storeUpworkTokens(
            ['access_token' => 'fixture', 'refresh_token' => null, 'expires_at' => null],
            $user,
            'Fixture data (no Upwork API calls)',
        );
    }

    /**
     * A valid access token, refreshed when it is about to expire.
     */
    public function accessToken(): string
    {
        $integration = $this->integrations->get(PlatformIntegrationProvider::Upwork);

        if (! $integration->isConnected()) {
            throw new UpworkAuthenticationException('Upwork is not connected.');
        }

        $credentials = $integration->credentials ?? [];
        $expiresAt = $credentials['expires_at'] ?? null;

        if ($expiresAt !== null && $expiresAt - self::REFRESH_MARGIN_SECONDS <= now()->getTimestamp()) {
            if (blank($credentials['refresh_token'] ?? null)) {
                throw new UpworkAuthenticationException('The Upwork token expired. Reconnect Upwork.');
            }

            try {
                $tokens = $this->tokenRequest([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $credentials['refresh_token'],
                ]);
            } catch (UpworkAuthenticationException $exception) {
                $this->integrations->markError(PlatformIntegrationProvider::Upwork, $exception->getMessage());

                throw $exception;
            }

            $this->integrations->storeUpworkTokens($tokens, refresh: true);

            return $tokens['access_token'];
        }

        return (string) $credentials['access_token'];
    }

    /**
     * @param  array<string, string>  $params
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?int}
     */
    private function tokenRequest(array $params): array
    {
        if (! $this->appConfigured()) {
            throw new UpworkAuthenticationException('UPWORK_CLIENT_ID and UPWORK_CLIENT_SECRET are not set.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('upwork.timeout', 30))
                ->post(config('upwork.token_url'), [
                    ...$params,
                    'client_id' => config('upwork.client_id'),
                    'client_secret' => config('upwork.client_secret'),
                ]);
        } catch (ConnectionException) {
            throw new UpworkAuthenticationException('Could not reach Upwork to authorise the connection.');
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new UpworkAuthenticationException("Upwork did not issue a token (HTTP {$response->status()}).");
        }

        $expiresIn = $response->json('expires_in');

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => is_numeric($expiresIn) ? now()->addSeconds((int) $expiresIn)->getTimestamp() : null,
        ];
    }

    /**
     * Best effort: the connection works without a display name.
     */
    private function accountName(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post(config('upwork.graphql_url'), ['query' => 'query { user { name } }']);

            return $response->json('data.user.name');
        } catch (Throwable) {
            return null;
        }
    }
}
