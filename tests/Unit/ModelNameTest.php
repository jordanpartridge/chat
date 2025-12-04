<?php

use App\Enums\ModelName;
use Prism\Prism\Enums\Provider;

it('has correct model values', function () {
    expect(ModelName::LLAMA32->value)->toBe('llama3.2')
        ->and(ModelName::MISTRAL->value)->toBe('mistral')
        ->and(ModelName::PHI3->value)->toBe('phi3')
        ->and(ModelName::GROQ_LLAMA31_70B->value)->toBe('llama-3.1-70b-versatile');
});

it('returns correct display names', function () {
    expect(ModelName::LLAMA32->getName())->toBe('Llama 3.2')
        ->and(ModelName::MISTRAL->getName())->toBe('Mistral')
        ->and(ModelName::PHI3->getName())->toBe('Phi-3')
        ->and(ModelName::GROQ_LLAMA31_70B->getName())->toBe('Llama 3.1 70B (Groq)');
});

it('returns correct provider for each model type', function () {
    // Ollama models
    expect(ModelName::LLAMA32->getProvider())->toBe(Provider::Ollama)
        ->and(ModelName::MISTRAL->getProvider())->toBe(Provider::Ollama);

    // Groq models
    expect(ModelName::GROQ_LLAMA31_70B->getProvider())->toBe(Provider::Groq)
        ->and(ModelName::GROQ_MIXTRAL->getProvider())->toBe(Provider::Groq);
});

it('converts to array with required keys', function () {
    $array = ModelName::LLAMA32->toArray();

    expect($array)->toHaveKeys(['id', 'name', 'description', 'provider', 'supportsTools'])
        ->and($array['id'])->toBe('llama3.2')
        ->and($array['provider'])->toBe('ollama')
        ->and($array['supportsTools'])->toBeFalse();

    $groqArray = ModelName::GROQ_LLAMA31_70B->toArray();

    expect($groqArray['provider'])->toBe('groq')
        ->and($groqArray['supportsTools'])->toBeTrue();
});

it('gets all models as array', function () {
    $models = ModelName::getAllModels();

    expect($models)->toBeArray()
        ->and(count($models))->toBe(count(ModelName::cases()));
});
