<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MinecraftWebhookSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('site_options')) {
            Schema::create('site_options', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('minecraft_accounts')) {
            Schema::create('minecraft_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id')->nullable();
                $table->string('platform', 20);
                $table->string('uuid', 64)->nullable();
                $table->string('username', 80);
                $table->boolean('is_whitelisted')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamp('last_logout_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('minecraft_penalties')) {
            Schema::create('minecraft_penalties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('minecraft_account_id')->nullable();
                $table->string('external_id', 120)->nullable()->unique();
                $table->string('uuid', 64)->nullable();
                $table->string('username', 80);
                $table->string('type', 20);
                $table->text('reason')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_permanent')->default(false);
                $table->string('by_uuid', 64)->nullable();
                $table->string('by_username', 80)->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->string('lifted_by_uuid', 64)->nullable();
                $table->string('lifted_by_username', 80)->nullable();
                $table->timestamps();
            });
        }

        SiteOption::query()->where('name', 'minecraft.webhook-secret')->delete();
    }

    protected function tearDown(): void
    {
        SiteOption::query()->where('name', 'minecraft.webhook-secret')->delete();

        parent::tearDown();
    }

    public function test_minecraft_webhook_rejects_replayed_delivery_ids(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $payload = json_encode([
            'event' => 'player.profile.updated',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($payload);

        $timestamp = (string) time();
        $deliveryId = '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77';
        $signature = MinecraftSyncService::signPayload($payload, $timestamp, 'shared-secret');
        $headers = [
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
            'X-Minecraft-Delivery-Id' => $deliveryId,
        ];

        $firstResponse = $this->withHeaders($headers)->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));
        $firstResponse->assertOk()->assertJson(['ok' => true]);

        $secondResponse = $this->withHeaders($headers)->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));
        $secondResponse->assertStatus(409)->assertJson(['ok' => false, 'error' => 'replay_detected']);
    }

    public function test_minecraft_webhook_requires_a_delivery_id(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $payload = json_encode([
            'event' => 'player.profile.updated',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($payload);

        $timestamp = (string) time();
        $signature = MinecraftSyncService::signPayload($payload, $timestamp, 'shared-secret');

        $response = $this->withHeaders([
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
        ])->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));

        $response->assertStatus(409)->assertJson(['ok' => false, 'error' => 'replay_detected']);
    }

    public function test_minecraft_webhook_ignores_stale_penalty_create_after_lift_for_same_external_id(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $externalId = 'penalty-123';
        $liftedAt = now();
        $createdAt = now()->subMinute();

        $liftPayload = [
            'event' => 'player.penalty.lifted',
            'external_id' => $externalId,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'type' => 'ban',
            'occurred_at' => $liftedAt->toIso8601String(),
        ];
        $createPayload = [
            'event' => 'player.penalty.created',
            'external_id' => $externalId,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'type' => 'ban',
            'occurred_at' => $createdAt->toIso8601String(),
            'is_permanent' => true,
        ];

        $firstResponse = $this->postSignedMinecraftWebhook($liftPayload, '11111111-2222-3333-4444-555555555555');
        $firstResponse->assertOk()->assertJson(['ok' => true]);

        $secondResponse = $this->postSignedMinecraftWebhook($createPayload, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $secondResponse->assertOk()->assertJson(['ok' => true, 'ignored' => true]);

        $penalty = Model::resolveConnection()->table('minecraft_penalties')->where('external_id', $externalId)->first();
        $this->assertNotNull($penalty);
        $this->assertSame('ban', $penalty->type);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $penalty->uuid);
        $this->assertNotNull($penalty->lifted_at);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedMinecraftWebhook(array $payload, string $deliveryId)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($body);

        $timestamp = (string) time();
        $signature = MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret');

        return $this->withHeaders([
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
            'X-Minecraft-Delivery-Id' => $deliveryId,
        ])->postJson(route('webhook.stemcraft.server'), $payload);
    }
}
