<?php

declare(strict_types=1);

namespace App\Services;

readonly class ConduitKnowledgeResult
{
    /**
     * @param  array<int, array{id: int, title: string, content: string, tags: array<string>, priority: string, status: string}>  $entries
     */
    public function __construct(
        public bool $success,
        public array $entries,
        public ?string $error = null,
    ) {}

    /**
     * Check if results were found.
     */
    public function hasResults(): bool
    {
        return $this->success && count($this->entries) > 0;
    }

    /**
     * Get the number of results.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Format results as a string for LLM context.
     */
    public function toContextString(): string
    {
        if (! $this->success) {
            return "Knowledge search failed: {$this->error}";
        }

        if (! $this->hasResults()) {
            return 'No relevant knowledge found.';
        }

        $output = ["Found {$this->count()} relevant knowledge entries:\n"];

        foreach ($this->entries as $index => $entry) {
            $num = $index + 1;
            $output[] = '---';
            $output[] = "### {$num}. {$entry['title']}";

            if (! empty($entry['tags'])) {
                $output[] = 'Tags: '.implode(', ', $entry['tags']);
            }

            $output[] = '';
            $output[] = $entry['content'];
            $output[] = '';
        }

        return implode("\n", $output);
    }

    /**
     * Format results for display in chat.
     */
    public function toDisplayString(): string
    {
        if (! $this->success) {
            return "**Knowledge Search Error:** {$this->error}";
        }

        if (! $this->hasResults()) {
            return '*No relevant knowledge entries found.*';
        }

        $output = ["**Found {$this->count()} knowledge entries:**\n"];

        foreach ($this->entries as $index => $entry) {
            $num = $index + 1;
            $tags = ! empty($entry['tags']) ? ' `'.implode('` `', $entry['tags']).'`' : '';
            $output[] = "**{$num}. {$entry['title']}**{$tags}";

            // Truncate content for display
            $content = $entry['content'];
            if (strlen($content) > 500) {
                $content = substr($content, 0, 500).'...';
            }
            $output[] = $content;
            $output[] = '';
        }

        return implode("\n", $output);
    }
}
