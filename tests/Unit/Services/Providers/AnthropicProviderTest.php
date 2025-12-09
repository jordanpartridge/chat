<?php

use App\Services\Providers\AnthropicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

describe('validateAndFetchModels', function () {
    it('validates api key and fetches models successfully', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'claude-sonnet-4-20250514',
                        'display_name' => 'Claude 4 Sonnet',
                    ],
                    [
                        'id' => 'claude-3-opus-20240229',
                        'display_name' => 'Claude 3 Opus',
                    ],
                    [
                        'id' => 'claude-3-haiku-20240307',
                    ],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key-12345');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toHaveCount(3);
        expect($result['models'][0])->toBe([
            'id' => 'claude-sonnet-4-20250514',
            'name' => 'Claude 4 Sonnet',
            'description' => 'Balanced performance and speed',
        ]);
        expect($result['models'][1])->toBe([
            'id' => 'claude-3-opus-20240229',
            'name' => 'Claude 3 Opus',
            'description' => 'Most capable, complex tasks',
        ]);
        expect($result['models'][2]['id'])->toBe('claude-3-haiku-20240307');
        expect($result['models'][2]['description'])->toBe('Fast and lightweight');
        expect($result['error'])->toBeNull();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.anthropic.com/v1/models'
                && $request->header('x-api-key')[0] === 'sk-ant-test-key-12345'
                && $request->header('anthropic-version')[0] === '2023-06-01';
        });
    });

    it('formats model names when display_name is missing', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'claude-opus-3-20240229',
                    ],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'][0]['name'])->toBe('Claude Opus 3');
    });

    it('returns error for 401 unauthorized', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([], 401),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-invalid-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Invalid API key');
    });

    it('returns error for 403 forbidden', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([], 403),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-no-permission');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('API key does not have permission to list models');
    });

    it('returns error for other unsuccessful status codes', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('Anthropic API error', [
                'status' => 500,
                'body' => 'Internal server error',
            ]);

        Http::fake([
            'api.anthropic.com/v1/models' => Http::response('Internal server error', 500),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Failed to connect to Anthropic API');
    });

    it('handles connection timeout', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('Anthropic connection failed', \Mockery::on(function ($arg) {
                return isset($arg['error']) && is_string($arg['error']);
            }));

        Http::fake(function () {
            throw new ConnectionException('Connection timeout');
        });

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['valid'])->toBeFalse();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBe('Could not connect to Anthropic API');
    });

    it('handles empty models array', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBeNull();
    });

    it('handles missing data field in response', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['valid'])->toBeTrue();
        expect($result['models'])->toBe([]);
        expect($result['error'])->toBeNull();
    });

    it('generates correct description for opus models', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-opus-4'],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['models'][0]['description'])->toBe('Most capable, complex tasks');
    });

    it('generates correct description for sonnet models', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-sonnet-4'],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['models'][0]['description'])->toBe('Balanced performance and speed');
    });

    it('generates correct description for haiku models', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-haiku-3'],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['models'][0]['description'])->toBe('Fast and lightweight');
    });

    it('generates default description for unknown model types', function () {
        Http::fake([
            'api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-unknown-model'],
                ],
            ], 200),
        ]);

        $provider = new AnthropicProvider;
        $result = $provider->validateAndFetchModels('sk-ant-test-key');

        expect($result['models'][0]['description'])->toBe('Claude model');
    });
});
