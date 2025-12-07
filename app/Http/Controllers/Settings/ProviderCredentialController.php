<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreProviderCredentialRequest;
use App\Http\Requests\Settings\UpdateProviderCredentialRequest;
use App\Models\UserApiCredential;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\XaiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderCredentialController extends Controller
{
    public function __construct(
        private readonly AnthropicProvider $anthropicProvider,
        private readonly XaiProvider $xaiProvider,
    ) {}

    /**
     * Show the provider credentials settings page.
     */
    public function index(Request $request): Response
    {
        $credentials = $request->user()->apiCredentials()
            ->select(['id', 'provider', 'is_enabled', 'last_used_at', 'created_at'])
            ->with(['models:id,user_api_credential_id,model_id,name,enabled'])
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
        $user = $request->user();
        $validated = $request->validated();

        $exists = $user->apiCredentials()
            ->where('provider', $validated['provider'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['provider' => 'You already have a key for this provider.']);
        }

        // Fetch full model data from provider
        $providerResult = $this->fetchModelsFromProvider($validated['provider'], $validated['api_key']);

        if (! $providerResult['valid']) {
            return back()->withErrors(['api_key' => $providerResult['error'] ?? 'Failed to validate API key.']);
        }

        // Create the credential
        $credential = $user->apiCredentials()->create([
            'provider' => $validated['provider'],
            'api_key' => $validated['api_key'],
        ]);

        // Create selected models
        $selectedModelIds = $validated['models'];
        $modelsToCreate = collect($providerResult['models'])
            ->filter(fn (array $model) => in_array($model['id'], $selectedModelIds))
            ->map(fn (array $model) => [
                'model_id' => $model['id'],
                'name' => $model['name'],
                'description' => $model['description'] ?? null,
                'enabled' => true,
                'supports_tools' => $this->modelSupportsTools($validated['provider'], $model['id']),
            ]);

        $credential->models()->createMany($modelsToCreate->all());

        return back()->with('success', 'Provider added successfully.');
    }

    /**
     * Fetch models from the provider API.
     *
     * @return array{valid: bool, models: array<int, array{id: string, name: string, description: string}>, error: string|null}
     */
    private function fetchModelsFromProvider(string $provider, string $apiKey): array
    {
        return match ($provider) {
            'anthropic' => $this->anthropicProvider->validateAndFetchModels($apiKey),
            'xai' => $this->xaiProvider->validateAndFetchModels($apiKey),
            default => ['valid' => true, 'models' => [], 'error' => null],
        };
    }

    /**
     * Update the provider credential.
     */
    public function update(UpdateProviderCredentialRequest $request, UserApiCredential $credential): RedirectResponse
    {
        $credential->update($request->validated());

        return back()->with('success', 'Provider updated successfully.');
    }

    /**
     * Remove the provider credential.
     */
    public function destroy(Request $request, UserApiCredential $credential): RedirectResponse
    {
        if ($credential->user_id !== $request->user()->id) {
            abort(403);
        }

        // Models will be cascade deleted via foreign key constraint
        $credential->delete();

        return back()->with('success', 'Provider removed successfully.');
    }

    /**
     * Toggle the provider credential enabled state.
     */
    public function toggle(Request $request, UserApiCredential $credential): RedirectResponse
    {
        if ($credential->user_id !== $request->user()->id) {
            abort(403);
        }

        $credential->update(['is_enabled' => ! $credential->is_enabled]);

        return back();
    }

    /**
     * Toggle an individual model's enabled state.
     */
    public function toggleModel(Request $request, UserApiCredential $credential, \App\Models\AiModel $model): RedirectResponse
    {
        if ($credential->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($model->user_api_credential_id !== $credential->id) {
            abort(403);
        }

        $model->update(['enabled' => ! $model->enabled]);

        return back();
    }

    /**
     * Determine if a model supports tools based on provider and model ID.
     */
    private function modelSupportsTools(string $provider, string $modelId): bool
    {
        // All current Claude models support tools
        if ($provider === 'anthropic') {
            return true;
        }

        // OpenAI GPT-4 and GPT-3.5-turbo support tools
        if ($provider === 'openai') {
            return str_contains($modelId, 'gpt-4') || str_contains($modelId, 'gpt-3.5-turbo');
        }

        // Gemini models support tools
        if ($provider === 'gemini') {
            return true;
        }

        // xAI Grok models support tools (except vision-only models)
        if ($provider === 'xai') {
            return ! str_contains($modelId, 'vision');
        }

        // Default to true for other providers
        return true;
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
