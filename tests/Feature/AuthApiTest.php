<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Tests the login, user retrieval, and logout functionality of the Auth API.
     *
     * This test performs the following steps:
     * 1. Creates a new user using a factory.
     * 2. Attempts a successful login with the correct credentials,
     *    checks for a 200 status code, and verifies the structure of the returned token.
     * 3. Retrieves the authenticated user's data using the token,
     *    checks for a 200 status code, and verifies the returned user data.
     * 4. Logs out the authenticated user using the token and checks for a 204 status code.
     * 5. Attempts a failed login with incorrect credentials and checks for a 422 status code.
     *
     * @return void
     */
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
