<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSearchService
{
    private string $apiKey;

    private string $baseUrl = 'https://api.tavily.com';

    public function __construct()
    {
        $this->apiKey = (string) config('services.tavily.api_key', '');
    }

    /**
     * Check if web search is available.
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Search the web using Tavily API.
     *
     * @return array{success: bool, results: array<array{title: string, url: string, content: string}>, answer: ?string, error: ?string}
     */
    public function search(string $query, int $limit = 5): array
    {
        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'results' => [],
                'answer' => null,
                'error' => 'Tavily API key not configured',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/search", [
                    'api_key' => $this->apiKey,
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => $limit,
                    'include_answer' => true,
                    'include_raw_content' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('WebSearchService: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'results' => [],
                    'answer' => null,
                    'error' => 'Search request failed: '.$response->status(),
                ];
            }

            $data = $response->json();

            $results = [];
            foreach ($data['results'] ?? [] as $result) {
                $results[] = [
                    'title' => $result['title'] ?? 'Untitled',
                    'url' => $result['url'] ?? '',
                    'content' => $result['content'] ?? '',
                ];
            }

            return [
                'success' => true,
                'results' => $results,
                'answer' => $data['answer'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('WebSearchService: Exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'results' => [],
                'answer' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
