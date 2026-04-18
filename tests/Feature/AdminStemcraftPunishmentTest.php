<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftWebhookLog;
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
        $this->assertSame((string) $admin->id, (string) $penalty->by_user_id);
        $this->assertSame('Admin User', $penalty->by_username);
        $this->assertNull($penalty->lifted_at);

        $liftResponse = $this->actingAs($admin)->delete(route('admin.stemcraft.punishments.destroy', $penalty), [
            'lift_reason' => 'Appeal accepted',
        ]);

        $liftResponse->assertRedirect(route('admin.stemcraft.punishments.index'));
        $liftResponse->assertSessionHasNoErrors();

        $penalty->refresh();
        $this->assertNotNull($penalty->lifted_at);
        $this->assertSame((string) $admin->id, (string) $penalty->lifted_by_user_id);
        $this->assertSame('Admin User', $penalty->lifted_by_username);
        $this->assertSame('Appeal accepted', $penalty->lift_reason);

        $liftLog = MinecraftWebhookLog::query()
            ->where('event', 'player.penalty.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Appeal accepted', $liftLog->payload['lift_reason'] ?? null);
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

    public function test_admin_can_apply_a_warning_without_an_end_date(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174002',
            'username' => 'PlayerThree',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.punishments.store'), [
            'username' => 'PlayerThree',
            'type' => MinecraftPenalty::TYPE_WARN,
            'reason' => 'Please keep it civil',
            'ends_at' => '',
        ]);

        $response->assertRedirect(route('admin.stemcraft.punishments.index'));
        $response->assertSessionHasNoErrors();

        $penalty = MinecraftPenalty::query()->latest('id')->firstOrFail();
        $this->assertSame(MinecraftPenalty::TYPE_WARN, $penalty->type);
        $this->assertFalse($penalty->is_permanent);
        $this->assertNull($penalty->ends_at);
        $this->assertNull($penalty->duration_seconds);
    }

    public function test_punishments_page_uses_lift_label_in_confirmation_helper(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.punishments.index'));

        $response->assertOk();
        $this->assertStringContainsString(
            "SM.confirmDelete('{{ csrf_token() }}', 'Lift punishment?', 'This will notify the STEMCraft server to lift the active punishment.', \$el, 'Lift')",
            file_get_contents(resource_path('views/admin/stemcraft/punishments.blade.php')),
        );
    }

    public function test_admin_can_target_specific_account_when_usernames_match_across_platforms(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '123e4567-e89b-12d3-a456-426614174111',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);
        $targetAccount = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => '123e4567-e89b-12d3-a456-426614174222',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.punishments.store'), [
            'minecraft_account_id' => (string) $targetAccount->id,
            'username' => '',
            'type' => MinecraftPenalty::TYPE_MUTE,
            'reason' => 'Targeted correctly',
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('admin.stemcraft.punishments.index'));
        $response->assertSessionHasNoErrors();

        $penalty = MinecraftPenalty::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $targetAccount->id, (int) $penalty->minecraft_account_id);
        $this->assertSame(strtolower((string) $targetAccount->uuid), (string) $penalty->uuid);
        $this->assertSame((string) $targetAccount->username, (string) $penalty->username);
    }

    public function test_punishment_form_shows_platform_suffixes_in_username_suggestions(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '123e4567-e89b-12d3-a456-426614174333',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);
        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => '123e4567-e89b-12d3-a456-426614174444',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.punishments.index'));

        $response->assertOk();
        $response->assertSee('SharedName (java)');
        $response->assertSee('SharedName (bedrock)');
    }

    public function test_suffix_username_without_hidden_account_id_still_targets_matching_platform_account(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '123e4567-e89b-12d3-a456-426614174555',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);
        $targetAccount = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => '123e4567-e89b-12d3-a456-426614174666',
            'username' => 'SharedName',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.punishments.store'), [
            'minecraft_account_id' => '',
            'username' => 'SharedName (bedrock)',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Parsed from suffix',
            'ends_at' => '',
        ]);

        $response->assertRedirect(route('admin.stemcraft.punishments.index'));
        $response->assertSessionHasNoErrors();

        $penalty = MinecraftPenalty::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $targetAccount->id, (int) $penalty->minecraft_account_id);
        $this->assertSame(strtolower((string) $targetAccount->uuid), (string) $penalty->uuid);
        $this->assertSame((string) $targetAccount->username, (string) $penalty->username);
    }

    public function test_recent_punishment_history_shows_platform_next_to_player_name(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $account = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => '123e4567-e89b-12d3-a456-426614174777',
            'username' => 'HistoryPlayer',
            'is_whitelisted' => true,
        ]);

        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $account->id,
            'uuid' => strtolower((string) $account->uuid),
            'username' => (string) $account->username,
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'History test',
            'started_at' => now()->subDay(),
            'ends_at' => now()->subHours(2),
            'is_permanent' => false,
            'by_username' => '<server>',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.punishments.index'));

        $response->assertOk();
        $response->assertSee('HistoryPlayer');
        $response->assertSee('text-xs font-normal text-gray-500">(bedrock)</span>', false);
    }
}
