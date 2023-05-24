<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;


    public function testLogin(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        // Test successful login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
        ]);
        $token = $response->json('token');

        // Test getting authenticated user
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->get('/api/me');
        $response->assertStatus(200);
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ]
        ]);

        // Test logout
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/logout');
        $response->assertStatus(204);

        // Test failed login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(422);
    }
}
