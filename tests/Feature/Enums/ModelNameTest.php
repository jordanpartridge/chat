<?php

declare(strict_types=1);

use App\Enums\ModelName;
use Prism\Prism\Enums\Provider;

describe('ModelName', function (): void {
    it('has expected cases', function (): void {
        expect(ModelName::cases())->toHaveCount(9)
            // Ollama models
            ->and(ModelName::LLAMA32->value)->toBe('llama3.2')
            ->and(ModelName::LLAMA31->value)->toBe('llama3.1')
            ->and(ModelName::MISTRAL->value)->toBe('mistral')
            ->and(ModelName::CODELLAMA->value)->toBe('codellama')
            ->and(ModelName::PHI3->value)->toBe('phi3')
            ->and(ModelName::QWEN25->value)->toBe('qwen2.5')
            // Groq models
            ->and(ModelName::GROQ_LLAMA33_70B->value)->toBe('llama-3.3-70b-versatile')
            ->and(ModelName::GROQ_LLAMA31_8B->value)->toBe('llama-3.1-8b-instant')
            ->and(ModelName::GROQ_LLAMA4_SCOUT->value)->toBe('meta-llama/llama-4-scout-17b-16e-instruct');
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
        'groq_llama33_70b' => [ModelName::GROQ_LLAMA33_70B, 'Llama 3.3 70B (Groq)'],
        'groq_llama31_8b' => [ModelName::GROQ_LLAMA31_8B, 'Llama 3.1 8B (Groq)'],
        'groq_llama4_scout' => [ModelName::GROQ_LLAMA4_SCOUT, 'Llama 4 Scout (Groq)'],
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
        'groq_llama33_70b' => [ModelName::GROQ_LLAMA33_70B, 'Latest Llama 3.3, excellent reasoning'],
        'groq_llama31_8b' => [ModelName::GROQ_LLAMA31_8B, 'Ultra-fast cloud inference, good for quick tasks'],
        'groq_llama4_scout' => [ModelName::GROQ_LLAMA4_SCOUT, "Meta's newest Llama 4, multimodal capable"],
    ]);

    it('returns Ollama provider for local models', function (ModelName $model): void {
        expect($model->getProvider())->toBe(Provider::Ollama);
    })->with([
        ModelName::LLAMA32,
        ModelName::LLAMA31,
        ModelName::MISTRAL,
        ModelName::CODELLAMA,
        ModelName::PHI3,
        ModelName::QWEN25,
    ]);

    it('returns Groq provider for cloud models', function (ModelName $model): void {
        expect($model->getProvider())->toBe(Provider::Groq);
    })->with([
        ModelName::GROQ_LLAMA33_70B,
        ModelName::GROQ_LLAMA31_8B,
        ModelName::GROQ_LLAMA4_SCOUT,
    ]);

    it('supports tools only for Groq models', function (): void {
        // Ollama models do not support tools
        expect(ModelName::LLAMA32->supportsTools())->toBeFalse()
            ->and(ModelName::LLAMA31->supportsTools())->toBeFalse()
            ->and(ModelName::MISTRAL->supportsTools())->toBeFalse()
            ->and(ModelName::CODELLAMA->supportsTools())->toBeFalse()
            ->and(ModelName::PHI3->supportsTools())->toBeFalse()
            ->and(ModelName::QWEN25->supportsTools())->toBeFalse();

        // Groq models support tools
        expect(ModelName::GROQ_LLAMA33_70B->supportsTools())->toBeTrue()
            ->and(ModelName::GROQ_LLAMA31_8B->supportsTools())->toBeTrue()
            ->and(ModelName::GROQ_LLAMA4_SCOUT->supportsTools())->toBeTrue();
    });

    it('converts to array with correct structure', function (ModelName $model): void {
        $array = $model->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKeys(['id', 'name', 'description', 'provider', 'supportsTools'])
            ->and($array['id'])->toBe($model->value)
            ->and($array['name'])->toBe($model->getName())
            ->and($array['description'])->toBe($model->getDescription())
            ->and($array['provider'])->toBe($model->getProvider()->value)
            ->and($array['supportsTools'])->toBe($model->supportsTools());
    })->with(ModelName::cases());

    it('returns all defined models via getAllModels', function (): void {
        $models = ModelName::getAllModels();

        expect($models)
            ->toBeArray()
            ->toHaveCount(9)
            ->each->toHaveKeys(['id', 'name', 'description', 'provider', 'supportsTools']);
    });

    it('returns only installed Ollama models via getAvailableModels', function (): void {
        $mockOllama = Mockery::mock(\App\Services\OllamaService::class);
        $mockOllama->shouldReceive('getInstalledModelNames')
            ->once()
            ->andReturn(['llama3.2', 'mistral']);

        // No Groq API key configured
        config(['prism.providers.groq.api_key' => null]);

        $models = ModelName::getAvailableModels($mockOllama);

        expect($models)
            ->toBeArray()
            ->toHaveCount(2);

        $modelIds = array_column($models, 'id');
        expect($modelIds)->toBe(['llama3.2', 'mistral']);
    });

    it('includes Groq models when API key is configured', function (): void {
        $mockOllama = Mockery::mock(\App\Services\OllamaService::class);
        $mockOllama->shouldReceive('getInstalledModelNames')
            ->once()
            ->andReturn(['llama3.2']);

        // Groq API key is configured
        config(['prism.providers.groq.api_key' => 'test-api-key']);

        $models = ModelName::getAvailableModels($mockOllama);

        expect($models)
            ->toBeArray()
            ->toHaveCount(4); // 1 Ollama + 3 Groq

        $modelIds = array_column($models, 'id');
        expect($modelIds)->toContain('llama3.2')
            ->and($modelIds)->toContain('llama-3.3-70b-versatile')
            ->and($modelIds)->toContain('llama-3.1-8b-instant')
            ->and($modelIds)->toContain('meta-llama/llama-4-scout-17b-16e-instruct');
    });
});
