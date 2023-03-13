<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function testLogin()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
    
        // Test successful login
        $response = $this->postJson('/api/login', [
            'username' => $user->username,
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
                'username' => $user->username,
            ]
        ]);
    
        // Test logout
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/logout');
        $response->assertStatus(204);
    
        // Test failed login
        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(422);
    }
}
