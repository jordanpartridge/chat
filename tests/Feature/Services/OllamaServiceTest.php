<?php

use App\Services\OllamaService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('models', function () {
    it('returns available models from ollama api', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest', 'modified_at' => '2024-01-01', 'size' => 1000000],
                    ['name' => 'mistral:latest', 'modified_at' => '2024-01-01', 'size' => 2000000],
                ],
            ]),
        ]);

        $service = new OllamaService();
        $models = $service->getAvailableModels();

        expect($models)->toHaveCount(2)
            ->and($models[0]['name'])->toBe('llama3.2:latest')
            ->and($models[1]['name'])->toBe('mistral:latest');
    });

    it('returns installed model names normalized', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest'],
                    ['name' => 'phi3:latest'],
                ],
            ]),
        ]);

        $service = new OllamaService();
        $names = $service->getInstalledModelNames();

        expect($names)->toBe(['llama3.2', 'phi3']);
    });

    it('checks if model is available', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest'],
                ],
            ]),
        ]);

        $service = new OllamaService();

        expect($service->isModelAvailable('llama3.2'))->toBeTrue()
            ->and($service->isModelAvailable('llama3.2:latest'))->toBeTrue()
            ->and($service->isModelAvailable('mistral'))->toBeFalse();
    });
});

describe('error handling', function () {
    it('returns empty array when ollama is not running', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([], 500),
        ]);

        $service = new OllamaService();
        $models = $service->getAvailableModels();

        expect($models)->toBe([]);
    });

    it('handles connection timeout gracefully', function () {
        Http::fake([
            'localhost:11434/api/tags' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $service = new OllamaService();
        $models = $service->getAvailableModels();

        expect($models)->toBe([]);
    });
});
