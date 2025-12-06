<?php

use App\Models\User;
use App\Models\UserApiCredential;

describe('encryption', function () {
    it('encrypts api_key on save', function () {
        $credential = UserApiCredential::factory()->create([
            'api_key' => 'sk-test-plaintext-key-12345',
        ]);

        // The database value should be encrypted, not plaintext
        $dbValue = \DB::table('user_api_credentials')
            ->where('id', $credential->id)
            ->value('api_key');

        expect($dbValue)->not->toBe('sk-test-plaintext-key-12345');
    });

    it('decrypts api_key on retrieve', function () {
        $originalKey = 'sk-test-plaintext-key-12345';

        $credential = UserApiCredential::factory()->create([
            'api_key' => $originalKey,
        ]);

        $retrieved = UserApiCredential::find($credential->id);

        expect($retrieved->api_key)->toBe($originalKey);
    });
});

describe('relationships', function () {
    it('belongs to user', function () {
        $user = User::factory()->create();
        $credential = UserApiCredential::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($credential->user)->toBeInstanceOf(User::class)
            ->and($credential->user->id)->toBe($user->id);
    });

    it('user has many api credentials', function () {
        $user = User::factory()->create();

        UserApiCredential::factory()->openai()->create(['user_id' => $user->id]);
        UserApiCredential::factory()->anthropic()->create(['user_id' => $user->id]);
        UserApiCredential::factory()->groq()->create(['user_id' => $user->id]);

        expect($user->apiCredentials)->toHaveCount(3)
            ->and($user->apiCredentials->first())->toBeInstanceOf(UserApiCredential::class);
    });
});

describe('scopes', function () {
    it('filters by provider', function () {
        $user = User::factory()->create();

        UserApiCredential::factory()->openai()->create(['user_id' => $user->id]);
        UserApiCredential::factory()->anthropic()->create(['user_id' => $user->id]);
        UserApiCredential::factory()->groq()->create(['user_id' => $user->id]);

        $openaiCredentials = UserApiCredential::forProvider('openai')->get();
        $anthropicCredentials = UserApiCredential::forProvider('anthropic')->get();

        expect($openaiCredentials)->toHaveCount(1)
            ->and($openaiCredentials->first()->provider)->toBe('openai')
            ->and($anthropicCredentials)->toHaveCount(1)
            ->and($anthropicCredentials->first()->provider)->toBe('anthropic');
    });
});

describe('accessors', function () {
    it('provides maskedKey accessor', function () {
        $credential = UserApiCredential::factory()->create([
            'api_key' => 'sk-test-1234567890abcdefg',
        ]);

        expect($credential->maskedKey)->toBe('sk-••••••defg')
            ->and($credential->maskedKey)->toContain('sk-••••••');
    });

    it('provides isConfigured accessor', function () {
        $configuredCredential = UserApiCredential::factory()->create([
            'api_key' => 'sk-test-key',
        ]);

        $emptyCredential = UserApiCredential::factory()->create([
            'api_key' => '',
        ]);

        expect($configuredCredential->isConfigured)->toBeTrue()
            ->and($emptyCredential->isConfigured)->toBeFalse();
    });
});

describe('constraints', function () {
    it('enforces unique provider per user', function () {
        $user = User::factory()->create();

        UserApiCredential::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
        ]);

        expect(fn () => UserApiCredential::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('allows same provider for different users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $credential1 = UserApiCredential::factory()->create([
            'user_id' => $user1->id,
            'provider' => 'openai',
        ]);

        $credential2 = UserApiCredential::factory()->create([
            'user_id' => $user2->id,
            'provider' => 'openai',
        ]);

        expect($credential1->provider)->toBe('openai')
            ->and($credential2->provider)->toBe('openai')
            ->and($credential1->user_id)->not->toBe($credential2->user_id);
    });
});

describe('factory states', function () {
    it('creates openai credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->openai()->create();

        expect($credential->provider)->toBe('openai')
            ->and($credential->api_key)->toStartWith('sk-test-');
    });

    it('creates anthropic credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->anthropic()->create();

        expect($credential->provider)->toBe('anthropic')
            ->and($credential->api_key)->toStartWith('sk-ant-');
    });

    it('creates xai credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->xai()->create();

        expect($credential->provider)->toBe('xai')
            ->and($credential->api_key)->toStartWith('xai-');
    });

    it('creates groq credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->groq()->create();

        expect($credential->provider)->toBe('groq')
            ->and($credential->api_key)->toStartWith('gsk_');
    });

    it('creates gemini credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->gemini()->create();

        expect($credential->provider)->toBe('gemini')
            ->and($credential->api_key)->toStartWith('AIzaSy');
    });

    it('creates mistral credential with correct prefix', function () {
        $credential = UserApiCredential::factory()->mistral()->create();

        expect($credential->provider)->toBe('mistral')
            ->and($credential->api_key)->toStartWith('mistral-');
    });
});

describe('user helper methods', function () {
    it('retrieves api key for specific provider', function () {
        $user = User::factory()->create();

        UserApiCredential::factory()->openai()->create([
            'user_id' => $user->id,
            'api_key' => 'sk-test-openai-key',
            'is_enabled' => true,
        ]);

        UserApiCredential::factory()->anthropic()->create([
            'user_id' => $user->id,
            'api_key' => 'sk-ant-anthropic-key',
            'is_enabled' => true,
        ]);

        expect($user->getApiKeyFor('openai'))->toBe('sk-test-openai-key')
            ->and($user->getApiKeyFor('anthropic'))->toBe('sk-ant-anthropic-key');
    });

    it('returns null when provider not found', function () {
        $user = User::factory()->create();

        expect($user->getApiKeyFor('nonexistent'))->toBeNull();
    });

    it('returns null when provider is disabled', function () {
        $user = User::factory()->create();

        UserApiCredential::factory()->openai()->create([
            'user_id' => $user->id,
            'api_key' => 'sk-test-openai-key',
            'is_enabled' => false,
        ]);

        expect($user->getApiKeyFor('openai'))->toBeNull();
    });
});
