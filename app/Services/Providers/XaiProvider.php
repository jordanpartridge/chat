<?php

declare(strict_types=1);

namespace App\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XaiProvider
{
    private const BASE_URL = 'https://api.x.ai/v1';

    /**
     * Validate an API key and fetch available models.
     *
     * @return array{valid: bool, models: array<int, array{id: string, name: string, description: string}>, error: string|null}
     */
    public function validateAndFetchModels(string $apiKey): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])->get(self::BASE_URL.'/models');

            if ($response->status() === 401) {
                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'Invalid API key',
                ];
            }

            if ($response->status() === 403) {
                // xAI API keys may not have model listing permission
                // Validate by making a simple completion request instead
                return $this->validateWithCompletionRequest($apiKey);
            }

            if (! $response->successful()) {
                Log::warning('xAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'Failed to connect to xAI API',
                ];
            }

            $data = $response->json();
            $models = collect($data['data'] ?? [])
                ->filter(fn (array $model) => $this->isChatModel($model['id']))
                ->map(fn (array $model) => [
                    'id' => $model['id'],
                    'name' => $this->formatModelName($model['id']),
                    'description' => $this->getModelDescription($model['id']),
                ])
                ->values()
                ->all();

            return [
                'valid' => true,
                'models' => $models,
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            Log::warning('xAI connection failed', ['error' => $e->getMessage()]);

            return [
                'valid' => false,
                'models' => [],
                'error' => 'Could not connect to xAI API',
            ];
        }
    }

    /**
     * Check if a model is a chat model (exclude embedding, image generation models).
     */
    private function isChatModel(string $modelId): bool
    {
        // Exclude image generation and embedding models
        if (str_contains($modelId, 'image') || str_contains($modelId, 'embed')) {
            return false;
        }

        return true;
    }

    /**
     * Format model ID into readable name.
     */
    private function formatModelName(string $modelId): string
    {
        // grok-4-0709 -> Grok 4
        // grok-3-mini -> Grok 3 Mini
        $name = str($modelId)
            ->replace('-', ' ')
            ->title()
            ->replaceMatches('/\d{4}$/', '') // Remove date suffix like 0709
            ->replaceMatches('/\d{4}$/', '') // Remove date suffix like 1212
            ->trim()
            ->toString();

        return $name;
    }

    /**
     * Get description based on model family.
     */
    private function getModelDescription(string $modelId): string
    {
        return match (true) {
            str_contains($modelId, 'grok-4') && str_contains($modelId, 'reasoning') => 'Advanced reasoning capabilities',
            str_contains($modelId, 'grok-4') => 'Most capable Grok model',
            str_contains($modelId, 'grok-3-mini') => 'Fast and efficient',
            str_contains($modelId, 'grok-3') => 'Balanced performance',
            str_contains($modelId, 'vision') => 'Vision capabilities',
            str_contains($modelId, 'code') => 'Optimized for coding',
            default => 'Grok model',
        };
    }

    /**
     * Validate API key by making a minimal completion request and return known models.
     *
     * @return array{valid: bool, models: array<int, array{id: string, name: string, description: string}>, error: string|null}
     */
    private function validateWithCompletionRequest(string $apiKey): array
    {
        try {
            // Make a minimal request to validate the key
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::BASE_URL.'/chat/completions', [
                    'model' => 'grok-3-mini',
                    'messages' => [['role' => 'user', 'content' => 'hi']],
                    'max_tokens' => 1,
                ]);

            if ($response->status() === 401) {
                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'Invalid API key',
                ];
            }

            if (! $response->successful() && $response->status() !== 200) {
                // Check if it's a rate limit or other temporary error
                if ($response->status() === 429) {
                    // Key is valid but rate limited - still return models
                } else {
                    Log::warning('xAI validation request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [
                        'valid' => false,
                        'models' => [],
                        'error' => 'Failed to validate API key',
                    ];
                }
            }

            // Key is valid - return known models
            return [
                'valid' => true,
                'models' => $this->getKnownModels(),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            Log::warning('xAI validation connection failed', ['error' => $e->getMessage()]);

            return [
                'valid' => false,
                'models' => [],
                'error' => 'Could not connect to xAI API',
            ];
        }
    }

    /**
     * Get the list of known xAI models.
     *
     * @return array<int, array{id: string, name: string, description: string}>
     */
    private function getKnownModels(): array
    {
        return [
            ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
            ['id' => 'grok-3-mini', 'name' => 'Grok 3 Mini', 'description' => 'Fast and efficient'],
            ['id' => 'grok-3-mini-fast', 'name' => 'Grok 3 Mini Fast', 'description' => 'Fastest responses'],
            ['id' => 'grok-2-1212', 'name' => 'Grok 2', 'description' => 'Previous generation'],
        ];
    }
}
