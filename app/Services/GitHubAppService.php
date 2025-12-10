<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GitHubAppService
{
    private string $appId;

    private string $clientId;

    private string $clientSecret;

    private ?string $privateKey;

    private string $baseUrl = 'https://api.github.com';

    public function __construct()
    {
        $this->appId = config('services.github.app_id', '');
        $this->clientId = config('services.github.client_id', '');
        $this->clientSecret = config('services.github.client_secret', '');
        $this->privateKey = $this->loadPrivateKey();
    }

    private function loadPrivateKey(): ?string
    {
        // Try direct key first
        $key = config('services.github.private_key');
        if ($key) {
            return $key;
        }

        // Try file path
        $path = config('services.github.private_key_path');
        if ($path && file_exists(base_path($path))) {
            return file_get_contents(base_path($path));
        }

        return null;
    }

    /**
     * Check if the GitHub App is configured.
     */
    public function isInstalled(): bool
    {
        return ! empty($this->appId) && ! empty($this->privateKey);
    }

    /**
     * Get all installations of this app.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInstallations(): array
    {
        if (! $this->isInstalled()) {
            return [];
        }

        $jwt = $this->generateJwt();

        $response = Http::withToken($jwt, 'Bearer')
            ->accept('application/vnd.github+json')
            ->get("{$this->baseUrl}/app/installations");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * Start device flow for user authentication.
     *
     * @return array<string, mixed>
     */
    public function startDeviceFlow(): array
    {
        $response = Http::asForm()
            ->accept('application/json')
            ->post('https://github.com/login/device/code', [
                'client_id' => $this->clientId,
                'scope' => 'repo read:org',
            ]);

        return $response->json();
    }

    /**
     * Poll for device flow completion.
     *
     * @return array<string, mixed>
     */
    public function pollDeviceFlow(string $deviceCode): array
    {
        $response = Http::asForm()
            ->accept('application/json')
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ]);

        return $response->json();
    }

    /**
     * Exchange authorization code for access token.
     *
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()
            ->accept('application/json')
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
            ]);

        $data = $response->json();

        if (isset($data['access_token'])) {
            Cache::put('github_user_token', $data['access_token'], now()->addSeconds($data['expires_in'] ?? 28800));
        }

        return $data;
    }

    /**
     * Handle new installation.
     */
    public function handleInstallation(int $installationId): void
    {
        Cache::put('github_installation_id', $installationId);
    }

    /**
     * Get installation access token.
     *
     * @return array<string, mixed>
     */
    public function getInstallationToken(int $installationId): array
    {
        $cacheKey = "github_installation_token_{$installationId}";

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($installationId) {
            $jwt = $this->generateJwt();

            $response = Http::withToken($jwt, 'Bearer')
                ->accept('application/vnd.github+json')
                ->post("{$this->baseUrl}/app/installations/{$installationId}/access_tokens");

            return $response->json();
        });
    }

    /**
     * Test making a commit as the GitHub App.
     *
     * @return array<string, mixed>
     */
    public function testCommitAsApp(int $installationId, string $repo): array
    {
        $token = $this->getInstallationToken($installationId);

        if (! isset($token['token'])) {
            return ['error' => 'Failed to get installation token'];
        }

        // Get the default branch
        $repoResponse = Http::withToken($token['token'])
            ->accept('application/vnd.github+json')
            ->get("{$this->baseUrl}/repos/{$repo}");

        if (! $repoResponse->successful()) {
            return ['error' => 'Failed to get repo info'];
        }

        $defaultBranch = $repoResponse->json('default_branch');

        // Get current commit SHA
        $refResponse = Http::withToken($token['token'])
            ->accept('application/vnd.github+json')
            ->get("{$this->baseUrl}/repos/{$repo}/git/ref/heads/{$defaultBranch}");

        if (! $refResponse->successful()) {
            return ['error' => 'Failed to get ref'];
        }

        return [
            'success' => true,
            'repo' => $repo,
            'branch' => $defaultBranch,
            'sha' => $refResponse->json('object.sha'),
            'token_expires' => $token['expires_at'],
            'message' => 'Ready to commit as the-shit-agents app!',
        ];
    }

    /**
     * Generate JWT for GitHub App authentication.
     */
    private function generateJwt(): string
    {
        $now = time();

        $payload = [
            'iat' => $now - 60,
            'exp' => $now + (10 * 60),
            'iss' => $this->appId,
        ];

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }

    /**
     * Make an authenticated API request as the app.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apiRequest(int $installationId, string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getInstallationToken($installationId);

        if (! isset($token['token'])) {
            return ['error' => 'Failed to get installation token'];
        }

        $request = Http::withToken($token['token'])
            ->accept('application/vnd.github+json');

        $response = match (strtoupper($method)) {
            'GET' => $request->get("{$this->baseUrl}{$endpoint}"),
            'POST' => $request->post("{$this->baseUrl}{$endpoint}", $data),
            'PUT' => $request->put("{$this->baseUrl}{$endpoint}", $data),
            'PATCH' => $request->patch("{$this->baseUrl}{$endpoint}", $data),
            'DELETE' => $request->delete("{$this->baseUrl}{$endpoint}"),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        return $response->json() ?? [];
    }
}
