<?php

use App\Models\AiModel;
use App\Services\ModelSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

describe('syncAndGetAvailable', function () {
    it('syncs models and returns available enabled models', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        config(['prism.providers.groq.api_key' => 'test-key']);

        $groqModel = AiModel::factory()->create([
            'provider' => 'groq',
            'enabled' => true,
            'is_available' => false,
        ]);

        $service = app(ModelSyncService::class);
        $models = $service->syncAndGetAvailable();

        expect($models)->toHaveCount(1);
        expect($groqModel->fresh()->is_available)->toBeTrue();
    });

    it('returns models ordered by provider and name', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => [['name' => 'llama3.2:latest']]]),
        ]);

        config(['prism.providers.groq.api_key' => 'test-key']);

        AiModel::factory()->create([
            'name' => 'Zebra Model',
            'provider' => 'groq',
            'enabled' => true,
            'is_available' => true,
        ]);
        AiModel::factory()->create([
            'name' => 'Alpha Model',
            'provider' => 'groq',
            'enabled' => true,
            'is_available' => true,
        ]);

        $service = app(ModelSyncService::class);
        $models = $service->syncAndGetAvailable();

        expect($models->first()->name)->toBe('Alpha Model');
    });
});

describe('syncAll', function () {
    it('uses cache to avoid repeated API calls', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        $service = app(ModelSyncService::class);

        $service->syncAll();
        $service->syncAll(); // Second call should be cached

        Http::assertSentCount(1);
    });

    it('syncs both Ollama and Groq providers', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        config(['prism.providers.groq.api_key' => 'test-key']);

        $ollamaModel = AiModel::factory()->create([
            'provider' => 'ollama',
            'is_available' => true,
        ]);
        $groqModel = AiModel::factory()->create([
            'provider' => 'groq',
            'is_available' => false,
        ]);

        $service = app(ModelSyncService::class);
        $service->syncAll();

        // Ollama model should be unavailable (not in API response)
        expect($ollamaModel->fresh()->is_available)->toBeFalse();
        // Groq model should be available (API key configured)
        expect($groqModel->fresh()->is_available)->toBeTrue();
    });
});

describe('forceSync', function () {
    it('clears cache and syncs again', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        $service = app(ModelSyncService::class);

        $service->syncAll();
        $service->forceSync(); // Should clear cache and sync again

        Http::assertSentCount(2);
    });
});

