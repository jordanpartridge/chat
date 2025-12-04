<?php

use App\Services\ConduitKnowledgeResult;
use App\Services\ConduitKnowledgeService;
use App\Tools\ConduitKnowledgeTool;

it('returns error when conduit is not available', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(false);

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test query');

    expect($result)->toBe('Error: Conduit CLI is not available. Knowledge search is disabled.');
});

it('returns error when query is too short', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'a');

    expect($result)->toBe('Error: Search query is too short. Please provide a more specific query.');
});

it('searches knowledge base successfully', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->with('laravel auth', null, null, false, 5)
        ->andReturn(new ConduitKnowledgeResult(
            success: true,
            entries: [
                [
                    'id' => 1,
                    'title' => 'Laravel Auth Guide',
                    'content' => 'Use Fortify for auth',
                    'tags' => ['laravel', 'auth'],
                    'priority' => 'high',
                    'status' => 'open',
                ],
            ]
        ));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'laravel auth');

    expect($result)->toContain('[knowledge:1 results]')
        ->and($result)->toContain('Laravel Auth Guide');
});

it('returns no results message when none found', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->andReturn(new ConduitKnowledgeResult(
            success: true,
            entries: []
        ));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'nonexistent topic');

    expect($result)->toBe("No knowledge entries found matching 'nonexistent topic'.");
});

it('returns error message when search fails', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->andReturn(new ConduitKnowledgeResult(
            success: false,
            entries: [],
            error: 'Connection refused'
        ));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test query');

    expect($result)->toBe('Knowledge search failed: Connection refused');
});

it('passes tags to search service', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->once()
        ->with('test', ['laravel', 'api'], null, false, 5)
        ->andReturn(new ConduitKnowledgeResult(success: true, entries: []));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test', tags: 'laravel, api');

    expect($result)->toContain('No knowledge entries found');
});

it('passes collection to search service', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->once()
        ->with('test', null, 'docs', false, 5)
        ->andReturn(new ConduitKnowledgeResult(success: true, entries: []));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test', collection: 'docs');

    expect($result)->toContain('No knowledge entries found');
});

it('enables semantic search when specified', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->once()
        ->with('test', null, null, true, 5)
        ->andReturn(new ConduitKnowledgeResult(success: true, entries: []));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test', searchType: 'semantic');

    expect($result)->toContain('No knowledge entries found');
});

it('handles empty tags string', function () {
    $service = $this->mock(ConduitKnowledgeService::class);
    $service->shouldReceive('isAvailable')->andReturn(true);
    $service->shouldReceive('search')
        ->once()
        ->with('test', null, null, false, 5)
        ->andReturn(new ConduitKnowledgeResult(success: true, entries: []));

    $tool = new ConduitKnowledgeTool($service);

    $result = $tool->execute(query: 'test', tags: '');

    expect($result)->toContain('No knowledge entries found');
});
