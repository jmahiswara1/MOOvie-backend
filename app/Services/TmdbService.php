<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout = 5;

    public function __construct()
    {
        $this->baseUrl = config('tmdb.base_url', 'https://api.themoviedb.org/3');
        $this->apiKey = config('tmdb.api_key', '');
    }

    /**
     * Helper untuk handle request dengan timeout dan API key
     */
    private function makeRequest(string $endpoint, array $params = [])
    {
        $params['api_key'] = $this->apiKey;

        return Http::timeout($this->timeout)->get($this->baseUrl . $endpoint, $params);
    }

    /**
     * Fetch kategori spesifik atau genre
     */
    public function fetchCategory(string $category): array
    {
        $cacheKey = 'tmdb_category_' . $category;
        $fallbackKey = 'fallback_' . $category;

        // Try to get from fast cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Determine endpoint: category vs genre
            $endpoint = "/movie/{$category}";
            $params = [];

            // Map frontend category names to TMDB endpoints or genres
            if ($category === 'trending') {
                $endpoint = "/trending/movie/day";
            } elseif ($category === 'action') {
                $endpoint = "/discover/movie";
                $params['with_genres'] = 28;
            } elseif ($category === 'horror') {
                $endpoint = "/discover/movie";
                $params['with_genres'] = 27;
            } elseif ($category === 'comedy') {
                $endpoint = "/discover/movie";
                $params['with_genres'] = 35;
            }

            $response = $this->makeRequest($endpoint, $params);

            if ($response->failed()) {
                return Cache::get($fallbackKey, []);
            }

            $data = $response->json()['results'] ?? [];
            
            // Cache valid data for 2 hours
            Cache::put($cacheKey, $data, now()->addHours(2));
            // Update fallback cache for 24 hours
            Cache::put($fallbackKey, $data, now()->addDay());
            
            return $data;

        } catch (ConnectionException $e) {
            return Cache::get($fallbackKey, []);
        }
    }

    /**
     * Search movies by keyword
     */
    public function search(string $query, int $page = 1): array
    {
        if (empty(trim($query))) {
            return ['results' => [], 'total_pages' => 0, 'total_results' => 0];
        }

        $cacheKey = 'tmdb_search_' . md5($query . $page);
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $page) {
            try {
                $response = $this->makeRequest('/search/movie', [
                    'query' => $query,
                    'page' => $page,
                ]);

                if ($response->failed()) {
                    return ['results' => [], 'total_pages' => 0, 'total_results' => 0];
                }

                return $response->json();
            } catch (ConnectionException $e) {
                return ['results' => [], 'total_pages' => 0, 'total_results' => 0];
            }
        });
    }

    /**
     * Get detailed movie info including cast and trailers
     */
    public function getDetail(int $movieId): ?array
    {
        $cacheKey = 'tmdb_detail_' . $movieId;
        $fallbackKey = 'fallback_detail_' . $movieId;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->makeRequest("/movie/{$movieId}", [
                'append_to_response' => 'credits,videos'
            ]);

            if ($response->failed()) {
                return Cache::get($fallbackKey);
            }

            $data = $response->json();
            
            // Extract only what we need to save cache space
            $filteredData = [
                'id' => $data['id'],
                'title' => $data['title'],
                'overview' => $data['overview'],
                'poster_path' => $data['poster_path'],
                'backdrop_path' => $data['backdrop_path'],
                'release_date' => $data['release_date'],
                'vote_average' => $data['vote_average'],
                'genres' => $data['genres'],
                'runtime' => $data['runtime'],
                'status' => $data['status'],
                // Get top 5 cast members
                'cast' => array_slice($data['credits']['cast'] ?? [], 0, 5),
                // Get YouTube trailers
                'videos' => array_filter($data['videos']['results'] ?? [], function ($video) {
                    return $video['site'] === 'YouTube' && $video['type'] === 'Trailer';
                }),
            ];

            // Re-index videos array
            $filteredData['videos'] = array_values($filteredData['videos']);

            Cache::put($cacheKey, $filteredData, now()->addHours(2));
            Cache::put($fallbackKey, $filteredData, now()->addDay());

            return $filteredData;

        } catch (ConnectionException $e) {
            return Cache::get($fallbackKey);
        }
    }

    /**
     * Batch fetch basic movie info for watchlist display
     */
    public function getMoviesByIds(array $ids): array
    {
        $movies = [];
        foreach ($ids as $id) {
            $detail = $this->getDetail($id);
            if ($detail) {
                // Simplify for list view
                $movies[] = [
                    'id' => $detail['id'],
                    'title' => $detail['title'],
                    'poster_path' => $detail['poster_path'],
                    'vote_average' => $detail['vote_average'],
                    'release_date' => $detail['release_date'] ?? null,
                ];
            }
        }
        return $movies;
    }
}
