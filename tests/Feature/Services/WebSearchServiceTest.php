<?php

declare(strict_types=1);

use App\Services\WebSearchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('services.tavily.api_key', 'test-api-key');
});

it('returns not available when api key is empty', function () {
    Config::set('services.tavily.api_key', '');

    $service = new WebSearchService;

    expect($service->isAvailable())->toBeFalse();
});

it('returns available when api key is set', function () {
    $service = new WebSearchService;

    expect($service->isAvailable())->toBeTrue();
});

it('returns error when searching without api key', function () {
    Config::set('services.tavily.api_key', '');

    $service = new WebSearchService;
    $result = $service->search('test query');

    expect($result)->toBe([
        'success' => false,
        'results' => [],
        'answer' => null,
        'error' => 'Tavily API key not configured',
    ]);
});

it('makes successful search request', function () {
    Http::fake([
        'api.tavily.com/search' => Http::response([
            'results' => [
                [
                    'title' => 'Test Result',
                    'url' => 'https://example.com',
                    'content' => 'Test content',
                ],
            ],
            'answer' => 'Test answer',
        ], 200),
    ]);

    $service = new WebSearchService;
    $result = $service->search('test query', 5);

    expect($result['success'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0])->toBe([
        'title' => 'Test Result',
        'url' => 'https://example.com',
        'content' => 'Test content',
    ]);
    expect($result['answer'])->toBe('Test answer');
    expect($result['error'])->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.tavily.com/search'
            && $request['api_key'] === 'test-api-key'
            && $request['query'] === 'test query'
            && $request['max_results'] === 5;
    });
});

it('handles missing fields in search results gracefully', function () {
    Http::fake([
        'api.tavily.com/search' => Http::response([
            'results' => [
                ['title' => 'Only Title'],
                [],
            ],
        ], 200),
    ]);

    $service = new WebSearchService;
    $result = $service->search('test query');

    expect($result['success'])->toBeTrue();
    expect($result['results'])->toHaveCount(2);
    expect($result['results'][0]['title'])->toBe('Only Title');
    expect($result['results'][0]['url'])->toBe('');
    expect($result['results'][0]['content'])->toBe('');
    expect($result['results'][1]['title'])->toBe('Untitled');
    expect($result['answer'])->toBeNull();
});

it('handles failed api response', function () {
    Log::spy();

    Http::fake([
        'api.tavily.com/search' => Http::response(['error' => 'Bad request'], 400),
    ]);

    $service = new WebSearchService;
    $result = $service->search('test query');

    expect($result['success'])->toBeFalse();
    expect($result['results'])->toBe([]);
    expect($result['answer'])->toBeNull();
    expect($result['error'])->toBe('Search request failed: 400');

    Log::shouldHaveReceived('warning')->once();
});

it('handles exceptions during search', function () {
    Log::spy();

    Http::fake([
        'api.tavily.com/search' => function () {
            throw new Exception('Connection timeout');
        },
    ]);

    $service = new WebSearchService;
    $result = $service->search('test query');

    expect($result['success'])->toBeFalse();
    expect($result['results'])->toBe([]);
    expect($result['answer'])->toBeNull();
    expect($result['error'])->toBe('Connection timeout');

    Log::shouldHaveReceived('error')->once();
});

it('handles null api key config', function () {
    Config::set('services.tavily.api_key', null);

    $service = new WebSearchService;

    expect($service->isAvailable())->toBeFalse();
});
