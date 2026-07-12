<?php

namespace Tests\Feature;

use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        Token::query()->where('type', 'login')->delete();
        User::query()->where('email', 'member@example.com')->delete();
    }

    public function test_verified_user_can_start_login_flow_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member@example.com',
            'remember_email' => '0',
        ]);

        $response->assertOk();
        $response->assertSee('Check your inbox');

        $this->assertDatabaseHas('tokens', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);

        $this->assertNotNull(Token::query()->where('user_id', $user->id)->where('type', 'login')->first());
    }

    public function test_username_value_does_not_start_login_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member1',
            'remember_email' => '0',
        ]);

        $response->assertOk();
        $response->assertDontSee('Check your inbox');

        $this->assertDatabaseMissing('tokens', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);
    }

    public function test_verified_user_with_password_is_prompted_for_password_instead_of_email_link(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'email_verified_at' => now(),
            'password' => 'secret1234',
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member@example.com',
            'remember_email' => '0',
        ]);

        $response->assertOk();
        $response->assertSee('Enter your password');
        $response->assertSee('name="password"', false);
        $response->assertSee('Sign in another way');
        $response->assertSee(route('login'), false);

        $this->assertDatabaseMissing('tokens', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);
    }
}
