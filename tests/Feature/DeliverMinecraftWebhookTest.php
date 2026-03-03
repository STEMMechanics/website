<?php

namespace Tests\Feature;

use App\Jobs\DeliverMinecraftWebhook;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use RuntimeException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliverMinecraftWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('minecraft_webhook_logs');
        Schema::dropIfExists('site_options');

        Schema::create('minecraft_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 20);
            $table->string('status', 20);
            $table->string('event', 120)->nullable();
            $table->string('delivery_id', 120)->nullable();
            $table->string('method', 12)->default('POST');
            $table->text('target_url')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('payload')->nullable();
            $table->longText('raw_body')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('retried_from_id')->nullable();
            $table->timestamps();
        });

        Schema::create('site_options', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('value');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('minecraft_webhook_logs');
        Schema::dropIfExists('site_options');

        parent::tearDown();
    }

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

        $job = new DeliverMinecraftWebhook('account.sync', [
            'account' => [
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
            $expectedSignature = MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret');

            return $request->url() === 'https://example.test/stemcraft/webhook'
                && $deliveryId === '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77'
                && hash_equals($expectedSignature, $signature);
        });

        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'account.sync',
            'delivery_id' => '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77',
            'status' => 'delivered',
            'response_status' => 200,
        ]);
    }

    public function test_delivery_job_marks_missing_configuration_as_pending_for_retry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('STEMCraft webhook URL or secret is not configured.');

        $job = new DeliverMinecraftWebhook('account.sync', [
            'account' => [
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
                'event' => 'account.sync',
                'delivery_id' => '11111111-2222-3333-4444-555555555555',
                'status' => 'pending',
                'attempt_count' => 1,
            ]);
        }
    }
}
