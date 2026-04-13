<?php

namespace Tests\Feature;

use App\Jobs\DeliverMinecraftWebhook;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeliverMinecraftWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_job_sends_signed_request_with_delivery_id(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/stemcraft/webhook',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake();

        $job = new DeliverMinecraftWebhook('player.profile.created', [
            'player' => [
                'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                'username' => 'PlayerOne',
                'platform' => 'java',
                'user_id' => 'user-1',
                'is_whitelisted' => true,
            ],
        ], '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77');

        $job->handle();

        Http::assertSent(function ($request): bool {
            $timestamp = (string) $request->header('X-Minecraft-Timestamp')[0];
            $signature = (string) $request->header('X-Minecraft-Signature')[0];
            $deliveryId = (string) $request->header('X-Minecraft-Delivery-Id')[0];
            $body = $request->body();
            $decoded = json_decode($body, true);
            $expectedSignature = MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret');

            return $request->url() === 'https://example.test/stemcraft/webhook'
                && $deliveryId === '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77'
                && is_array($decoded)
                && is_string($decoded['event_id'] ?? null)
                && trim((string) $decoded['event_id']) !== ''
                && is_string($decoded['occurred_at'] ?? null)
                && trim((string) $decoded['occurred_at']) !== ''
                && hash_equals($expectedSignature, $signature);
        });

        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'player.profile.created',
            'delivery_id' => '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77',
            'status' => 'delivered',
            'response_status' => 200,
        ]);

        $payload = \App\Models\MinecraftWebhookLog::query()
            ->where('delivery_id', '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77')
            ->value('payload');

        $this->assertIsArray($payload);
        $this->assertIsString($payload['event_id'] ?? null);
        $this->assertIsString($payload['occurred_at'] ?? null);
    }

    public function test_delivery_job_maps_legacy_penalty_lifted_event_to_supported_updated_event(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/stemcraft/webhook',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake();

        $job = new DeliverMinecraftWebhook('player.penalty.lifted', [
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'type' => 'ban',
            'occurred_at' => now()->toIso8601String(),
        ], 'abcdabcd-abcd-abcd-abcd-abcdabcdabcd');

        $job->handle();

        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return is_array($decoded)
                && ($decoded['event'] ?? null) === 'player.penalty.updated';
        });

        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'player.penalty.updated',
            'delivery_id' => 'abcdabcd-abcd-abcd-abcd-abcdabcdabcd',
            'status' => 'delivered',
        ]);
    }

    public function test_delivery_job_marks_missing_configuration_as_pending_for_retry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('STEMCraft webhook URL or secret is not configured.');

        $job = new DeliverMinecraftWebhook('player.profile.created', [
            'player' => [
                'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                'username' => 'PlayerOne',
                'platform' => 'java',
                'user_id' => 'user-1',
                'is_whitelisted' => true,
            ],
        ], '11111111-2222-3333-4444-555555555555');

        try {
            $job->handle();
        } finally {
            $this->assertDatabaseHas('minecraft_webhook_logs', [
                'direction' => 'outbound',
                'event' => 'player.profile.created',
                'delivery_id' => '11111111-2222-3333-4444-555555555555',
                'status' => 'pending',
                'attempt_count' => 1,
            ]);
        }
    }

    public function test_delivery_job_normalizes_occurred_at_to_utc_zulu(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/stemcraft/webhook',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake();

        $job = new DeliverMinecraftWebhook('player.profile.deleted', [
            'occurred_at' => '2026-03-05T10:42:27+10:00',
            'player' => [
                'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                'username' => 'PlayerOne',
                'platform' => 'java',
                'occurred_at' => '2026-03-05T10:42:27+10:00',
            ],
        ], 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        $job->handle();

        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);
            $occurredAt = (string) ($decoded['occurred_at'] ?? '');
            $playerOccurredAt = (string) ($decoded['player']['occurred_at'] ?? '');

            return is_array($decoded)
                && str_ends_with($occurredAt, 'Z')
                && str_ends_with($playerOccurredAt, 'Z');
        });
    }

    public function test_delivery_job_uses_a_fresh_delivery_id_for_retries(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/stemcraft/webhook',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake();

        $job = new class('player.profile.created', [
            'player' => [
                'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                'username' => 'PlayerOne',
                'platform' => 'java',
                'is_whitelisted' => true,
            ],
        ], '99999999-8888-7777-6666-555555555555') extends DeliverMinecraftWebhook
        {
            public function attempts(): int
            {
                return 2;
            }
        };

        $retryDeliveryId = null;

        $job->handle();

        Http::assertSent(function ($request) use (&$retryDeliveryId): bool {
            $retryDeliveryId = (string) ($request->header('X-Minecraft-Delivery-Id')[0] ?? '');

            return $retryDeliveryId !== '';
        });

        $this->assertNotSame('99999999-8888-7777-6666-555555555555', $retryDeliveryId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $retryDeliveryId
        );
        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'player.profile.created',
            'delivery_id' => $retryDeliveryId,
            'status' => 'delivered',
            'response_status' => 200,
        ]);
    }
}
