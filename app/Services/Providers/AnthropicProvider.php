<?php

declare(strict_types=1);

namespace App\Services\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider
{
    private const BASE_URL = 'https://api.anthropic.com/v1';

    private const API_VERSION = '2023-06-01';

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
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                ])->get(self::BASE_URL.'/models');

            if ($response->status() === 401) {
                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'Invalid API key',
                ];
            }

            if ($response->status() === 403) {
                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'API key does not have permission to list models',
                ];
            }

            if (! $response->successful()) {
                Log::warning('Anthropic API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'valid' => false,
                    'models' => [],
                    'error' => 'Failed to connect to Anthropic API',
                ];
            }

            $data = $response->json();
            $models = collect($data['data'] ?? [])
                ->map(fn (array $model) => [
                    'id' => $model['id'],
                    'name' => $model['display_name'] ?? $this->formatModelName($model['id']),
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
            Log::warning('Anthropic connection failed', ['error' => $e->getMessage()]);

            return [
                'valid' => false,
                'models' => [],
                'error' => 'Could not connect to Anthropic API',
            ];
        }
    }

    /**
     * Format model ID into readable name.
     */
    private function formatModelName(string $modelId): string
    {
        // claude-sonnet-4-20250514 -> Claude Sonnet 4
        $name = str($modelId)
            ->replace('-', ' ')
            ->title()
            ->replaceMatches('/\d{8}$/', '') // Remove date suffix
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
            str_contains($modelId, 'opus') => 'Most capable, complex tasks',
            str_contains($modelId, 'sonnet') => 'Balanced performance and speed',
            str_contains($modelId, 'haiku') => 'Fast and lightweight',
            default => 'Claude model',
        };
    }
}
