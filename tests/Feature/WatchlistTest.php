<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_to_watchlist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/watchlist/toggle', [
            'movie_id' => 12345,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.action', 'added');
        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
            'movie_id' => 12345,
        ]);
    }

    public function test_authenticated_user_can_remove_from_watchlist(): void
    {
        $user = User::factory()->create();
        $user->watchlists()->create(['movie_id' => 12345]);

        $response = $this->actingAs($user)->postJson('/api/watchlist/toggle', [
            'movie_id' => 12345,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.action', 'removed');
        $this->assertDatabaseMissing('watchlists', [
            'user_id' => $user->id,
            'movie_id' => 12345,
        ]);
    }

    public function test_guest_cannot_access_watchlist(): void
    {
        $this->getJson('/api/watchlist')->assertStatus(401);
    }

    public function test_guest_cannot_toggle_watchlist(): void
    {
        $this->postJson('/api/watchlist/toggle', [
            'movie_id' => 12345,
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_get_watchlist_ids(): void
    {
        $user = User::factory()->create();
        $user->watchlists()->create(['movie_id' => 111]);
        $user->watchlists()->create(['movie_id' => 222]);

        $response = $this->actingAs($user)->getJson('/api/watchlist/ids');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['data' => [111, 222]]);
    }

    public function test_authenticated_user_can_get_paginated_watchlist(): void
    {
        $user = User::factory()->create();
        $user->watchlists()->create(['movie_id' => 99999]);

        $response = $this->actingAs($user)->getJson('/api/watchlist');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_toggle_requires_movie_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/watchlist/toggle', []);

        $response->assertStatus(422);
    }
}
