<?php

declare(strict_types=1);

use App\Services\WebSearchService;
use App\Tools\WebSearchTool;
use Illuminate\Support\Facades\Log;

it('returns error when web search is not available', function () {
    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(false);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('test query');

    expect($result)->toBe('Error: Web search is not configured. Please add TAVILY_API_KEY to your environment.');
});

it('returns error for short queries', function () {
    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('ab');

    expect($result)->toBe('Error: Search query is too short. Please provide a more specific query.');
});

it('returns formatted results on successful search', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->with('laravel framework', 5)->andReturn([
        'success' => true,
        'results' => [
            [
                'title' => 'Laravel - The PHP Framework',
                'url' => 'https://laravel.com',
                'content' => 'Laravel is a web application framework.',
            ],
        ],
        'answer' => 'Laravel is a popular PHP framework.',
        'error' => null,
    ]);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('laravel framework');

    expect($result)->toContain('WEB SEARCH RESULTS:');
    expect($result)->toContain('Summary: Laravel is a popular PHP framework.');
    expect($result)->toContain('Sources:');
    expect($result)->toContain('[1] Laravel - The PHP Framework');
    expect($result)->toContain('URL: https://laravel.com');
    expect($result)->toContain('Laravel is a web application framework.');

    Log::shouldHaveReceived('info')->twice();
});

it('returns results without answer when not provided', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->andReturn([
        'success' => true,
        'results' => [
            [
                'title' => 'Test Result',
                'url' => 'https://example.com',
                'content' => 'Some content',
            ],
        ],
        'answer' => null,
        'error' => null,
    ]);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('test query');

    expect($result)->toContain('WEB SEARCH RESULTS:');
    expect($result)->not->toContain('Summary:');
    expect($result)->not->toContain('Sources:');
    expect($result)->toContain('[1] Test Result');
});

it('returns no results message when search returns empty', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->andReturn([
        'success' => true,
        'results' => [],
        'answer' => null,
        'error' => null,
    ]);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('obscure query');

    expect($result)->toBe("No web results found for 'obscure query'.");
});

it('returns error message on search failure', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->andReturn([
        'success' => false,
        'results' => [],
        'answer' => null,
        'error' => 'API rate limit exceeded',
    ]);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('test query');

    expect($result)->toBe('Web search failed: API rate limit exceeded');

    Log::shouldHaveReceived('warning')->once();
});

it('handles exceptions gracefully', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->andThrow(new Exception('Unexpected error'));

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('test query');

    expect($result)->toBe('Error searching the web: Unexpected error');

    Log::shouldHaveReceived('error')->once();
});

it('exposes isAvailable from service', function () {
    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->once()->andReturn(true);

    $tool = new WebSearchTool($mockService);

    expect($tool->isAvailable())->toBeTrue();
});

it('formats multiple results correctly', function () {
    Log::spy();

    $mockService = Mockery::mock(WebSearchService::class);
    $mockService->shouldReceive('isAvailable')->andReturn(true);
    $mockService->shouldReceive('search')->andReturn([
        'success' => true,
        'results' => [
            ['title' => 'First', 'url' => 'https://first.com', 'content' => 'First content'],
            ['title' => 'Second', 'url' => 'https://second.com', 'content' => 'Second content'],
            ['title' => 'Third', 'url' => 'https://third.com', 'content' => 'Third content'],
        ],
        'answer' => null,
        'error' => null,
    ]);

    $tool = new WebSearchTool($mockService);
    $result = $tool->execute('test query');

    expect($result)->toContain('[1] First');
    expect($result)->toContain('[2] Second');
    expect($result)->toContain('[3] Third');
});
