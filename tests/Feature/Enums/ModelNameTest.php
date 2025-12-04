<?php

declare(strict_types=1);

use App\Enums\ModelName;
use Prism\Prism\Enums\Provider;

describe('ModelName', function (): void {
    it('has expected cases', function (): void {
        expect(ModelName::cases())->toHaveCount(6)
            ->and(ModelName::LLAMA32->value)->toBe('llama3.2')
            ->and(ModelName::LLAMA31->value)->toBe('llama3.1')
            ->and(ModelName::MISTRAL->value)->toBe('mistral')
            ->and(ModelName::CODELLAMA->value)->toBe('codellama')
            ->and(ModelName::PHI3->value)->toBe('phi3')
            ->and(ModelName::QWEN25->value)->toBe('qwen2.5');
    });

    it('returns human-readable names', function (ModelName $model, string $expectedName): void {
        expect($model->getName())->toBe($expectedName);
    })->with([
        'llama32' => [ModelName::LLAMA32, 'Llama 3.2'],
        'llama31' => [ModelName::LLAMA31, 'Llama 3.1'],
        'mistral' => [ModelName::MISTRAL, 'Mistral'],
        'codellama' => [ModelName::CODELLAMA, 'Code Llama'],
        'phi3' => [ModelName::PHI3, 'Phi-3'],
        'qwen25' => [ModelName::QWEN25, 'Qwen 2.5'],
    ]);

    it('returns descriptions', function (ModelName $model, string $expectedDescription): void {
        expect($model->getDescription())->toBe($expectedDescription);
    })->with([
        'llama32' => [ModelName::LLAMA32, 'Latest Llama model, great for general tasks'],
        'llama31' => [ModelName::LLAMA31, 'Powerful Llama model with extended context'],
        'mistral' => [ModelName::MISTRAL, 'Fast and efficient for most tasks'],
        'codellama' => [ModelName::CODELLAMA, 'Specialized for code generation'],
        'phi3' => [ModelName::PHI3, "Microsoft's compact but capable model"],
        'qwen25' => [ModelName::QWEN25, 'Alibaba model, good tool calling support'],
    ]);

    it('returns Ollama as provider for all models', function (ModelName $model): void {
        expect($model->getProvider())->toBe(Provider::Ollama);
    })->with(ModelName::cases());

    it('converts to array with correct structure', function (ModelName $model): void {
        $array = $model->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKeys(['id', 'name', 'description', 'provider'])
            ->and($array['id'])->toBe($model->value)
            ->and($array['name'])->toBe($model->getName())
            ->and($array['description'])->toBe($model->getDescription())
            ->and($array['provider'])->toBe('ollama');
    })->with(ModelName::cases());

    it('returns all defined models via getAllModels', function (): void {
        $models = ModelName::getAllModels();

        expect($models)
            ->toBeArray()
            ->toHaveCount(6)
            ->each->toHaveKeys(['id', 'name', 'description', 'provider']);
    });

    it('returns only installed models via getAvailableModels', function (): void {
        $mockOllama = Mockery::mock(\App\Services\OllamaService::class);
        $mockOllama->shouldReceive('getInstalledModelNames')
            ->once()
            ->andReturn(['llama3.2', 'mistral']);

        $models = ModelName::getAvailableModels($mockOllama);

        expect($models)
            ->toBeArray()
            ->toHaveCount(2)
            ->each->toHaveKeys(['id', 'name', 'description', 'provider']);

        // Verify only the installed models are returned
        $modelIds = array_column($models, 'id');
        expect($modelIds)->toBe(['llama3.2', 'mistral']);
    });
});
