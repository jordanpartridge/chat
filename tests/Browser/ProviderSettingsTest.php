<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserApiCredential;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('provider settings navigation', function () {
    it('can navigate to provider settings', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $page = visit('/settings/profile');

        $page->click('Providers')
            ->assertUrlContains('/settings/providers')
            ->assertSee('API Providers');
    });
});

describe('provider list', function () {
    it('shows configured providers', function () {
        $user = User::factory()->create();
        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSee('OpenAI')
            ->assertSee('Enabled');
    });

    it('shows empty state when no providers', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSee('No providers configured yet');
    });
});

describe('add provider', function () {
    it('can add a new provider', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->click('Add Provider')
            ->waitForText('Add API Provider')
            ->select('provider', 'openai')
            ->fill('api_key', 'sk-test-key-12345678901234567890')
            ->click('Add Provider')
            ->waitForText('OpenAI');

        $this->assertDatabaseHas('user_api_credentials', [
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);
    });
});

describe('toggle provider', function () {
    it('can disable a provider', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create([
            'provider' => 'openai',
            'is_enabled' => true,
        ]);

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSee('OpenAI')
            ->assertSee('Enabled')
            ->click('Disable')
            ->waitForText('Disabled');

        $this->assertDatabaseHas('user_api_credentials', [
            'id' => $credential->id,
            'is_enabled' => false,
        ]);
    });

    it('can enable a provider', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create([
            'provider' => 'anthropic',
            'is_enabled' => false,
        ]);

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSee('Anthropic')
            ->assertSee('Disabled')
            ->click('Enable')
            ->waitForText('Enabled');

        $this->assertDatabaseHas('user_api_credentials', [
            'id' => $credential->id,
            'is_enabled' => true,
        ]);
    });
});

describe('remove provider', function () {
    it('can remove a provider', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSee('OpenAI')
            ->click('[data-testid="delete-openai"]')
            ->waitForText('Remove OpenAI?')
            ->click('Remove Provider')
            ->waitUntilMissing('OpenAI');

        $this->assertModelMissing($credential);
    });
});
