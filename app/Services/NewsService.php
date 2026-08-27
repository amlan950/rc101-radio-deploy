<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NewsService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = \App\Models\Api::getValue('news_api') ?: env('NEWS_API_KEY');
        $this->baseUrl = env('NewsService_BASE_URL', 'https://newsapi.org/v2');
    }

    /**
     * Get top 12 most relevant and recent pop culture news
     *
     * @return array
     */
    public function getPopCultureNews()
    {
        // Check if API key is available
        if (!$this->apiKey) {
            \Log::info('News API key not configured in NewsService');
            return [];
        }

        $cacheKey = 'pop_culture_news_' . date('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () {
            try {
                // Search specifically for pop music culture news
                $response = Http::withoutVerifying()->get($this->baseUrl . '/everything', [
                    'q' => '("pop music" OR "pop star" OR "pop artist" OR "pop singer" OR "pop album" OR "pop song" OR "pop chart" OR "pop hit" OR "Billboard" OR "music chart" OR "album release" OR "single release") AND (news OR interview OR release OR chart OR award OR concert OR tour)',
                    'sortBy' => 'publishedAt',
                    'language' => 'en',
                    'pageSize' => 12,
                    'apiKey' => $this->apiKey
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['articles']) && !empty($data['articles'])) {
                        return $this->formatArticles($data['articles']);
                    }
                }

                return [];
            } catch (\Exception $e) {
                \Log::error('News API Error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get top headlines from entertainment category
     *
     * @return array
     */
    public function getEntertainmentHeadlines()
    {
        // Check if API key is available
        if (!$this->apiKey) {
            \Log::info('News API key not configured in NewsService');
            return [];
        }

        $cacheKey = 'entertainment_headlines_' . date('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = Http::withoutVerifying()->get($this->baseUrl . '/top-headlines', [
                    'category' => 'entertainment',
                    'language' => 'en',
                    'pageSize' => 12,
                    'apiKey' => $this->apiKey
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['articles']) && !empty($data['articles'])) {
                        return $this->formatArticles($data['articles']);
                    }
                }

                return [];
            } catch (\Exception $e) {
                \Log::error('News API Error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Format articles for consistent output
     *
     * @param array $articles
     * @return array
     */
    private function formatArticles(array $articles)
    {
        $formatted = [];

        foreach ($articles as $article) {
            // Skip articles without essential data
            if (empty($article['title']) || empty($article['publishedAt'])) {
                continue;
            }

            $formatted[] = [
                'title' => $article['title'] ?? 'No title available',
                'description' => $article['description'] ?? 'No description available',
                'content' => $article['content'] ?? '',
                'author' => $article['author'] ?? 'Unknown author',
                'source' => $article['source']['name'] ?? 'Unknown source',
                'publishedAt' => $article['publishedAt'],
                'url' => $article['url'] ?? '',
                'urlToImage' => $article['urlToImage'] ?? null,
                'formattedDate' => $this->formatDate($article['publishedAt'])
            ];
        }

        return $formatted;
    }

    /**
     * Format date for display
     *
     * @param string $dateString
     * @return string
     */
    private function formatDate($dateString)
    {
        try {
            $date = new \DateTime($dateString);
            return $date->format('M j, Y g:i A');
        } catch (\Exception $e) {
            return 'Date not available';
        }
    }
}