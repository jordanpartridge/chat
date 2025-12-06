<?php

use App\Models\User;
use App\Models\UserApiCredential;

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
    it('creates credential with valid data', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-test-key-12345',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);
    });

    it('prevents duplicate provider', function () {
        $user = User::factory()->create();
        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);

        $response = $this->actingAs($user)->post(route('provider-credentials.store'), [
            'provider' => 'openai',
            'api_key' => 'sk-another-key',
        ]);

        $response->assertSessionHasErrors('provider');
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
