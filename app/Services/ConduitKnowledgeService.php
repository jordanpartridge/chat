<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ConduitKnowledgeService
{
    /**
     * Perform a search against the Conduit knowledge base by invoking the Conduit CLI and parsing its output.
     *
     * @param string $query The search query string.
     * @param array<string>|null $tags Optional list of tags to filter results.
     * @param string|null $collection Optional collection name to restrict the search.
     * @param bool $semantic When true, perform a semantic search variant.
     * @param int $limit Maximum number of results to return.
     * @return ConduitKnowledgeResult The parsed search result containing success status, entries, and an error message when unsuccessful.
     */
    public function search(
        string $query,
        ?array $tags = null,
        ?string $collection = null,
        bool $semantic = false,
        int $limit = 5
    ): ConduitKnowledgeResult {
        $command = $this->buildSearchCommand($query, $tags, $collection, $semantic, $limit);

        // Set PATH to include Herd's PHP binary location
        $env = [
            'PATH' => '/Users/jordanpartridge/Library/Application Support/Herd/bin:/usr/local/bin:/usr/bin:/bin',
        ];

        $result = Process::timeout(30)->env($env)->run($command);

        if (! $result->successful()) {
            return new ConduitKnowledgeResult(
                success: false,
                entries: [],
                error: $result->errorOutput() ?: 'Failed to search knowledge base'
            );
        }

        $output = $result->output();

        return $this->parseSearchOutput($output);
    }

    / **
     * Determine whether the Conduit CLI is available on the system.
     *
     * Checks several common installation paths for an executable `conduit` binary;
     * if none are found, falls back to running `which conduit` as a final check.
     *
     * @return bool `true` if an executable conduit binary is found, `false` otherwise.
     */
    public function isAvailable(): bool
    {
        // Check common locations for conduit binary
        $possiblePaths = [
            '/Users/jordanpartridge/.composer/vendor/bin/conduit',
            '/usr/local/bin/conduit',
            getenv('HOME').'/.composer/vendor/bin/conduit',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return true;
            }
        }

        // Fallback to which command
        $result = Process::timeout(5)->run('which conduit');

        return $result->successful() && ! empty(trim($result->output()));
    }

    /**
     * Resolve the file system path to the Conduit CLI binary.
     *
     * Checks common installation locations and returns the first executable path found.
     * If no explicit path is found, returns 'conduit' to rely on the system PATH.
     *
     * @return string The resolved conduit binary path (absolute executable path or 'conduit').
     */
    private function getConduitPath(): string
    {
        $possiblePaths = [
            '/Users/jordanpartridge/.composer/vendor/bin/conduit',
            '/usr/local/bin/conduit',
            getenv('HOME').'/.composer/vendor/bin/conduit',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return 'conduit'; // fallback to PATH
    }

    /**
     * Add a new knowledge entry.
     *
     * @param  array<string>  $tags
     */
    public function add(
        string $content,
        array $tags = [],
        ?string $collection = null,
        string $priority = 'medium'
    ): bool {
        $command = ['conduit', 'knowledge:add', '--no-interaction'];

        if (! empty($tags)) {
            $command[] = '--tags='.implode(',', $tags);
        }

        if ($collection !== null) {
            $command[] = '--collection='.$collection;
        }

        $command[] = '--priority='.$priority;

        // Pass content via stdin
        $result = Process::timeout(30)
            ->input($content)
            ->run(implode(' ', $command));

        return $result->successful();
    }

    /**
         * Construct the shell command string for executing a Conduit knowledge search.
         *
         * Builds a single command string with arguments and options (with values escaped)
         * appropriate for passing to a shell.
         *
         * @param string $query The search query.
         * @param array<string>|null $tags Optional list of tags to filter results.
         * @param string|null $collection Optional collection name to restrict the search.
         * @param bool $semantic Whether to enable semantic search.
         * @param int $limit Maximum number of results to return.
         * @return string The assembled shell command string to execute the Conduit CLI search.
         */
    private function buildSearchCommand(
        string $query,
        ?array $tags,
        ?string $collection,
        bool $semantic,
        int $limit
    ): string {
        $parts = [$this->getConduitPath(), 'knowledge:search'];

        $parts[] = escapeshellarg($query);
        $parts[] = '--limit='.escapeshellarg((string) $limit);

        if ($semantic) {
            $parts[] = '--semantic';
        }

        if ($tags !== null && count($tags) > 0) {
            $parts[] = '--tags='.escapeshellarg(implode(',', $tags));
        }

        if ($collection !== null) {
            $parts[] = '--collection='.escapeshellarg($collection);
        }

        $parts[] = '--no-interaction';

        return implode(' ', $parts);
    }

    /**
     * Parse the search output into structured results.
     */
    private function parseSearchOutput(string $output): ConduitKnowledgeResult
    {
        $lines = explode("\n", $output);
        $entries = [];
        $currentEntry = null;
        $currentContent = [];

        foreach ($lines as $line) {
            // Check for entry header (e.g., "📝 #1 Title - Description")
            if (preg_match('/^📝 #(\d+) (.+)$/', $line, $matches)) {
                // Save previous entry if exists
                if ($currentEntry !== null) {
                    $currentEntry['content'] = trim(implode("\n", $currentContent));
                    $entries[] = $currentEntry;
                }

                $currentEntry = [
                    'id' => (int) $matches[1],
                    'title' => $matches[2],
                    'content' => '',
                    'tags' => [],
                    'priority' => 'medium',
                    'status' => 'open',
                ];
                $currentContent = [];

                continue;
            }

            // Check for tags line
            if (preg_match('/^\s*🏷️\s*(.+)$/', $line, $matches)) {
                if ($currentEntry !== null) {
                    $currentEntry['tags'] = array_map('trim', explode(',', $matches[1]));
                }

                continue;
            }

            // Check for priority/status line
            if (preg_match('/^\s*📊 Priority: (\w+) \| Status: (\w+)/', $line, $matches)) {
                if ($currentEntry !== null) {
                    $currentEntry['priority'] = $matches[1];
                    $currentEntry['status'] = $matches[2];
                }

                continue;
            }

            // Check for date line (marks end of entry metadata)
            if (preg_match('/^\s*📅/', $line)) {
                continue;
            }

            // Skip the "Found X results" header
            if (preg_match('/^🔍 Found \d+ results?$/', $line)) {
                continue;
            }

            // Add to current entry content
            if ($currentEntry !== null && ! empty(trim($line))) {
                $currentContent[] = $line;
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null) {
            $currentEntry['content'] = trim(implode("\n", $currentContent));
            $entries[] = $currentEntry;
        }

        return new ConduitKnowledgeResult(
            success: true,
            entries: $entries,
            error: null
        );
    }
}