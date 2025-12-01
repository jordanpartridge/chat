<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434'
    ) {}

    /**
     * Get list of available models from Ollama.
     *
     * @return array<int, array{name: string, modified_at: string, size: int}>
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            if ($response->successful()) {
                return $response->json('models', []);
            }

            Log::warning('Ollama API returned non-successful response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('Failed to connect to Ollama API', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get model names that are currently installed.
     *
     * @return array<int, string>
     */
    public function getInstalledModelNames(): array
    {
        $models = $this->getAvailableModels();

        return array_map(
            fn (array $model): string => $this->normalizeModelName($model['name'] ?? ''),
            $models
        );
    }

    /**
     * Check if a specific model is available.
     */
    public function isModelAvailable(string $modelName): bool
    {
        $installedModels = $this->getInstalledModelNames();

        return in_array($this->normalizeModelName($modelName), $installedModels, true);
    }

    /**
     * Normalize model name by removing version tags (e.g., "llama3.2:latest" -> "llama3.2").
     */
    private function normalizeModelName(string $name): string
    {
        return explode(':', $name)[0];
    }
}
