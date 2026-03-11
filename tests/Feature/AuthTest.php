<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['success', 'data', 'message']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_fails_without_name(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Login via Sanctum stateful flow with Referer header
        $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertStatus(200);

        // Logout using same session
        $response = $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/logout');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Login via Sanctum stateful flow
        $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertStatus(200);

        // Access protected route in same session
        $response = $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->getJson('/api/user');

        $response->assertStatus(200)->assertJsonFragment(['email' => $user->email]);
    }
}
