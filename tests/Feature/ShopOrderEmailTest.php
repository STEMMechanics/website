<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\StoreOrderConfirmation;
use App\Mail\StoreOrderPaid;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ShopOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_queues_confirmation_email(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 0,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ]);

        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'billing_phone' => '0400654321',
            'shipping_country' => 'Australia',
        ])->assertRedirect();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'jamie@example.com'
                && $job->mailable instanceof StoreOrderConfirmation;
        });
    }

    public function test_successful_payment_queues_paid_email(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createPayment')->once()->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1995],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ]);

        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Morgan Example',
            'billing_email' => 'morgan@example.com',
            'billing_phone' => '0400555444',
            'shipping_country' => 'Australia',
        ])->assertRedirect(route('shop.checkout.payment'));

        $this->assertSame(0, StoreOrder::query()->count());

        Queue::fake();

        $response = $this->post(route('shop.checkout.payment.process'), [
            'source_id' => 'cnon:card-nonce-ok',
        ]);

        $order = StoreOrder::query()->firstOrFail();

        $response->assertRedirect(route('shop.order.show', [
            'storeOrder' => $order,
            'accessToken' => $order->access_token,
        ]));

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'morgan@example.com'
                && $job->mailable instanceof StoreOrderPaid;
        });
    }
}
