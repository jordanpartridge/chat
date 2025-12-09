<?php

use App\Services\Providers\XaiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

describe('validateAndFetchModels', function () {
    it('validates api key and fetches models successfully', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'grok-3',
                    ],
                    [
                        'id' => 'grok-3-mini',
                    ],
                    [
                        'id' => 'grok-vision-beta',
                    ],
                    [
                        'id' => 'grok-embed-large',
                    ],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key-12345');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(3); // Chat models including vision, but not embed
        expect($result['models'][0])->toBe([
            'id' => 'grok-3',
            'name' => 'Grok 3',
            'description' => 'Balanced performance',
        ]);
        expect($result['models'][1])->toBe([
            'id' => 'grok-3-mini',
            'name' => 'Grok 3 Mini',
            'description' => 'Fast and efficient',
        ]);
        expect($result['models'][2])->toBe([
            'id' => 'grok-vision-beta',
            'name' => 'Grok Vision Beta',
            'description' => 'Vision capabilities',
        ]);
        expect($result['error'])->toBeNull();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.ai/v1/models'
                && $request->header('Authorization')[0] === 'Bearer xai-test-key-12345';
        });
    });

    it('filters out image generation models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-3'],
                    ['id' => 'grok-image-gen'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(1);
        expect($result['models'][0]['id'])->toBe('grok-3');
    });

    it('filters out embedding models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-3'],
                    ['id' => 'grok-embed-large'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(1);
        expect($result['models'][0]['id'])->toBe('grok-3');
    });

    it('formats model names correctly', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-4-0709'],
                    ['id' => 'grok-3-mini-fast'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['name'])->toBe('Grok 4');
        expect($result['models'][1]['name'])->toBe('Grok 3 Mini Fast');
    });

    it('returns error for 401 unauthorized', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 401),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-invalid-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Invalid API key');
    });

    it('falls back to validation request on 403 forbidden', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'hi']],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(4); // Known models
        expect($result['models'][0]['id'])->toBe('grok-3');
        expect($result['error'])->toBeNull();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.ai/v1/chat/completions'
                && $request['model'] === 'grok-3-mini'
                && $request['max_tokens'] === 1;
        });
    });

    it('falls back on 403 and returns error if validation fails with 401', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => Http::response([], 401),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-invalid-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Invalid API key');
    });

    it('falls back on 403 and handles rate limiting gracefully', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => Http::response([], 429),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(4);
        expect($result['error'])->toBeNull();
    });

    it('falls back on 403 and returns error on other failures', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('xAI validation request failed', \Mockery::on(function ($arg) {
                return $arg['status'] === 500;
            }));

        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => Http::response('Server error', 500),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Failed to validate API key');
    });

    it('falls back on 403 and handles connection exception', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('xAI validation connection failed', \Mockery::on(function ($arg) {
                return isset($arg['error']);
            }));

        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => function () {
                throw new ConnectionException('Connection timeout');
            },
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Could not connect to xAI API');
    });

    it('returns error for other unsuccessful status codes', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('xAI API error', [
                'status' => 500,
                'body' => 'Internal server error',
            ]);

        Http::fake([
            'api.x.ai/v1/models' => Http::response('Internal server error', 500),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Failed to connect to xAI API');
    });

    it('handles connection timeout', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('xAI connection failed', \Mockery::on(function ($arg) {
                return isset($arg['error']) && is_string($arg['error']);
            }));

        Http::fake(function () {
            throw new ConnectionException('Connection timeout');
        });

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Could not connect to xAI API');
    });

    it('handles empty models array', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBeNull();
    });

    it('handles missing data field in response', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBeNull();
    });

    it('generates correct description for grok-4 models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-4'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Most capable Grok model');
    });

    it('generates correct description for grok-4 reasoning models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-4-reasoning'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Advanced reasoning capabilities');
    });

    it('generates correct description for grok-3 models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-3'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Balanced performance');
    });

    it('generates correct description for grok-3-mini models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-3-mini'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Fast and efficient');
    });

    it('generates correct description for vision models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-vision-beta'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Vision capabilities');
    });

    it('generates correct description for code models', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-code'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Optimized for coding');
    });

    it('generates default description for unknown model types', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([
                'data' => [
                    ['id' => 'grok-unknown-model'],
                ],
            ], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'][0]['description'])->toBe('Grok model');
    });

    it('returns known models list', function () {
        Http::fake([
            'api.x.ai/v1/models' => Http::response([], 403),
            'api.x.ai/v1/chat/completions' => Http::response([], 200),
        ]);

        $provider = new XaiProvider;
        $result = $provider->validateAndFetchModels('xai-test-key');

        expect($result['models'])->toBe([
            ['id' => 'grok-3', 'name' => 'Grok 3', 'description' => 'Balanced performance'],
            ['id' => 'grok-3-mini', 'name' => 'Grok 3 Mini', 'description' => 'Fast and efficient'],
            ['id' => 'grok-3-mini-fast', 'name' => 'Grok 3 Mini Fast', 'description' => 'Fastest responses'],
            ['id' => 'grok-2-1212', 'name' => 'Grok 2', 'description' => 'Previous generation'],
        ]);
    });
});
