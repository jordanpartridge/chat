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

    /**
     * Create a commit as the GitHub App with optional co-author.
     *
     * @param  array<string, string>  $files  Array of path => content
     * @return array<string, mixed>
     */
    public function createCommit(
        int $installationId,
        string $repo,
        string $branch,
        string $message,
        array $files,
        ?string $coAuthor = null
    ): array {
        // Add co-author to message if provided
        if ($coAuthor) {
            $message .= "\n\nCo-Authored-By: {$coAuthor}";
        }

        // Get the current commit SHA for the branch
        $ref = $this->apiRequest($installationId, 'GET', "/repos/{$repo}/git/ref/heads/{$branch}");
        if (isset($ref['error'])) {
            return $ref;
        }
        $baseSha = $ref['object']['sha'];

        // Get the base tree
        $baseCommit = $this->apiRequest($installationId, 'GET', "/repos/{$repo}/git/commits/{$baseSha}");
        if (isset($baseCommit['error'])) {
            return $baseCommit;
        }
        $baseTreeSha = $baseCommit['tree']['sha'];

        // Create blobs for each file
        $treeItems = [];
        foreach ($files as $path => $content) {
            $blob = $this->apiRequest($installationId, 'POST', "/repos/{$repo}/git/blobs", [
                'content' => $content,
                'encoding' => 'utf-8',
            ]);
            if (isset($blob['error'])) {
                return $blob;
            }

            $treeItems[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $blob['sha'],
            ];
        }

        // Create new tree
        $tree = $this->apiRequest($installationId, 'POST', "/repos/{$repo}/git/trees", [
            'base_tree' => $baseTreeSha,
            'tree' => $treeItems,
        ]);
        if (isset($tree['error'])) {
            return $tree;
        }

        // Create commit
        $commit = $this->apiRequest($installationId, 'POST', "/repos/{$repo}/git/commits", [
            'message' => $message,
            'tree' => $tree['sha'],
            'parents' => [$baseSha],
        ]);
        if (isset($commit['error'])) {
            return $commit;
        }

        // Update branch reference
        $result = $this->apiRequest($installationId, 'PATCH', "/repos/{$repo}/git/refs/heads/{$branch}", [
            'sha' => $commit['sha'],
        ]);

        return [
            'success' => true,
            'commit_sha' => $commit['sha'],
            'commit_url' => $commit['html_url'] ?? "https://github.com/{$repo}/commit/{$commit['sha']}",
            'message' => $message,
        ];
    }

    /**
     * Create a new branch from a base branch.
     *
     * @return array<string, mixed>
     */
    public function createBranch(int $installationId, string $repo, string $newBranch, string $baseBranch = 'master'): array
    {
        // Get base branch SHA
        $ref = $this->apiRequest($installationId, 'GET', "/repos/{$repo}/git/ref/heads/{$baseBranch}");
        if (isset($ref['error'])) {
            return $ref;
        }

        // Create new branch
        return $this->apiRequest($installationId, 'POST', "/repos/{$repo}/git/refs", [
            'ref' => "refs/heads/{$newBranch}",
            'sha' => $ref['object']['sha'],
        ]);
    }

    /**
     * Create a pull request as the GitHub App.
     *
     * @return array<string, mixed>
     */
    public function createPullRequest(
        int $installationId,
        string $repo,
        string $head,
        string $base,
        string $title,
        string $body
    ): array {
        return $this->apiRequest($installationId, 'POST', "/repos/{$repo}/pulls", [
            'title' => $title,
            'body' => $body,
            'head' => $head,
            'base' => $base,
        ]);
    }

    /**
     * Full workflow: create branch, commit files, create PR as the app.
     *
     * @param  array<string, string>  $files
     * @return array<string, mixed>
     */
    public function createPullRequestWithChanges(
        int $installationId,
        string $repo,
        string $branchName,
        string $baseBranch,
        string $commitMessage,
        array $files,
        string $prTitle,
        string $prBody,
        ?string $coAuthor = null
    ): array {
        // Create branch
        $branch = $this->createBranch($installationId, $repo, $branchName, $baseBranch);
        if (isset($branch['error'])) {
            return ['error' => 'Failed to create branch', 'details' => $branch];
        }

        // Create commit
        $commit = $this->createCommit($installationId, $repo, $branchName, $commitMessage, $files, $coAuthor);
        if (isset($commit['error'])) {
            return ['error' => 'Failed to create commit', 'details' => $commit];
        }

        // Create PR
        $pr = $this->createPullRequest($installationId, $repo, $branchName, $baseBranch, $prTitle, $prBody);
        if (isset($pr['error'])) {
            return ['error' => 'Failed to create PR', 'details' => $pr];
        }

        return [
            'success' => true,
            'branch' => $branchName,
            'commit' => $commit,
            'pr_number' => $pr['number'],
            'pr_url' => $pr['html_url'],
        ];
    }
}
