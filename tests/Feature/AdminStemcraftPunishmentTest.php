<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftPenalty;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStemcraftPunishmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_apply_and_lift_a_mute_from_punishments_page(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $account = MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
        ]);

        $storeResponse = $this->actingAs($admin)->post(route('admin.stemcraft.punishments.store'), [
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_MUTE,
            'reason' => 'Cooling off period',
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $storeResponse->assertRedirect(route('admin.stemcraft.punishments.index'));
        $storeResponse->assertSessionHasNoErrors();

        $penalty = MinecraftPenalty::query()->firstOrFail();
        $this->assertSame((int) $account->id, (int) $penalty->minecraft_account_id);
        $this->assertSame(MinecraftPenalty::TYPE_MUTE, $penalty->type);
        $this->assertSame('Cooling off period', $penalty->reason);
        $this->assertNull($penalty->lifted_at);

        $liftResponse = $this->actingAs($admin)->delete(route('admin.stemcraft.punishments.destroy', $penalty));

        $liftResponse->assertRedirect(route('admin.stemcraft.punishments.index'));
        $liftResponse->assertSessionHasNoErrors();

        $penalty->refresh();
        $this->assertNotNull($penalty->lifted_at);
        $this->assertSame('Admin User', $penalty->lifted_by_username);
    }

    public function test_blank_end_date_creates_a_permanent_ban(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174001',
            'username' => 'PlayerTwo',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.punishments.store'), [
            'username' => 'PlayerTwo',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Serious misconduct',
            'ends_at' => '',
        ]);

        $response->assertRedirect(route('admin.stemcraft.punishments.index'));
        $response->assertSessionHasNoErrors();

        $penalty = MinecraftPenalty::query()->latest('id')->firstOrFail();
        $this->assertSame(MinecraftPenalty::TYPE_BAN, $penalty->type);
        $this->assertTrue($penalty->is_permanent);
        $this->assertNull($penalty->ends_at);
        $this->assertNull($penalty->duration_seconds);
    }
}
