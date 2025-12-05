<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\WebSearchService;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Tool;

class WebSearchTool extends Tool
{
    public function __construct(
        private WebSearchService $webSearch,
    ) {
        $this
            ->as('search_web')
            ->for('Search the internet for current information. Use this when the knowledge base has no results, or for real-time data like news, current events, or information about public figures/projects not in the personal knowledge base.')
            ->withStringParameter('query', 'The search query - what to search for on the web')
            ->using($this->execute(...));
    }

    /**
     * Check if web search is available.
     */
    public function isAvailable(): bool
    {
        return $this->webSearch->isAvailable();
    }

    public function execute(string $query): string
    {
        try {
            if (! $this->webSearch->isAvailable()) {
                return 'Error: Web search is not configured. Please add TAVILY_API_KEY to your environment.';
            }

            if (strlen($query) < 3) {
                return 'Error: Search query is too short. Please provide a more specific query.';
            }

            Log::info('WebSearchTool executing', ['query' => $query]);

            $result = $this->webSearch->search($query, 5);

            if (! $result['success']) {
                Log::warning('WebSearchTool search failed', ['error' => $result['error']]);

                return "Web search failed: {$result['error']}";
            }

            if (empty($result['results'])) {
                return "No web results found for '{$query}'.";
            }

            $output = "WEB SEARCH RESULTS:\n\n";

            // Include AI-generated answer if available
            if (! empty($result['answer'])) {
                $output .= "Summary: {$result['answer']}\n\n";
                $output .= "Sources:\n";
            }

            foreach ($result['results'] as $i => $item) {
                $num = $i + 1;
                $output .= "[{$num}] {$item['title']}\n";
                $output .= "    URL: {$item['url']}\n";
                $output .= "    {$item['content']}\n\n";
            }

            Log::info('WebSearchTool success', ['result_count' => count($result['results'])]);

            return $output;
        } catch (\Throwable $e) {
            Log::error('WebSearchTool exception', ['message' => $e->getMessage()]);

            return 'Error searching the web: '.$e->getMessage();
        }
    }
}
