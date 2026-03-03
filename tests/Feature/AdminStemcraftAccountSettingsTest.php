<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStemcraftAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_index_shows_detached_accounts_by_default(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => 'java',
            'username' => 'DetachedPlayer',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.index'));

        $response->assertOk();
        $response->assertSee('DetachedPlayer');
    }

    public function test_admin_can_disable_whitelist_for_a_linked_account_without_removing_link(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $owner = User::factory()->create();
        $account = MinecraftAccount::query()->create([
            'user_id' => $owner->id,
            'platform' => 'java',
            'username' => 'LinkedPlayer',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.stemcraft.update', $account), [
            'user_id' => (string) $owner->id,
            'platform' => 'java',
            'username' => 'LinkedPlayer',
            'is_whitelisted' => '0',
            'admin_notes' => '',
        ]);

        $response->assertRedirect(route('admin.stemcraft.edit', $account));
        $response->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame((string) $owner->id, (string) $account->user_id);
        $this->assertFalse($account->is_whitelisted);
    }
}
