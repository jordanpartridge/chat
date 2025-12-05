<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\ConduitKnowledgeService;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Tool;

class ConduitKnowledgeTool extends Tool
{
    public function __construct(
        private ConduitKnowledgeService $knowledge,
    ) {
        $this
            ->as('search_knowledge')
            ->for('Search the personal knowledge base. Call this ONCE, then respond with the results. IMPORTANT: Only state facts found in the search results. Do NOT infer, embellish, or add information not explicitly returned. If the search returns no results, say so - do not guess.')
            ->withStringParameter('query', 'The search query - what information to look for')
            ->using($this->execute(...));
    }

    public function execute(
        string $query,
    ): string {
        try {
            if (! $this->knowledge->isAvailable()) {
                return 'Error: Conduit CLI is not available. Knowledge search is disabled.';
            }

            if (strlen($query) < 2) {
                return 'Error: Search query is too short. Please provide a more specific query.';
            }

            $result = $this->knowledge->search(
                query: $query,
                tags: null,
                collection: null,
                semantic: false,
                limit: 5
            );

            if (! $result->success) {
                Log::warning('ConduitKnowledgeTool search failed', ['error' => $result->error]);

                return "Knowledge search failed: {$result->error}";
            }

            if (! $result->hasResults()) {
                return "No knowledge entries found matching '{$query}'.";
            }

            return sprintf(
                "SEARCH COMPLETE - Found %d results. Use this information to answer the user's question:\n\n%s",
                $result->count(),
                $result->toContextString()
            );
        } catch (\Throwable $e) {
            Log::error('ConduitKnowledgeTool exception', ['message' => $e->getMessage()]);

            return 'Error searching knowledge base: '.$e->getMessage();
        }
    }
}
