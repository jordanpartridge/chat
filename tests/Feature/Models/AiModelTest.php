<?php

use App\Models\Agent;
use App\Models\AiModel;
use App\Models\User;
use App\Models\UserApiCredential;

describe('factory', function () {
    it('creates a valid model', function () {
        $model = AiModel::factory()->create();

        expect($model)->toBeInstanceOf(AiModel::class);
    });

    it('creates model with specified attributes', function () {
        $credential = UserApiCredential::factory()->create(['provider' => 'openai']);

        $model = AiModel::factory()->forCredential($credential)->create([
            'name' => 'GPT-4 Turbo',
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

    it('supports multiple providers via credentials', function () {
        $user = User::factory()->create();

        $ollamaCredential = UserApiCredential::factory()->for($user)->create(['provider' => 'ollama']);
        $groqCredential = UserApiCredential::factory()->for($user)->create(['provider' => 'groq']);
        $openaiCredential = UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);
        $anthropicCredential = UserApiCredential::factory()->for($user)->create(['provider' => 'anthropic']);

        AiModel::factory()->forCredential($ollamaCredential)->create(['model_id' => 'llama3:8b']);
        AiModel::factory()->forCredential($groqCredential)->create(['model_id' => 'llama-3.1-70b']);
        AiModel::factory()->forCredential($openaiCredential)->create(['model_id' => 'gpt-4']);
        AiModel::factory()->forCredential($anthropicCredential)->create(['model_id' => 'claude-3-opus']);

        expect(AiModel::count())->toBe(4);
    });
});

describe('scopes', function () {
    it('filters enabled models only', function () {
        AiModel::factory()->count(3)->create(['enabled' => true]);
        AiModel::factory()->count(2)->create(['enabled' => false]);

        expect(AiModel::enabled()->count())->toBe(3);
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
    it('belongs to a credential', function () {
        $credential = UserApiCredential::factory()->create(['provider' => 'anthropic']);
        $model = AiModel::factory()->forCredential($credential)->create();

        expect($model->credential)->toBeInstanceOf(UserApiCredential::class)
            ->and($model->credential->id)->toBe($credential->id);
    });

    it('belongs to many agents', function () {
        $model = AiModel::factory()->create();
        $agents = Agent::factory()->count(3)->create();

        $model->agents()->attach($agents);

        expect($model->agents)->toHaveCount(3)
            ->and($model->agents->first())->toBeInstanceOf(Agent::class);
    });

    it('gets provider from credential', function () {
        $credential = UserApiCredential::factory()->create(['provider' => 'groq']);
        $model = AiModel::factory()->forCredential($credential)->create();

        expect($model->provider)->toBe('groq');
    });
});

describe('constraints', function () {
    it('enforces unique credential and model_id combination', function () {
        $credential = UserApiCredential::factory()->create();

        AiModel::factory()->forCredential($credential)->create([
            'model_id' => 'gpt-4',
        ]);

        expect(fn () => AiModel::factory()->forCredential($credential)->create([
            'model_id' => 'gpt-4',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
