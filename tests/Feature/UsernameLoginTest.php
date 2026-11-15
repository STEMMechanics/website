<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Token;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

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

    public function test_unverified_workshop_requestor_can_start_login_flow_with_email(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'workshop-requestor@example.com',
        ]);
        $owner = User::factory()->create();
        $hero = Media::factory()->create([
            'user_id' => $owner->id,
            'mime_type' => 'image/png',
        ]);

        Workshop::query()->create([
            'title' => 'Requested Workshop',
            'hero_media_name' => $hero->name,
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'none',
            'user_id' => $owner->id,
            'requested_by_user_id' => $user->id,
        ]);

        $this->assertTrue($user->canUseEmailLogin());

        $previousGenericLogin = config('security.generic_login');
        config(['security.generic_login' => true]);

        try {
            $response = $this->withSession([
                'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
            ])->post(route('login.store'), [
                'login' => $user->email,
                'method' => 'email',
                'remember_email' => '0',
            ]);
        } finally {
            config(['security.generic_login' => $previousGenericLogin]);
        }

        $response->assertOk();
        $response->assertSee('Check your inbox');
        $this->assertDatabaseHas('tokens', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);
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
