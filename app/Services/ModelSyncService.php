<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelSyncService
{
    private const CACHE_KEY = 'ai_models_synced';

    private const CACHE_TTL = 60; // seconds

    public function __construct(
        private readonly OllamaService $ollamaService
    ) {}

    /**
     * Sync all provider models and return available models.
     * Uses caching to avoid hammering provider APIs.
     *
     * @return Collection<int, AiModel>
     */
    public function syncAndGetAvailable(): Collection
    {
        $this->syncAll();

        return AiModel::query()
            ->where('enabled', true)
            ->where('is_available', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();
    }

    /**
     * Sync availability for all providers.
     * Cached for CACHE_TTL seconds to avoid excessive API calls.
     */
    public function syncAll(): void
    {
        if (Cache::has(self::CACHE_KEY)) {
            return;
        }

        $this->syncOllama();
        $this->syncGroq();
        $this->syncOpenAI();
        $this->syncAnthropic();
        $this->syncXAI();
        $this->syncGemini();
        $this->syncMistral();

        Cache::put(self::CACHE_KEY, true, self::CACHE_TTL);
    }

    /**
     * Force sync without checking cache.
     */
    public function forceSync(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->syncAll();
    }

    /**
     * Sync Ollama models by querying the local API.
     */
    public function syncOllama(): void
    {
        try {
            $installedModels = $this->ollamaService->getInstalledModelNames();

            // Mark all Ollama models as unavailable first
            AiModel::query()
                ->where('provider', 'ollama')
                ->update(['is_available' => false]);

            // Then mark installed ones as available
            if (count($installedModels) > 0) {
                AiModel::query()
                    ->where('provider', 'ollama')
                    ->whereIn('model_id', $installedModels)
                    ->update(['is_available' => true]);

                Log::debug('Ollama models synced', ['installed' => $installedModels]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync Ollama models', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync Groq models by checking if API key is configured.
     */
    public function syncGroq(): void
    {
        $apiKey = config('prism.providers.groq.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'groq')
            ->update(['is_available' => $isAvailable]);

        Log::debug('Groq models synced', ['available' => $isAvailable]);
    }

    /**
     * Sync OpenAI models by checking if API key is configured.
     */
    public function syncOpenAI(): void
    {
        $apiKey = config('prism.providers.openai.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'openai')
            ->update(['is_available' => $isAvailable]);

        Log::debug('OpenAI models synced', ['available' => $isAvailable]);
    }

    /**
     * Sync Anthropic models by checking if API key is configured.
     */
    public function syncAnthropic(): void
    {
        $apiKey = config('prism.providers.anthropic.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'anthropic')
            ->update(['is_available' => $isAvailable]);

        Log::debug('Anthropic models synced', ['available' => $isAvailable]);
    }

    /**
     * Sync xAI models by checking if API key is configured.
     */
    public function syncXAI(): void
    {
        $apiKey = config('prism.providers.xai.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'xai')
            ->update(['is_available' => $isAvailable]);

        Log::debug('xAI models synced', ['available' => $isAvailable]);
    }

    /**
     * Sync Gemini models by checking if API key is configured.
     */
    public function syncGemini(): void
    {
        $apiKey = config('prism.providers.gemini.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'gemini')
            ->update(['is_available' => $isAvailable]);

        Log::debug('Gemini models synced', ['available' => $isAvailable]);
    }

    /**
     * Sync Mistral models by checking if API key is configured.
     */
    public function syncMistral(): void
    {
        $apiKey = config('prism.providers.mistral.api_key');
        $isAvailable = ! empty($apiKey);

        AiModel::query()
            ->where('provider', 'mistral')
            ->update(['is_available' => $isAvailable]);

        Log::debug('Mistral models synced', ['available' => $isAvailable]);
    }

    /**
     * Get the default model (first available, or first enabled).
     */
    public function getDefaultModel(): ?AiModel
    {
        return AiModel::query()
            ->where('enabled', true)
            ->where('is_available', true)
            ->orderByDesc('supports_tools') // Prefer models with tool support
            ->orderBy('speed_tier')
            ->first()
            ?? AiModel::query()
                ->where('enabled', true)
                ->first();
    }

    /**
     * Find a model by its model_id string.
     */
    public function findByModelId(string $modelId): ?AiModel
    {
        return AiModel::query()
            ->where('model_id', $modelId)
            ->first();
    }
}