describe('syncOllama', function () {
    it('marks installed models as available', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest'],
                    ['name' => 'mistral:latest'],
                ],
            ]),
        ]);

        $llama = AiModel::factory()->create([
            'provider' => 'ollama',
            'model_id' => 'llama3.2',
            'is_available' => false,
        ]);
        $mistral = AiModel::factory()->create([
            'provider' => 'ollama',
            'model_id' => 'mistral',
            'is_available' => false,
        ]);
        $phi = AiModel::factory()->create([
            'provider' => 'ollama',
            'model_id' => 'phi3',
            'is_available' => true, // Should become unavailable
        ]);

        $service = app(ModelSyncService::class);
        $service->syncOllama();

        expect($llama->fresh()->is_available)->toBeTrue();
        expect($mistral->fresh()->is_available)->toBeTrue();
        expect($phi->fresh()->is_available)->toBeFalse();
    });

    it('marks all Ollama models unavailable when none installed', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        $model = AiModel::factory()->create([
            'provider' => 'ollama',
            'is_available' => true,
        ]);

        $service = app(ModelSyncService::class);
        $service->syncOllama();

        expect($model->fresh()->is_available)->toBeFalse();
    });

    it('handles Ollama API errors gracefully', function () {
        Http::fake([
            'localhost:11434/api/tags' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to sync Ollama models', \Mockery::type('array'));

        Log::shouldReceive('debug')->andReturnNull();

        $model = AiModel::factory()->create([
            'provider' => 'ollama',
            'is_available' => true,
        ]);

        $service = app(ModelSyncService::class);
        $service->syncOllama();

        // Model should retain its previous state on error
        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('logs debug message when models are synced', function () {
        Http::fake([
            'localhost:11434/api/tags' => Http::response([
                'models' => [['name' => 'llama3.2:latest']],
            ]),
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Ollama models synced', ['installed' => ['llama3.2']]);

        AiModel::factory()->create([
            'provider' => 'ollama',
            'model_id' => 'llama3.2',
        ]);

        $service = app(ModelSyncService::class);
        $service->syncOllama();
    });
});

describe('syncGroq', function () {
    it('marks Groq models available when API key is configured', function () {
        config(['prism.providers.groq.api_key' => 'test-api-key']);

        $model = AiModel::factory()->create([
            'provider' => 'groq',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Groq models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncGroq();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks Groq models unavailable when API key is empty', function () {
        config(['prism.providers.groq.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'groq',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Groq models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncGroq();

        expect($model->fresh()->is_available)->toBeFalse();
    });

    it('marks Groq models unavailable when API key is null', function () {
        config(['prism.providers.groq.api_key' => null]);

        $model = AiModel::factory()->create([
            'provider' => 'groq',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Groq models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncGroq();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('syncOpenAI', function () {
    it('marks OpenAI models available when API key is configured', function () {
        config(['prism.providers.openai.api_key' => 'sk-test-key']);

        $model = AiModel::factory()->create([
            'provider' => 'openai',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('OpenAI models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncOpenAI();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks OpenAI models unavailable when no API key', function () {
        config(['prism.providers.openai.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'openai',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('OpenAI models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncOpenAI();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('syncAnthropic', function () {
    it('marks Anthropic models available when API key is configured', function () {
        config(['prism.providers.anthropic.api_key' => 'sk-ant-test-key']);

        $model = AiModel::factory()->create([
            'provider' => 'anthropic',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Anthropic models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncAnthropic();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks Anthropic models unavailable when no API key', function () {
        config(['prism.providers.anthropic.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'anthropic',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Anthropic models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncAnthropic();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('syncXAI', function () {
    it('marks xAI models available when API key is configured', function () {
        config(['prism.providers.xai.api_key' => 'xai-test-key']);

        $model = AiModel::factory()->create([
            'provider' => 'xai',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('xAI models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncXAI();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks xAI models unavailable when no API key', function () {
        config(['prism.providers.xai.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'xai',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('xAI models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncXAI();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('syncGemini', function () {
    it('marks Gemini models available when API key is configured', function () {
        config(['prism.providers.gemini.api_key' => 'gemini-test-key']);

        $model = AiModel::factory()->create([
            'provider' => 'gemini',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Gemini models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncGemini();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks Gemini models unavailable when no API key', function () {
        config(['prism.providers.gemini.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'gemini',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Gemini models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncGemini();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('syncMistral', function () {
    it('marks Mistral models available when API key is configured', function () {
        config(['prism.providers.mistral.api_key' => 'mistral-test-key']);

        $model = AiModel::factory()->create([
            'provider' => 'mistral',
            'is_available' => false,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Mistral models synced', ['available' => true]);

        $service = app(ModelSyncService::class);
        $service->syncMistral();

        expect($model->fresh()->is_available)->toBeTrue();
    });

    it('marks Mistral models unavailable when no API key', function () {
        config(['prism.providers.mistral.api_key' => '']);

        $model = AiModel::factory()->create([
            'provider' => 'mistral',
            'is_available' => true,
        ]);

        Log::shouldReceive('debug')
            ->once()
            ->with('Mistral models synced', ['available' => false]);

        $service = app(ModelSyncService::class);
        $service->syncMistral();

        expect($model->fresh()->is_available)->toBeFalse();
    });
});

describe('getDefaultModel', function () {
    it('returns first available enabled model with tool support', function () {
        $withTools = AiModel::factory()->create([
            'enabled' => true,
            'is_available' => true,
            'supports_tools' => true,
            'speed_tier' => 'medium',
        ]);
        $withoutTools = AiModel::factory()->create([
            'enabled' => true,
            'is_available' => true,
            'supports_tools' => false,
            'speed_tier' => 'fast',
        ]);

        $service = app(ModelSyncService::class);
        $default = $service->getDefaultModel();

        expect($default->id)->toBe($withTools->id);
    });

    it('falls back to enabled model when none are available', function () {
        $enabledModel = AiModel::factory()->create([
            'enabled' => true,
            'is_available' => false,
        ]);

        $service = app(ModelSyncService::class);
        $default = $service->getDefaultModel();

        expect($default->id)->toBe($enabledModel->id);
    });

    it('returns null when no models exist', function () {
        $service = app(ModelSyncService::class);
        $default = $service->getDefaultModel();

        expect($default)->toBeNull();
    });

    it('prefers faster models when tool support is equal', function () {
        $fast = AiModel::factory()->create([
            'enabled' => true,
            'is_available' => true,
            'supports_tools' => true,
            'speed_tier' => 'fast',
        ]);
        $slow = AiModel::factory()->create([
            'enabled' => true,
            'is_available' => true,
            'supports_tools' => true,
            'speed_tier' => 'slow',
        ]);

        $service = app(ModelSyncService::class);
        $default = $service->getDefaultModel();

        expect($default->id)->toBe($fast->id);
    });
});

describe('findByModelId', function () {
    it('finds model by model_id', function () {
        $model = AiModel::factory()->create([
            'model_id' => 'gpt-4-turbo',
        ]);

        $service = app(ModelSyncService::class);
        $found = $service->findByModelId('gpt-4-turbo');

        expect($found->id)->toBe($model->id);
    });

    it('returns null when model not found', function () {
        $service = app(ModelSyncService::class);
        $found = $service->findByModelId('nonexistent-model');

        expect($found)->toBeNull();
    });
});
