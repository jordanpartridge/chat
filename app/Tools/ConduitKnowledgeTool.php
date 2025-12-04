<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\ConduitKnowledgeService;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Tool;

class ConduitKnowledgeTool extends Tool
{
    /**
     * Initialize the ConduitKnowledgeTool and configure it as the "search_knowledge" tool.
     *
     * @param ConduitKnowledgeService $knowledge Service used to perform knowledge searches.
     */
    public function __construct(
        private ConduitKnowledgeService $knowledge,
    ) {
        $this
            ->as('search_knowledge')
            ->for('Search the knowledge base. Call this ONCE, then respond to the user with the results. Do NOT call this tool multiple times.')
            ->withStringParameter('query', 'The search query - what information to look for')
            ->using($this->execute(...));
    }

    /**
     * Perform a knowledge search for the given query and return a user-facing result message.
     *
     * @param string $query The search query string to look up; must be at least 2 characters.
     * @return string A user-facing message: an error if the Conduit CLI is unavailable or the query is too short or an exception occurs; "No knowledge entries found..." when no matches exist; or a success message "SEARCH COMPLETE - Found N results..." followed by the results' context string on success.
     */
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