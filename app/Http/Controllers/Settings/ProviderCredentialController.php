<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreProviderCredentialRequest;
use App\Http\Requests\Settings\UpdateProviderCredentialRequest;
use App\Models\UserApiCredential;
use App\Services\ModelSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProviderCredentialController extends Controller
{
    public function __construct(
        private readonly ModelSyncService $modelSyncService
    ) {}

    /**
     * Show the provider credentials settings page.
     */
    public function index(): Response
    {
        $credentials = auth()->user()->apiCredentials()
            ->select(['id', 'provider', 'is_enabled', 'last_used_at', 'created_at'])
            ->get();

        return Inertia::render('settings/Providers', [
            'credentials' => $credentials,
            'availableProviders' => $this->getAvailableProviders(),
        ]);
    }

    /**
     * Store a new provider credential.
     */
    public function store(StoreProviderCredentialRequest $request): RedirectResponse
    {
        $exists = auth()->user()->apiCredentials()
            ->where('provider', $request->validated('provider'))
            ->exists();

        if ($exists) {
            return back()->withErrors(['provider' => 'You already have a key for this provider.']);
        }

        auth()->user()->apiCredentials()->create($request->validated());

        $this->modelSyncService->forceSync();

        return back()->with('success', 'Provider added successfully.');
    }

    /**
     * Update the provider credential.
     */
    public function update(UpdateProviderCredentialRequest $request, UserApiCredential $credential): RedirectResponse
    {
        $credential->update($request->validated());

        $this->modelSyncService->forceSync();

        return back()->with('success', 'Provider updated successfully.');
    }

    /**
     * Remove the provider credential.
     */
    public function destroy(UserApiCredential $credential): RedirectResponse
    {
        if ($credential->user_id !== auth()->id()) {
            abort(403);
        }

        $credential->delete();

        $this->modelSyncService->forceSync();

        return back()->with('success', 'Provider removed successfully.');
    }

    /**
     * Toggle the provider credential enabled state.
     */
    public function toggle(UserApiCredential $credential): RedirectResponse
    {
        if ($credential->user_id !== auth()->id()) {
            abort(403);
        }

        $credential->update(['is_enabled' => ! $credential->is_enabled]);

        return back();
    }

    /**
     * Get the list of available providers.
     *
     * @return array<int, array{id: string, name: string, description: string}>
     */
    private function getAvailableProviders(): array
    {
        return [
            ['id' => 'openai', 'name' => 'OpenAI', 'description' => 'GPT-4, GPT-4o, GPT-3.5'],
            ['id' => 'anthropic', 'name' => 'Anthropic', 'description' => 'Claude 3.5, Claude 3'],
            ['id' => 'xai', 'name' => 'xAI', 'description' => 'Grok 4, Grok 3'],
            ['id' => 'gemini', 'name' => 'Google Gemini', 'description' => 'Gemini 2.0, Gemini 1.5'],
            ['id' => 'mistral', 'name' => 'Mistral', 'description' => 'Mistral Large, Mistral Small'],
            ['id' => 'groq', 'name' => 'Groq', 'description' => 'Fast inference for open models'],
        ];
    }
}
