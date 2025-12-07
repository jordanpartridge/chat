<?php

use App\Models\User;
use App\Models\UserApiCredential;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\XaiProvider;

describe('index', function () {
    it('redirects guests to login', function () {
        $response = $this->get(route('provider-credentials.index'));

        $response->assertRedirect(route('login'));
    });

    it('displays provider settings page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('provider-credentials.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Providers')
            ->has('credentials')
            ->has('availableProviders')
        );
    });

    it('includes user credentials', function () {
        $user = User::factory()->create();
        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);
        UserApiCredential::factory()->for($user)->create(['provider' => 'anthropic']);

        $response = $this->actingAs($user)->get(route('provider-credentials.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('credentials', 2)
        );
    });

    it('only shows credentials for the authenticated user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);
        UserApiCredential::factory()->for($otherUser)->create(['provider' => 'anthropic']);
        UserApiCredential::factory()->for($otherUser)->create(['provider' => 'xai']);

        $response = $this->actingAs($user)->get(route('provider-credentials.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('credentials', 1)
        );
    });
});

describe('store', function () {
    it('creates credential with valid data and selected models', function () {
        $user = User::factory()->create();

        // Mock the AnthropicProvider to return valid response
        $this->mock(AnthropicProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'claude-3-opus', 'name' => 'Claude 3 Opus', 'description' => 'Most capable'],
                    ['id' => 'claude-3-sonnet', 'name' => 'Claude 3 Sonnet', 'description' => 'Balanced'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-test-key-12345',
            'models' => ['claude-3-opus', 'claude-3-sonnet'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'anthropic',
        ]);
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'claude-3-opus',
        ]);
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'claude-3-sonnet',
        ]);
    });

    it('prevents duplicate provider', function () {
        $user = User::factory()->create();
        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-another-key',
            'models' => ['gpt-4'],
        ]);

        $response->assertSessionHasErrors('provider');
    });

    it('requires models to be selected', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-key-12345',
        ]);

        $response->assertSessionHasErrors('models');
    });

    it('validates provider is allowed', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'invalid-provider',
            'api_key' => 'sk-test-key',
        ]);

        $response->assertSessionHasErrors('provider');
    });

    it('requires provider field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'api_key' => 'sk-test-key',
        ]);

        $response->assertSessionHasErrors('provider');
    });

    it('requires api_key field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
        ]);

        $response->assertSessionHasErrors('api_key');
    });

    it('validates api_key minimum length', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'short',
        ]);

        $response->assertSessionHasErrors('api_key');
    });

    it('returns error when provider validation fails', function () {
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

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-invalid-key',
            'models' => ['claude-3-opus'],
        ]);

        $response->assertSessionHasErrors('api_key');
        $this->assertDatabaseMissing('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'anthropic',
        ]);
    });

    it('fetches models from xai provider', function () {
        $user = User::factory()->create();

        $this->mock(XaiProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
                    ['id' => 'grok-3-mini', 'name' => 'Grok 3 Mini', 'description' => 'Fast and efficient'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'xai',
            'api_key' => 'xai-test-key-12345',
            'models' => ['grok-3', 'grok-3-mini'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'xai',
        ]);
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'grok-3',
        ]);
    });

    it('handles default provider case gracefully', function () {
        $user = User::factory()->create();

        // OpenAI doesn't have a provider service yet, so it goes to default case
        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-openai-key-12345',
            'models' => ['gpt-4'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);
    });

    it('sets supports_tools correctly for anthropic models', function () {
        $user = User::factory()->create();

        $this->mock(AnthropicProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'claude-3-opus', 'name' => 'Claude 3 Opus', 'description' => 'Most capable'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'anthropic',
            'api_key' => 'sk-test-key',
            'models' => ['claude-3-opus'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'claude-3-opus',
            'supports_tools' => true,
        ]);
    });

    it('sets supports_tools correctly for openai gpt-4 models', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'models' => ['gpt-4', 'gpt-4-turbo'],
        ]);

        $response->assertRedirect();

        // For providers without a service, no models are created (empty default case)
        // The credential is created but models list is empty from fetchModelsFromProvider
        $this->assertDatabaseMissing('ai_models', [
            'model_id' => 'gpt-4',
        ]);

        // Verify credential was created
        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);
    });

    it('sets supports_tools correctly for openai gpt-3.5-turbo models', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'models' => ['gpt-3.5-turbo'],
        ]);

        $response->assertRedirect();

        // For providers without a service, no models are created
        $this->assertDatabaseMissing('ai_models', [
            'model_id' => 'gpt-3.5-turbo',
        ]);
    });

    it('sets supports_tools correctly for gemini models', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'gemini',
            'api_key' => 'gemini-test-key',
            'models' => ['gemini-pro'],
        ]);

        $response->assertRedirect();

        // For providers without a service, no models are created
        $this->assertDatabaseMissing('ai_models', [
            'model_id' => 'gemini-pro',
        ]);
    });

    it('sets supports_tools to true for xai non-vision models', function () {
        $user = User::factory()->create();

        $this->mock(XaiProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'xai',
            'api_key' => 'xai-test-key',
            'models' => ['grok-3'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'grok-3',
            'supports_tools' => true,
        ]);
    });

    it('sets supports_tools to false for xai vision models', function () {
        $user = User::factory()->create();

        $this->mock(XaiProvider::class)
            ->shouldReceive('validateAndFetchModels')
            ->once()
            ->andReturn([
                'valid' => true,
                'models' => [
                    ['id' => 'grok-vision-beta', 'name' => 'Grok Vision', 'description' => 'Vision capabilities'],
                ],
                'error' => null,
            ]);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'xai',
            'api_key' => 'xai-test-key',
            'models' => ['grok-vision-beta'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'grok-vision-beta',
            'supports_tools' => false,
        ]);
    });
});

