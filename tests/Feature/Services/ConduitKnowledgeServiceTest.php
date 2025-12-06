<?php

use App\Services\ConduitKnowledgeService;
use Illuminate\Support\Facades\Process;

describe('knowledge search', function () {
    it('searches knowledge base successfully', function () {
        Process::fake([
            '*' => Process::result(
                output: <<<'OUTPUT'
🔍 Found 2 results

📝 #1 Laravel Authentication - How to implement auth in Laravel

Authentication is a core feature of Laravel.
Use Laravel Fortify or Breeze for quick setup.
   🏷️  laravel, auth, security
   📊 Priority: high | Status: open
   📅 2 months ago

📝 #2 API Security - Best practices for API security

Always use rate limiting and validation.
   🏷️  api, security
   📊 Priority: medium | Status: open
   📅 3 months ago
OUTPUT
            ),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->search('laravel auth');

        expect($result->success)->toBeTrue()
            ->and($result->hasResults())->toBeTrue()
            ->and($result->count())->toBe(2)
            ->and($result->entries[0]['title'])->toContain('Laravel Authentication')
            ->and($result->entries[0]['tags'])->toContain('laravel')
            ->and($result->entries[0]['priority'])->toBe('high');
    });

    it('handles empty search results', function () {
        Process::fake([
            '*' => Process::result(output: "🔍 Found 0 results\n"),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->search('nonexistent topic');

        expect($result->success)->toBeTrue()
            ->and($result->hasResults())->toBeFalse()
            ->and($result->count())->toBe(0);
    });

    it('handles search failure', function () {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Command not found',
                exitCode: 1
            ),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->search('test query');

        expect($result->success)->toBeFalse()
            ->and($result->error)->toContain('Command not found');
    });

    it('builds correct search command with options', function () {
        Process::fake([
            '*' => Process::result(output: "🔍 Found 0 results\n"),
        ]);

        $service = new ConduitKnowledgeService;
        $service->search(
            query: 'test',
            tags: ['laravel', 'api'],
            collection: 'docs',
            semantic: true,
            limit: 3
        );

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, 'knowledge:search')
                && str_contains($command, "'test'")
                && str_contains($command, '--semantic')
                && str_contains($command, '--limit=')
                && str_contains($command, '--tags=');
        });
    });

    it('parses tags correctly from search output', function () {
        Process::fake([
            '*' => Process::result(
                output: <<<'OUTPUT'
🔍 Found 1 results

📝 #1 Test Entry - Description here

Some content
   🏷️  tag1, tag2, tag3
   📊 Priority: low | Status: closed
   📅 1 month ago
OUTPUT
            ),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->search('test');

        expect($result->entries[0]['tags'])->toBe(['tag1', 'tag2', 'tag3'])
            ->and($result->entries[0]['priority'])->toBe('low')
            ->and($result->entries[0]['status'])->toBe('closed');
    });
});

describe('conduit availability', function () {
    it('checks conduit availability', function () {
        Process::fake([
            'which conduit' => Process::result(output: '/usr/local/bin/conduit'),
        ]);

        $service = new ConduitKnowledgeService;

        expect($service->isAvailable())->toBeTrue();
    });

    it('returns false when conduit is not available', function () {
        // Skip this test if conduit is actually installed (can't mock file_exists)
        $possiblePaths = [
            '/Users/jordanpartridge/.composer/vendor/bin/conduit',
            '/usr/local/bin/conduit',
            getenv('HOME').'/.composer/vendor/bin/conduit',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $this->markTestSkipped('Conduit is installed on this system - cannot test unavailability');
            }
        }

        Process::fake([
            'which conduit' => Process::result(output: '', exitCode: 1),
        ]);

        $service = new ConduitKnowledgeService;

        expect($service->isAvailable())->toBeFalse();
    });

    it('uses fallback conduit path when not found in common paths', function () {
        // This test verifies the getConduitPath returns 'conduit' as fallback
        // when file doesn't exist in common paths
        Process::fake([
            'which conduit' => Process::result(output: '', exitCode: 1),
            '*' => Process::result(output: "🔍 Found 0 results\n"),
        ]);

        $service = new ConduitKnowledgeService;
        $service->search('test');

        // Should still run search using fallback 'conduit' command
        Process::assertRan(function ($process) {
            return str_contains($process->command, 'conduit') && str_contains($process->command, 'knowledge:search');
        });
    });
});

describe('knowledge add', function () {
    it('adds knowledge entry successfully', function () {
        Process::fake([
            '*' => Process::result(output: 'Knowledge entry added successfully'),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->add(
            content: 'Test knowledge content',
            tags: ['test', 'example'],
            collection: 'docs',
            priority: 'high'
        );

        expect($result)->toBeTrue();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, 'knowledge:add')
                && str_contains($command, '--tags=test,example')
                && str_contains($command, '--collection=docs')
                && str_contains($command, '--priority=high');
        });
    });

    it('adds knowledge entry with minimal options', function () {
        Process::fake([
            '*' => Process::result(output: 'Knowledge entry added'),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->add('Simple content');

        expect($result)->toBeTrue();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, 'knowledge:add')
                && str_contains($command, '--priority=medium')
                && ! str_contains($command, '--tags=')
                && ! str_contains($command, '--collection=');
        });
    });

    it('returns false when add fails', function () {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Failed to add entry',
                exitCode: 1
            ),
        ]);

        $service = new ConduitKnowledgeService;
        $result = $service->add('Test content');

        expect($result)->toBeFalse();
    });
});
