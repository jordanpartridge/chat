<?php

use App\Enums\ModelName;
use Prism\Prism\Enums\Provider;

it('has correct model values', function () {
    expect(ModelName::LLAMA32->value)->toBe('llama3.2')
        ->and(ModelName::MISTRAL->value)->toBe('mistral')
        ->and(ModelName::PHI3->value)->toBe('phi3');
});

it('returns correct display names', function () {
    expect(ModelName::LLAMA32->getName())->toBe('Llama 3.2')
        ->and(ModelName::MISTRAL->getName())->toBe('Mistral')
        ->and(ModelName::PHI3->getName())->toBe('Phi-3');
});

it('returns ollama provider for all models', function () {
    foreach (ModelName::cases() as $model) {
        expect($model->getProvider())->toBe(Provider::Ollama);
    }
});

it('converts to array with required keys', function () {
    $array = ModelName::LLAMA32->toArray();

    expect($array)->toHaveKeys(['id', 'name', 'description', 'provider'])
        ->and($array['id'])->toBe('llama3.2')
        ->and($array['provider'])->toBe('ollama');
});

it('gets all models as array', function () {
    $models = ModelName::getAllModels();

    expect($models)->toBeArray()
        ->and(count($models))->toBe(count(ModelName::cases()));
});