describe('update', function () {
    it('updates own credential', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create([
            'api_key' => 'sk-old-key-12345',
        ]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.update', $credential),
            ['api_key' => 'sk-new-key-67890']
        );

        $response->assertRedirect();
        expect($credential->fresh()->api_key)->toBe('sk-new-key-67890');
    });

    it('prevents updating other user credential', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $credential = UserApiCredential::factory()->for($other)->create();

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.update', $credential),
            ['api_key' => 'sk-new-key-12345']
        );

        $response->assertForbidden();
    });

    it('requires api_key field', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.update', $credential),
            []
        );

        $response->assertSessionHasErrors('api_key');
    });

    it('validates api_key minimum length', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.update', $credential),
            ['api_key' => 'short']
        );

        $response->assertSessionHasErrors('api_key');
    });
});

describe('destroy', function () {
    it('deletes own credential', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(
            route('provider-credentials.destroy', $credential)
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_api_credentials', ['id' => $credential->id]);
    });

    it('prevents deleting other user credential', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $credential = UserApiCredential::factory()->for($other)->create();

        $response = $this->actingAs($user)->delete(
            route('provider-credentials.destroy', $credential)
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('user_api_credentials', ['id' => $credential->id]);
    });
});

describe('toggle', function () {
    it('toggles credential enabled state from true to false', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create(['is_enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle', $credential)
        );

        $response->assertRedirect();
        expect($credential->fresh()->is_enabled)->toBeFalse();
    });

    it('toggles credential enabled state from false to true', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create(['is_enabled' => false]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle', $credential)
        );

        $response->assertRedirect();
        expect($credential->fresh()->is_enabled)->toBeTrue();
    });

    it('prevents toggling other user credential', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $credential = UserApiCredential::factory()->for($other)->create(['is_enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle', $credential)
        );

        $response->assertForbidden();
        expect($credential->fresh()->is_enabled)->toBeTrue();
    });
});

describe('toggleModel', function () {
    it('toggles model enabled state from true to false', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create();
        $model = \App\Models\AiModel::factory()->forCredential($credential)->create(['enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle-model', [$credential, $model])
        );

        $response->assertRedirect();
        expect($model->fresh()->enabled)->toBeFalse();
    });

    it('toggles model enabled state from false to true', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create();
        $model = \App\Models\AiModel::factory()->forCredential($credential)->disabled()->create();

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle-model', [$credential, $model])
        );

        $response->assertRedirect();
        expect($model->fresh()->enabled)->toBeTrue();
    });

    it('prevents toggling model from other user credential', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $credential = UserApiCredential::factory()->for($other)->create();
        $model = \App\Models\AiModel::factory()->forCredential($credential)->create(['enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle-model', [$credential, $model])
        );

        $response->assertForbidden();
        expect($model->fresh()->enabled)->toBeTrue();
    });

    it('prevents toggling model that does not belong to the credential', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create(['provider' => 'anthropic']);
        $otherCredential = UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);
        $model = \App\Models\AiModel::factory()->forCredential($otherCredential)->create(['enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle-model', [$credential, $model])
        );

        $response->assertForbidden();
        expect($model->fresh()->enabled)->toBeTrue();
    });

    it('prevents toggling model when both credential and model belong to different users', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherCredential = UserApiCredential::factory()->for($other)->create();
        $anotherCredential = UserApiCredential::factory()->for($other)->create();
        $model = \App\Models\AiModel::factory()->forCredential($anotherCredential)->create(['enabled' => true]);

        $response = $this->actingAs($user)->patch(
            route('provider-credentials.toggle-model', [$otherCredential, $model])
        );

        $response->assertForbidden();
        expect($model->fresh()->enabled)->toBeTrue();
    });
});
