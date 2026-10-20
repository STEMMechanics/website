<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\PushDeliveryService;
use Base64Url\Base64Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Tests\TestCase;

class PushDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function device(): PushDevice
    {
        $vapid = VAPID::createVapidKeys();
        config([
            'webpush.subject' => 'https://example.com',
            'webpush.public_key' => $vapid['publicKey'],
            'webpush.private_key' => $vapid['privateKey'],
        ]);
        $browser = VAPID::createVapidKeys();
        $endpoint = 'https://web.push.apple.com/test-subscription';

        return PushDevice::create([
            'user_id' => User::factory()->create()->id,
            'device_id' => (string) Str::uuid(), 'name' => 'Test browser', 'enabled' => true,
            'endpoint_hash' => hash('sha256', $endpoint),
            'subscription' => [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => $browser['publicKey'], 'auth' => Base64Url::encode(random_bytes(16))],
            ],
        ]);
    }

    private function delivery(Response|ConnectException $response, array &$history): PushDeliveryService
    {
        $handler = HandlerStack::create(new MockHandler([$response]));
        $handler->push(Middleware::history($history));
        $this->app->instance(ClientInterface::class, new Client(['handler' => $handler]));

        return $this->app->make(PushDeliveryService::class);
    }

    public static function encodings(): array
    {
        return ['existing browser subscription' => [null, 'aesgcm', 'WebPush '], 'explicit modern encoding' => ['aes128gcm', 'aes128gcm', 'vapid t=']];
    }

    #[DataProvider('encodings')]
    public function test_delivery_encrypts_and_signs_the_notification_through_the_psr_client(?string $encoding, string $expectedEncoding, string $authorizationPrefix): void
    {
        $device = $this->device();
        if ($encoding !== null) {
            $device->update(['subscription' => $device->subscription + ['contentEncoding' => $encoding]]);
        }
        $history = [];
        $delivery = $this->delivery(new Response(201), $history);

        $this->assertTrue($delivery->send($device, 'Workshop reminder', '/admin/workshops', 'workshop-test'));
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($device->subscription['endpoint'], (string) $request->getUri());
        $this->assertSame('3600', $request->getHeaderLine('TTL'));
        $this->assertSame($expectedEncoding, $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith($authorizationPrefix, $request->getHeaderLine('Authorization'));
        $this->assertGreaterThan(100, $request->getBody()->getSize());
        $this->assertStringNotContainsString('Workshop reminder', (string) $request->getBody());
        $this->assertTrue($device->fresh()->enabled);
        $this->assertNotNull($device->fresh()->subscription);
    }

    public static function failureStatuses(): array
    {
        return ['not found' => [404, true], 'gone' => [410, true], 'rate limited' => [429, false], 'unavailable' => [503, false]];
    }

    #[DataProvider('failureStatuses')]
    public function test_http_failures_disable_only_expired_subscriptions(int $status, bool $expired): void
    {
        $device = $this->device();
        $history = [];
        if (! $expired) {
            Log::shouldReceive('warning')->once()->with('Push delivery failed', ['device_id' => $device->id, 'status' => $status]);
        }

        $this->assertFalse($this->delivery(new Response($status), $history)->send($device, 'Test', '/account', 'test'));
        $this->assertCount(1, $history);
        $device->refresh();
        $this->assertSame(! $expired, $device->enabled);
        if ($expired) {
            $this->assertNull($device->subscription);
            $this->assertNull($device->endpoint_hash);
        } else {
            $this->assertNotNull($device->subscription);
            $this->assertNotNull($device->endpoint_hash);
        }
    }

    public function test_transport_failure_preserves_the_subscription_for_a_later_retry(): void
    {
        $device = $this->device();
        $history = [];
        $failure = new ConnectException('Connection timed out', new Request('POST', $device->subscription['endpoint']));
        Log::shouldReceive('warning')->once()->with('Push delivery failed', ['device_id' => $device->id, 'status' => null]);

        $this->assertFalse($this->delivery($failure, $history)->send($device, 'Test', '/account', 'test'));
        $this->assertCount(1, $history);
        $this->assertTrue($device->fresh()->enabled);
        $this->assertNotNull($device->fresh()->subscription);
    }
}
