<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\ConduitKnowledgeService;
use Prism\Prism\Tool;

class ConduitKnowledgeTool extends Tool
{
    public function __construct(
        private ConduitKnowledgeService $knowledge,
    ) {
        $this
            ->as('search_knowledge')
            ->for('Search the Conduit knowledge base for relevant information. Use this to find stored knowledge, documentation, or context about topics the user asks about.')
            ->withStringParameter('query', 'The search query - what information to look for')
            ->withStringParameter('tags', 'Optional comma-separated tags to filter by (e.g., "laravel,architecture")', required: false)
            ->withStringParameter('collection', 'Optional collection name to search within', required: false)
            ->withEnumParameter('search_type', 'Type of search to perform', ['keyword', 'semantic'])
            ->using($this->execute(...));
    }

    public function execute(
        string $query,
        ?string $tags = null,
        ?string $collection = null,
        string $searchType = 'keyword'
    ): string {
        if (! $this->knowledge->isAvailable()) {
            return 'Error: Conduit CLI is not available. Knowledge search is disabled.';
        }

        if (strlen($query) < 2) {
            return 'Error: Search query is too short. Please provide a more specific query.';
        }

        $tagArray = null;
        if ($tags !== null && $tags !== '') {
            $tagArray = array_map('trim', explode(',', $tags));
        }

        $result = $this->knowledge->search(
            query: $query,
            tags: $tagArray,
            collection: $collection,
            semantic: $searchType === 'semantic',
            limit: 5
        );

        if (! $result->success) {
            return "Knowledge search failed: {$result->error}";
        }

        if (! $result->hasResults()) {
            return "No knowledge entries found matching '{$query}'.";
        }

        // Return both context for LLM and display for user
        return sprintf(
            "[knowledge:%d results]\n\n%s",
            $result->count(),
            $result->toContextString()
        );
    }
}
