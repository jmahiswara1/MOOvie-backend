<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test
        Cache::flush();
    }

    public function test_can_search_movies(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'page' => 1,
                'results' => [
                    ['id' => 1, 'title' => 'Test Movie']
                ],
                'total_pages' => 1,
                'total_results' => 1,
            ], 200)
        ]);

        $response = $this->getJson('/api/movies/search?query=Test');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Test Movie')
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_search_with_empty_query_returns_empty_array(): void
    {
        $response = $this->getJson('/api/movies/search?query=');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_results', 0);
    }

    public function test_can_get_movie_detail(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/123*' => Http::response([
                'id' => 123,
                'title' => 'Detail Movie',
                'overview' => 'Test overview',
                'poster_path' => '/test.jpg',
                'backdrop_path' => '/bg.jpg',
                'release_date' => '2023-01-01',
                'vote_average' => 8.5,
                'genres' => [['id' => 28, 'name' => 'Action']],
                'runtime' => 120,
                'status' => 'Released',
                'credits' => [
                    'cast' => [
                        ['id' => 1, 'name' => 'Actor 1'],
                        ['id' => 2, 'name' => 'Actor 2'],
                    ]
                ],
                'videos' => [
                    'results' => [
                        ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'abcdefg'],
                        ['site' => 'Vimeo', 'type' => 'Trailer', 'key' => '12345'], // Should be filtered out
                    ]
                ]
            ], 200)
        ]);

        $response = $this->getJson('/api/movies/detail/123');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Detail Movie')
            ->assertJsonCount(2, 'data.cast')
            ->assertJsonCount(1, 'data.videos') // Only YouTube Trailer
            ->assertJsonPath('data.videos.0.key', 'abcdefg');
    }

    public function test_detail_returns_404_if_tmdb_fails(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/999*' => Http::response(null, 404)
        ]);

        $response = $this->getJson('/api/movies/detail/999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_can_get_movies_by_category(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/popular*' => Http::response([
                'results' => [
                    ['id' => 1, 'title' => 'Popular Movie']
                ]
            ], 200)
        ]);

        $response = $this->getJson('/api/movies/popular');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Popular Movie');
    }

    public function test_genre_category_uses_discover_endpoint(): void
    {
        Http::fake([
            'api.themoviedb.org/3/discover/movie*with_genres=28*' => Http::response([
                'results' => [
                    ['id' => 1, 'title' => 'Action Movie']
                ]
            ], 200)
        ]);

        $response = $this->getJson('/api/movies/action');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Action Movie');
    }

    public function test_invalid_category_returns_422(): void
    {
        $response = $this->getJson('/api/movies/invalid-category');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kategori tidak valid');
    }

    public function test_fallback_mechanism_returns_cached_data_on_tmdb_failure(): void
    {
        // First request succeeds, populates fallback cache
        Http::fake([
            'api.themoviedb.org/3/trending/movie/day*' => Http::response([
                'results' => [
                    ['id' => 1, 'title' => 'Trending Movie']
                ]
            ], 200)
        ]);

        // Access via TmdbService to ensure cache is populated
        $service = new \App\Services\TmdbService();
        $service->fetchCategory('trending');

        // Clear only the main cache, leave the fallback cache
        Cache::forget('tmdb_category_trending');

        // Second request fails, but should return data from fallback cache
        Http::fake([
            'api.themoviedb.org/3/trending/movie/day*' => Http::response(null, 500)
        ]);

        $response = $this->getJson('/api/movies/trending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Trending Movie');
    }

    public function test_rate_limiting_returns_429(): void
    {
        // Mock successful response
        Http::fake([
            'api.themoviedb.org/3/movie/popular*' => Http::response([
                'results' => []
            ], 200)
        ]);

        // Make 60 requests (the limit)
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/movies/popular');
        }

        // The 61st request should be rate limited
        $response = $this->getJson('/api/movies/popular');

        $response->assertStatus(429);
    }
}
