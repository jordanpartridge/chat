<?php

use App\Models\User;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\XaiProvider;

describe('validate', function () {
    it('validates and returns anthropic models successfully', function () {
        $user = User::factory()->create();

        $this->mock(AnthropicProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->with('sk-ant-key-12345')
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'claude-3-opus', 'name' => 'Claude 3 Opus', 'description' => 'Most capable'],
                    ['id' => 'claude-3-sonnet', 'name' => 'Claude 3 Sonnet', 'description' => 'Balanced'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-key-12345',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'models' => [
                ['id' => 'claude-3-opus', 'name' => 'Claude 3 Opus', 'description' => 'Most capable'],
                ['id' => 'claude-3-sonnet', 'name' => 'Claude 3 Sonnet', 'description' => 'Balanced'],
            ],
            'error' => null,
        ]);
    });

    it('returns error for invalid anthropic api key', function () {
        $user = User::factory()->create();

        $this->mock(AnthropicProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->with('sk-invalid-key')
            ->andReturn([
                'valid' => false,
                'models' => [],
                'error' => 'Invalid API key',
            ]);

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-invalid-key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Invalid API key',
        ]);
    });

    it('validates and returns xai models successfully', function () {
        $user = User::factory()->create();

        $this->mock(XaiProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->with('xai-key-12345')
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
                    ['id' => 'grok-3-mini', 'name' => 'Grok 3 Mini', 'description' => 'Fast and efficient'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'xai',
            'api_key' => 'xai-key-12345',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'models' => [
                ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
                ['id' => 'grok-3-mini', 'name' => 'Grok 3 Mini', 'description' => 'Fast and efficient'],
            ],
            'error' => null,
        ]);
    });

    it('returns error for invalid xai api key', function () {
        $user = User::factory()->create();

        $this->mock(XaiProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->with('xai-invalid-key')
            ->andReturn([
                'valid' => false,
                'models' => [],
                'error' => 'Invalid API key',
            ]);

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'xai',
            'api_key' => 'xai-invalid-key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Invalid API key',
        ]);
    });

    it('returns error for unsupported provider', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'openai',
            'api_key' => 'sk-openai-key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Provider not yet supported for validation',
        ]);
    });

    it('returns error for gemini provider', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'gemini',
            'api_key' => 'gemini-key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Provider not yet supported for validation',
        ]);
    });

    it('returns error for mistral provider', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'mistral',
            'api_key' => 'mistral-key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Provider not yet supported for validation',
        ]);
    });

    it('returns error for groq provider', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'groq',
            'api_key' => 'groq-key-12345',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => false,
            'models' => [],
            'error' => 'Provider not yet supported for validation',
        ]);
    });

    it('requires provider field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'api_key' => 'test-key',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('provider');
    });

    it('requires api_key field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'anthropic',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('api_key');
    });

    it('validates provider is in allowed list', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'invalid-provider',
            'api_key' => 'test-key',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('provider');
    });

    it('validates api_key minimum length', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('provider-credentials.validate'), [
            'provider' => 'anthropic',
            'api_key' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('api_key');
    });

    it('requires authentication', function () {
        $response = $this->postJson(route('provider-credentials.validate'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-test-key',
        ]);

        $response->assertUnauthorized();
    });
});
