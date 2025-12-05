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
