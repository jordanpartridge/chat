<?php

use App\Models\Agent;
use App\Models\AiModel;

describe('factory', function () {
    it('creates a valid model', function () {
        $model = AiModel::factory()->create();

        expect($model)->toBeInstanceOf(AiModel::class);
    });

    it('creates model with specified attributes', function () {
        $model = AiModel::factory()->create([
            'name' => 'GPT-4 Turbo',
            'provider' => 'openai',
            'model_id' => 'gpt-4-turbo',
            'context_window' => 128000,
            'supports_tools' => true,
            'supports_vision' => true,
            'speed_tier' => 'medium',
            'cost_tier' => 'high',
            'enabled' => true,
        ]);

        expect($model->name)->toBe('GPT-4 Turbo')
            ->and($model->provider)->toBe('openai')
            ->and($model->model_id)->toBe('gpt-4-turbo')
            ->and($model->context_window)->toBe(128000)
            ->and($model->supports_tools)->toBeTrue()
            ->and($model->supports_vision)->toBeTrue()
            ->and($model->speed_tier)->toBe('medium')
            ->and($model->cost_tier)->toBe('high')
            ->and($model->enabled)->toBeTrue();
    });

    it('supports multiple providers', function () {
        AiModel::factory()->create(['provider' => 'ollama', 'model_id' => 'llama3:8b']);
        AiModel::factory()->create(['provider' => 'groq', 'model_id' => 'llama-3.1-70b']);
        AiModel::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-4']);
        AiModel::factory()->create(['provider' => 'anthropic', 'model_id' => 'claude-3-opus']);

        expect(AiModel::where('provider', 'ollama')->count())->toBe(1)
            ->and(AiModel::where('provider', 'groq')->count())->toBe(1)
            ->and(AiModel::where('provider', 'openai')->count())->toBe(1)
            ->and(AiModel::where('provider', 'anthropic')->count())->toBe(1);
    });
});

describe('scopes', function () {
    it('filters enabled models only', function () {
        AiModel::factory()->count(3)->create(['enabled' => true]);
        AiModel::factory()->count(2)->create(['enabled' => false]);

        expect(AiModel::enabled()->count())->toBe(3);
    });

    it('filters by provider', function () {
        AiModel::factory()->count(2)->create(['provider' => 'ollama']);
        AiModel::factory()->count(3)->create(['provider' => 'groq']);

        expect(AiModel::byProvider('ollama')->count())->toBe(2)
            ->and(AiModel::byProvider('groq')->count())->toBe(3);
    });

    it('filters models supporting tools', function () {
        AiModel::factory()->count(2)->create(['supports_tools' => true]);
        AiModel::factory()->count(3)->create(['supports_tools' => false]);

        expect(AiModel::supportsTools()->count())->toBe(2);
    });

    it('filters models supporting vision', function () {
        AiModel::factory()->count(1)->create(['supports_vision' => true]);
        AiModel::factory()->count(4)->create(['supports_vision' => false]);

        expect(AiModel::supportsVision()->count())->toBe(1);
    });

    it('filters by speed tier', function () {
        AiModel::factory()->create(['speed_tier' => 'fast']);
        AiModel::factory()->count(2)->create(['speed_tier' => 'medium']);
        AiModel::factory()->create(['speed_tier' => 'slow']);

        expect(AiModel::bySpeedTier('fast')->count())->toBe(1)
            ->and(AiModel::bySpeedTier('medium')->count())->toBe(2);
    });
});

describe('relationships', function () {
    it('belongs to many agents', function () {
        $model = AiModel::factory()->create();
        $agents = Agent::factory()->count(3)->create();

        $model->agents()->attach($agents);

        expect($model->agents)->toHaveCount(3)
            ->and($model->agents->first())->toBeInstanceOf(Agent::class);
    });
});

describe('constraints', function () {
    it('enforces unique provider and model_id combination', function () {
        AiModel::factory()->create([
            'provider' => 'openai',
            'model_id' => 'gpt-4',
        ]);

        expect(fn () => AiModel::factory()->create([
            'provider' => 'openai',
            'model_id' => 'gpt-4',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
