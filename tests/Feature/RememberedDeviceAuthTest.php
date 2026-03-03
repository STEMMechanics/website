<?php

namespace Tests\Feature;

use App\Models\Token;
use App\Models\User;
use App\Support\RememberedDeviceManager;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RememberedDeviceAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_login_form_prefills_email_from_cookie(): void
    {
        $response = $this->withCookie(RememberedDeviceManager::EMAIL_COOKIE, 'ipad@example.com')
            ->get(route('login'));

        $response->assertOk();
        $response->assertSee('ipad@example.com');
    }

    public function test_login_submit_with_remember_email_checked_sets_prefill_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'prefill@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession($this->trustedAltchaSessionPayload())
            ->post(route('login.store'), [
                'login' => $user->email,
                'remember_email' => '1',
            ]);

        $response->assertOk();
        $response->assertCookie(RememberedDeviceManager::EMAIL_COOKIE, $user->email);
    }

    public function test_login_token_flow_with_remember_email_sets_cookie_on_token_login(): void
    {
        $user = User::factory()->create([
            'email' => 'token-prefill@example.com',
            'email_verified_at' => now(),
        ]);

        $postResponse = $this->withSession($this->trustedAltchaSessionPayload())
            ->post(route('login.store'), [
                'login' => $user->email,
                'remember_email' => '1',
            ]);

        $postResponse->assertOk();

        $loginToken = Token::query()
            ->where('user_id', $user->id)
            ->where('type', 'login')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($loginToken);

        $tokenResponse = $this->get(route('login', ['token' => (string) $loginToken->id]));

        $tokenResponse->assertRedirect(route('index'));
        $tokenResponse->assertCookie(RememberedDeviceManager::EMAIL_COOKIE, $user->email);
    }

    public function test_login_submit_with_remember_email_unchecked_clears_prefill_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'forget@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->withCookie(RememberedDeviceManager::EMAIL_COOKIE, $user->email)
            ->withSession($this->trustedAltchaSessionPayload())
            ->post(route('login.store'), [
                'login' => $user->email,
                'remember_email' => '0',
            ]);

        $response->assertOk();
        $response->assertCookieExpired(RememberedDeviceManager::EMAIL_COOKIE);
    }

    public function test_login_route_auto_signs_in_with_valid_remembered_device_cookie(): void
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
            'data' => [
                'user_agent' => 'iPad Safari',
                'ip_address' => '127.0.0.1',
                'last_used_at' => now()->subDay()->toIso8601String(),
            ],
            'expires_at' => null,
        ]);

        $response = $this->withCookie(RememberedDeviceManager::DEVICE_COOKIE, $token->id)
            ->get(route('login'));

        $response->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_can_enable_and_remove_current_remembered_device(): void
    {
        $user = User::factory()->create();

        $updateResponse = $this->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => $user->email,
                'username' => $user->username,
                'keep_signed_in_device' => 'on',
            ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertCookie(RememberedDeviceManager::DEVICE_COOKIE);

        $token = Token::query()
            ->where('user_id', $user->id)
            ->where('type', RememberedDeviceManager::DEVICE_TOKEN_TYPE)
            ->first();

        $this->assertNotNull($token);
        $this->assertNull($token->expires_at);

        $destroyResponse = $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->withSession(['_token' => 'test-csrf-token'])
            ->delete(route('account.device.destroy', $token), [
                '_token' => 'test-csrf-token',
            ]);

        $destroyResponse->assertRedirect(route('account.show'));

        $this->assertDatabaseMissing('tokens', [
            'id' => $token->id,
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
        ]);
    }

    public function test_account_device_destroy_returns_json_for_ajax_request(): void
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
            'data' => [
                'user_agent' => 'iPad Safari',
                'ip_address' => '10.0.0.1',
                'last_used_at' => now()->toIso8601String(),
            ],
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->deleteJson(route('account.device.destroy', $token));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('account.show'),
        ]);

        $this->assertDatabaseMissing('tokens', [
            'id' => $token->id,
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
        ]);
    }

    public function test_account_device_nickname_update_returns_json_for_ajax_request(): void
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
            'data' => [
                'user_agent' => 'iPad Safari',
                'ip_address' => '10.0.0.1',
                'last_used_at' => now()->toIso8601String(),
            ],
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('account.device.nickname.update', $token), [
                'nickname' => "James's iPad",
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'nickname' => "James's iPad",
        ]);

        /** @var Token|null $updatedToken */
        $updatedToken = Token::query()->find($token->id);

        $this->assertNotNull($updatedToken);
        $this->assertSame("James's iPad", (string) (($updatedToken->data ?? [])['nickname'] ?? ''));
    }

    public function test_account_remembered_devices_displays_browser_when_available(): void
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
            'data' => [
                'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'ip_address' => '10.0.0.22',
                'last_used_at' => now()->toIso8601String(),
            ],
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Browser: Safari');
    }

    public function test_account_remembered_device_uses_ipad_hint_for_macintosh_user_agent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15')
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => $user->email,
                'username' => $user->username,
                'keep_signed_in_device' => 'on',
                'remembered_device_hint' => 'ipad',
                'remembered_device_touch_points' => '5',
            ])
            ->assertRedirect();

        $token = Token::query()
            ->where('user_id', $user->id)
            ->where('type', RememberedDeviceManager::DEVICE_TOKEN_TYPE)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($token);

        $response = $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('iPad');
    }

    public function test_account_remembered_device_nickname_can_be_saved_and_rendered(): void
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'type' => RememberedDeviceManager::DEVICE_TOKEN_TYPE,
            'data' => [
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15',
                'ip_address' => '10.0.0.88',
                'last_used_at' => now()->toIso8601String(),
            ],
            'expires_at' => null,
        ]);

        $nickname = "James's iPad";

        $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => $user->email,
                'username' => $user->username,
                'keep_signed_in_device' => 'on',
                'current_device_nickname' => $nickname,
                'remembered_device_nicknames' => [
                    (string) $token->id => $nickname,
                ],
            ])
            ->assertRedirect();

        /** @var Token|null $updatedToken */
        $updatedToken = Token::query()
            ->where('id', $token->id)
            ->first();

        $this->assertNotNull($updatedToken);
        $this->assertSame($nickname, (string) (($updatedToken->data ?? [])['nickname'] ?? ''));

        $response = $this->actingAs($user)
            ->withCookie(RememberedDeviceManager::DEVICE_COOKIE, (string) $token->id)
            ->get(route('account.show'));

        $response->assertOk();
        $response->assertSee($nickname);
    }

    public function test_account_update_validation_error_preserves_keep_signed_in_device_old_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('account.show'))
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => 'not-an-email',
                'username' => $user->username,
                'keep_signed_in_device' => 'on',
            ]);

        $response->assertRedirect(route('account.show'));
        $response->assertSessionHasErrors('email');
        $response->assertSessionHasInput('keep_signed_in_device', 'on');
    }

    /**
     * @return array<string, int>
     */
    private function trustedAltchaSessionPayload(): array
    {
        return [
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ];
    }
}
