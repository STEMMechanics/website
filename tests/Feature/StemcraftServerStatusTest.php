<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StemcraftServerStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_status_endpoint_returns_offline_without_calling_external_endpoint_when_disabled(): void
    {
        Http::fake();

        $this->getJson(route('api.stemcraft.status'))
            ->assertOk()
            ->assertExactJson([
                'status' => 'offline',
                'players_online' => null,
                'max_players' => null,
                'version' => null,
                'server_address' => 'play.stemcraft.com.au',
                'message' => null,
                'checked_at' => null,
                'stale' => false,
            ]);

        Http::assertNothingSent();
    }

    public function test_status_endpoint_fetches_and_caches_safe_public_status(): void
    {
        $this->setOption('stemcraft.server-status.enabled', '1');
        $this->setOption('stemcraft.server-status.endpoint-url', 'https://status.example.test/status');
        $this->setOption('stemcraft.server-status.api-key', 'super-secret-key');
        $this->setOption('stemcraft.server-status.server-address', 'play.example.com');

        Http::fake([
            'https://status.example.test/status' => Http::response([
                'online' => true,
                'players_online' => 3,
                'max_players' => 20,
                'version' => '1.21.4',
                'maintenance' => false,
                'message' => null,
                'checked_at' => '2026-07-13T06:30:00+10:00',
                'players' => ['PlayerOne', 'PlayerTwo'],
            ]),
        ]);

        $this->getJson(route('api.stemcraft.status'))
            ->assertOk()
            ->assertJson([
                'status' => 'online',
                'players_online' => 3,
                'max_players' => 20,
                'version' => '1.21.4',
                'server_address' => 'play.example.com',
                'message' => null,
                'checked_at' => '2026-07-13T06:30:00+10:00',
                'stale' => false,
            ])
            ->assertDontSee('super-secret-key')
            ->assertDontSee('PlayerOne');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer super-secret-key'));
        $this->assertNotSame('super-secret-key', SiteOption::query()->where('name', 'stemcraft.server-status.api-key')->value('value'));

        $this->getJson(route('api.stemcraft.status'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_status_endpoint_returns_offline_when_refresh_fails(): void
    {
        $this->setOption('stemcraft.server-status.enabled', '1');
        $this->setOption('stemcraft.server-status.endpoint-url', 'https://status.example.test/status');
        $this->setOption('stemcraft.server-status.api-key', 'super-secret-key');

        Cache::forever('stemcraft.server-status.last-good', [
            'status' => 'online',
            'players_online' => 2,
            'max_players' => 20,
            'version' => '1.21.4',
            'server_address' => 'play.stemcraft.com.au',
            'message' => null,
            'checked_at' => '2026-07-13T06:30:00+10:00',
            'stale' => false,
        ]);
        Http::fake([
            'https://status.example.test/status' => Http::response(['error' => 'down'], 503),
        ]);

        $this->getJson(route('api.stemcraft.status'))
            ->assertOk()
            ->assertJson([
                'status' => 'offline',
                'players_online' => null,
                'max_players' => null,
                'version' => null,
                'stale' => false,
            ]);
    }

    public function test_status_api_key_is_not_rendered_in_admin_site_options(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        $this->setOption('stemcraft.server-status.api-key', 'super-secret-key');

        $this->actingAs($this->createAdminUser())
            ->get(route('admin.site_option.index', ['search' => 'stemcraft.server-status.api-key']))
            ->assertOk()
            ->assertSee('stemcraft.server-status.api-key')
            ->assertSee('••••••••', false)
            ->assertDontSee('super-secret-key');
    }

    public function test_blank_secret_update_keeps_existing_api_key(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        $option = SiteOption::query()
            ->where('name', 'stemcraft.server-status.api-key')
            ->firstOrFail();
        $option->value = SiteOption::encryptSecretValue('super-secret-key');
        $option->save();

        $this->actingAs($this->createAdminUser())
            ->putJson(route('admin.site_option.update', $option), ['value' => ''])
            ->assertOk()
            ->assertJsonPath('option.is_secret', true)
            ->assertJsonPath('option.value', '')
            ->assertJsonPath('option.has_value', true);

        $this->assertSame('super-secret-key', SiteOption::secretValue('stemcraft.server-status.api-key'));
    }

    private function setOption(string $name, string $value): void
    {
        if (SiteOption::isSecret($name)) {
            $value = SiteOption::encryptSecretValue($value);
        }

        SiteOption::query()->updateOrCreate(['name' => $name], ['value' => $value]);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'admin']);

        return $user;
    }
}
