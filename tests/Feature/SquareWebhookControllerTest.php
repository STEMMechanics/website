<?php

namespace Tests\Feature;

use App\Models\SquareWebhookEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SquareWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('square_webhook_events')) {
            Schema::create('square_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('event_id', 120)->unique();
                $table->string('event_type', 120)->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_square_webhook_duplicate_delivery_returns_ok_without_throwing(): void
    {
        config([
            'services.square.enabled' => true,
            'services.square.webhook_signature_key' => 'shared-secret',
            'services.square.webhook_url' => route('webhook.square'),
        ]);

        $payload = [
            'merchant_id' => 'MLKBKRDGS75GS',
            'location_id' => 'L20D3W6YMP7YY',
            'type' => 'payout.paid',
            'event_id' => 'ef9a47b0-80eb-362f-b4f9-781f4b0b0173',
            'created_at' => '2026-04-15T02:07:54.384Z',
            'data' => [
                'type' => 'payout',
                'id' => 'po_0602998a-91e6-45c4-8886-569e71bfee3f',
                'object' => [
                    'payout' => [
                        'id' => 'po_0602998a-91e6-45c4-8886-569e71bfee3f',
                        'status' => 'PAID',
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($body);

        $signature = $this->signSquareWebhookPayload($body, (string) config('services.square.webhook_url'));
        $server = [
            'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $firstResponse = $this->call('POST', '/webhooks/square', [], [], [], $server, $body);
        $firstResponse->assertOk()->assertJson(['ok' => true]);

        $secondResponse = $this->call('POST', '/webhooks/square', [], [], [], $server, $body);
        $secondResponse->assertOk()->assertJson([
            'ok' => true,
            'duplicate' => true,
        ]);

        $this->assertSame(1, SquareWebhookEvent::query()->count());
    }

    private function signSquareWebhookPayload(string $payload, string $requestUrl): string
    {
        return base64_encode(hash_hmac('sha256', $requestUrl.$payload, (string) config('services.square.webhook_signature_key'), true));
    }
}
