<?php

use App\Services\ConduitKnowledgeResult;

describe('result detection', function () {
    it('reports has results correctly', function () {
        $resultWithEntries = new ConduitKnowledgeResult(
            success: true,
            entries: [
                ['id' => 1, 'title' => 'Test', 'content' => 'Content', 'tags' => [], 'priority' => 'medium', 'status' => 'open'],
            ]
        );

        $emptyResult = new ConduitKnowledgeResult(
            success: true,
            entries: []
        );

        $failedResult = new ConduitKnowledgeResult(
            success: false,
            entries: [],
            error: 'Failed'
        );

        expect($resultWithEntries->hasResults())->toBeTrue()
            ->and($emptyResult->hasResults())->toBeFalse()
            ->and($failedResult->hasResults())->toBeFalse();
    });

    it('counts entries correctly', function () {
        $result = new ConduitKnowledgeResult(
            success: true,
            entries: [
                ['id' => 1, 'title' => 'One', 'content' => '', 'tags' => [], 'priority' => 'medium', 'status' => 'open'],
                ['id' => 2, 'title' => 'Two', 'content' => '', 'tags' => [], 'priority' => 'medium', 'status' => 'open'],
                ['id' => 3, 'title' => 'Three', 'content' => '', 'tags' => [], 'priority' => 'medium', 'status' => 'open'],
            ]
        );

        expect($result->count())->toBe(3);
    });
});

describe('context string formatting', function () {
    it('formats context string for LLM', function () {
        $result = new ConduitKnowledgeResult(
            success: true,
            entries: [
                [
                    'id' => 1,
                    'title' => 'Laravel Patterns',
                    'content' => 'Use repository pattern for data access.',
                    'tags' => ['laravel', 'patterns'],
                    'priority' => 'high',
                    'status' => 'open',
                ],
            ]
        );

        $context = $result->toContextString();

        expect($context)->toContain('Found 1 relevant knowledge entries')
            ->and($context)->toContain('Laravel Patterns')
            ->and($context)->toContain('Tags: laravel, patterns')
            ->and($context)->toContain('Use repository pattern');
    });

    it('returns error message for failed results', function () {
        $result = new ConduitKnowledgeResult(
            success: false,
            entries: [],
            error: 'Connection timeout'
        );

        $context = $result->toContextString();

        expect($context)->toContain('Knowledge search failed: Connection timeout');
    });

    it('returns appropriate message for no results', function () {
        $result = new ConduitKnowledgeResult(
            success: true,
            entries: []
        );

        $context = $result->toContextString();

        expect($context)->toBe('No relevant knowledge found.');
    });
});

describe('display string formatting', function () {
    it('formats display string for chat', function () {
        $result = new ConduitKnowledgeResult(
            success: true,
            entries: [
                [
                    'id' => 1,
                    'title' => 'Test Entry',
                    'content' => 'Some content here',
                    'tags' => ['tag1', 'tag2'],
                    'priority' => 'medium',
                    'status' => 'open',
                ],
            ]
        );

        $display = $result->toDisplayString();

        expect($display)->toContain('**Found 1 knowledge entries:**')
            ->and($display)->toContain('**1. Test Entry**')
            ->and($display)->toContain('`tag1`')
            ->and($display)->toContain('`tag2`');
    });

    it('returns error message for failed results', function () {
        $result = new ConduitKnowledgeResult(
            success: false,
            entries: [],
            error: 'Connection timeout'
        );

        $display = $result->toDisplayString();

        expect($display)->toContain('**Knowledge Search Error:** Connection timeout');
    });

    it('returns appropriate message for no results', function () {
        $result = new ConduitKnowledgeResult(
            success: true,
            entries: []
        );

        $display = $result->toDisplayString();

        expect($display)->toBe('*No relevant knowledge entries found.*');
    });

    it('truncates long content in display string', function () {
        $longContent = str_repeat('A', 600);

        $result = new ConduitKnowledgeResult(
            success: true,
            entries: [
                [
                    'id' => 1,
                    'title' => 'Long Entry',
                    'content' => $longContent,
                    'tags' => [],
                    'priority' => 'medium',
                    'status' => 'open',
                ],
            ]
        );

        $display = $result->toDisplayString();

        expect(strlen($display))->toBeLessThan(700)
            ->and($display)->toContain('...');
    });
});
