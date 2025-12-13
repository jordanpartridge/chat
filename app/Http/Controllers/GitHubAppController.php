<?php

namespace App\Http\Controllers;

use App\Services\GitHubAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GitHubAppController extends Controller
{
    public function __construct(
        private GitHubAppService $gitHubApp
    ) {}

    /**
     * Show GitHub App status and setup page.
     */
    public function index(): RedirectResponse
    {
        // TODO: Create GitHub/Index.vue page
        return redirect()->route('dashboard')
            ->with('success', 'GitHub App connected!');
    }

    /**
     * Start device flow authentication.
     * Returns a user code to enter at github.com/login/device
     */
    public function device(): JsonResponse
    {
        $deviceCode = $this->gitHubApp->startDeviceFlow();

        return response()->json([
            'device_code' => $deviceCode['device_code'],
            'user_code' => $deviceCode['user_code'],
            'verification_uri' => $deviceCode['verification_uri'],
            'expires_in' => $deviceCode['expires_in'],
            'interval' => $deviceCode['interval'],
        ]);
    }

    /**
     * Poll for device flow completion.
     */
    public function poll(Request $request): JsonResponse
    {
        $request->validate([
            'device_code' => 'required|string',
        ]);

        $result = $this->gitHubApp->pollDeviceFlow($request->device_code);

        return response()->json($result);
    }

    /**
     * OAuth callback (for web flow, if needed later).
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $installationId = $request->query('installation_id');
        $setupAction = $request->query('setup_action');

        if ($installationId) {
            $this->gitHubApp->handleInstallation($installationId);
        }

        if ($code) {
            $this->gitHubApp->exchangeCodeForToken($code);
        }

        return redirect()->route('github.index')
            ->with('success', 'GitHub App configured successfully!');
    }

    /**
     * Webhook endpoint (placeholder - needs tunnel for real use).
     */
    public function webhook(Request $request): JsonResponse
    {
        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        // Log for now, process later when we have tunnel
        logger()->info('GitHub webhook received', [
            'event' => $event,
            'action' => $payload['action'] ?? null,
        ]);

        return response()->json(['status' => 'received']);
    }

    /**
     * Test making a commit as the app.
     */
    public function testCommit(Request $request): JsonResponse
    {
        $request->validate([
            'installation_id' => 'required|integer',
            'repo' => 'required|string',
        ]);

        $result = $this->gitHubApp->testCommitAsApp(
            $request->installation_id,
            $request->repo
        );

        return response()->json($result);
    }

    /**
     * Get installation token for API calls.
     */
    public function installationToken(Request $request): JsonResponse
    {
        $request->validate([
            'installation_id' => 'required|integer',
        ]);

        $token = $this->gitHubApp->getInstallationToken($request->installation_id);

        return response()->json([
            'token' => $token['token'],
            'expires_at' => $token['expires_at'],
        ]);
    }
}
