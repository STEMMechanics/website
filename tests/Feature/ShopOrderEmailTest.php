<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\StoreOrderAdminNotification;
use App\Mail\StoreOrderAdminUpdateNotice;
use App\Mail\StoreOrderConfirmation;
use App\Mail\StoreOrderCustomerUpdateNotice;
use App\Mail\StoreOrderPaid;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ShopOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_queues_confirmation_email(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

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
                && $job->mailable instanceof StoreOrderConfirmation
                && $job->mailable->hasInvoiceAttachment === false
                && $job->mailable->receiptAttachmentCount === 0;
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreOrderAdminNotification
                && $job->mailable->notificationType === 'created';
        });
    }

    public function test_successful_payment_queues_paid_email(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1995
                && str_contains($idempotencyKey, '-amt-1995')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
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
        /** @phpstan-ignore-next-line */
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

        $response = $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Morgan Example',
            'billing_email' => 'morgan@example.com',
            'billing_phone' => '0400555444',
            'shipping_country' => 'Australia',
            'source_id' => 'cnon:card-nonce-ok',
        ]);

        /** @var StoreOrder $order */
        $order = StoreOrder::query()->firstOrFail();

        $response->assertRedirect(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));
        $response->assertSessionHas(
            'message',
            'Payment completed successfully. Your order email and receipt have been emailed.'
        );

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($order): bool {
            return $job->to === 'morgan@example.com'
                && $job->mailable instanceof StoreOrderPaid
                && $job->mailable->hasInvoiceAttachment === true
                && $job->mailable->receiptAttachmentCount === 1
                && str_contains($job->mailable->orderUrl, '/tracking/'.$order->access_token);
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreOrderAdminNotification
                && $job->mailable->notificationType === 'paid';
        });
    }

    public function test_store_order_emails_render_expected_delivery_copy_and_tracking_links(): void
    {
        /** @var StoreOrder $order */
        $order = StoreOrder::factory()->create([
            'order_number' => '381465',
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Morgan Example',
            'billing_email' => 'morgan@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'shipments' => [
                    [
                        'title' => 'Shipment 1: Ships now',
                        'title_primary' => 'Shipment 1: Ships now',
                        'title_meta' => null,
                        'dispatch_label' => 'Ships now',
                        'delivery_estimate_label' => '3-5 business days',
                        'amount' => 28.90,
                        'items' => [
                            [
                                'display_title' => 'Microbit',
                                'quantity' => 5,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Shipment 2: Ships later - Estimated May 1st 2026',
                        'title_primary' => 'Shipment 2: Ships later',
                        'title_meta' => 'Estimated May 1st 2026',
                        'dispatch_label' => 'Ships later from approximately May 1st 2026.',
                        'delivery_estimate_label' => '3-5 business days',
                        'amount' => 0,
                        'items' => [
                            [
                                'display_title' => 'Microbit',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'quantity' => 6,
            'available_now_quantity' => 5,
            'delayed_quantity' => 1,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-05-01',
            'line_total_amount' => 183.00,
        ]);

        $customerMail = new StoreOrderPaid(
            $order,
            'https://test.stemmechanics.com.au/tracking/'.$order->access_token,
        );
        $adminMail = new StoreOrderAdminNotification(
            $order,
            'https://test.stemmechanics.com.au/admin/store/orders/'.$order->order_number,
            'paid',
        );

        $customerRendered = $customerMail->render();
        $adminRendered = $adminMail->render();

        $this->assertStringContainsString('Preparing Order', $customerRendered);
        $this->assertStringContainsString('Delivery 1', $customerRendered);
        $this->assertStringContainsString('Delivery 2', $customerRendered);
        $this->assertStringContainsString('Ships now, Estimated arrival: 3-5 business days', $customerRendered);
        $this->assertStringContainsString('Shipping estimated May 1st 2026, Estimated arrival: 3-5 business days after dispatch', $customerRendered);
        $this->assertStringContainsString('Estimated arrival: 3-5 business days after dispatch', $customerRendered);
        $this->assertStringContainsString('Microbit x 5', $customerRendered);
        $this->assertStringContainsString('Microbit x 1', $customerRendered);
        $this->assertStringContainsString('3-5 business days', $customerRendered);
        $this->assertStringContainsString('/tracking/'.$order->access_token, $customerRendered);
        $this->assertStringNotContainsString('Split shipment:', $customerRendered);
        $this->assertStringNotContainsString('5 shipping now, 1 shipping later', $customerRendered);

        $this->assertStringContainsString('Preparing Order', $adminRendered);
        $this->assertStringContainsString('Delivery 1', $adminRendered);
        $this->assertStringContainsString('Microbit x 5', $adminRendered);
        $this->assertStringContainsString('Microbit x 1', $adminRendered);
    }

    public function test_immediate_order_update_notice_mailables_render_their_intro_copy(): void
    {
        $customerMail = new StoreOrderCustomerUpdateNotice('Morgan Example', [[
            'order_number' => '381465',
            'status_label' => 'Shipped',
            'notification_type' => 'shipped',
            'order_url' => 'https://test.stemmechanics.com.au/tracking/example-token',
            'updates' => [],
        ]]);
        $adminMail = new StoreOrderAdminUpdateNotice([[
            'order_number' => '381465',
            'status_label' => 'Shipped',
            'notification_type' => 'shipped',
            'admin_url' => 'https://test.stemmechanics.com.au/admin/store/orders/381465',
            'customer_name' => 'Morgan Example',
            'customer_email' => 'morgan@example.com',
            'updates' => [],
        ]]);

        $customerRendered = $customerMail->render();
        $adminRendered = $adminMail->render();

        $this->assertSame('Your order 381465 has now shipped', $customerMail->subjectLine);
        $this->assertStringContainsString('Your order, 381465, has now shipped.', $customerRendered);
        $this->assertStringContainsString('View Order', $customerRendered);
        $this->assertStringContainsString('https://test.stemmechanics.com.au/tracking/example-token', $customerRendered);

        $this->assertStringContainsString('Store Order Shipped', $adminRendered);
        $this->assertStringContainsString('A store order has now shipped.', $adminRendered);
        $this->assertStringContainsString('Open in Admin', $adminRendered);
        $this->assertStringContainsString('https://test.stemmechanics.com.au/admin/store/orders/381465', $adminRendered);
    }

    public function test_immediate_order_update_email_views_fall_back_when_intro_copy_is_missing(): void
    {
        $customerMail = new class('Morgan Example', [[
            'order_number' => '381465',
            'status_label' => 'Shipped',
            'notification_type' => 'shipped',
            'order_url' => 'https://test.stemmechanics.com.au/tracking/example-token',
            'updates' => [],
        ]]) extends Mailable
        {
            public function __construct(
                public string $recipientName,
                public array $orders,
            ) {}

            public function build(): static
            {
                return $this
                    ->subject('Test')
                    ->markdown('emails.store-order-customer-update-notice');
            }
        };

        $adminMail = new class([[
            'order_number' => '381465',
            'status_label' => 'Shipped',
            'notification_type' => 'shipped',
            'admin_url' => 'https://test.stemmechanics.com.au/admin/store/orders/381465',
            'customer_name' => 'Morgan Example',
            'customer_email' => 'morgan@example.com',
            'updates' => [],
        ]]) extends Mailable
        {
            public function __construct(
                public array $orders,
            ) {}

            public function build(): static
            {
                return $this
                    ->subject('Test')
                    ->markdown('emails.store-order-admin-update-notice');
            }
        };

        $customerRendered = $customerMail->render();
        $adminRendered = $adminMail->render();

        $this->assertStringContainsString('Your order, 381465, has now shipped.', $customerRendered);
        $this->assertStringContainsString('Store Order Shipped', $adminRendered);
        $this->assertStringContainsString('A store order has now shipped.', $adminRendered);
    }

    public function test_ready_for_pickup_customer_update_notice_uses_tailored_body_copy(): void
    {
        $customerMail = new StoreOrderCustomerUpdateNotice('Morgan Example', [[
            'order_number' => '381468',
            'status_label' => 'Ready for Pickup',
            'notification_type' => 'ready_for_pickup',
            'order_url' => 'https://test.stemmechanics.com.au/tracking/pickup-token',
            'item_sections' => [
                [
                    'heading' => 'Ready for pickup now',
                    'items' => [
                        [
                            'title' => 'Microbit Base',
                            'quantity' => 2,
                            'detail' => null,
                        ],
                    ],
                ],
                [
                    'heading' => 'Still expected later',
                    'items' => [
                        [
                            'title' => 'Microbit Base',
                            'quantity' => 3,
                            'detail' => 'Expected availability April 20th 2026',
                        ],
                    ],
                ],
            ],
            'updates' => [[
                'type' => 'status_changed',
                'time' => '11:34 am',
                'summary' => 'The order is now ready for pickup.',
                'detail' => 'Previously: Preparing Order.',
            ]],
        ]]);

        $rendered = $customerMail->render();

        $this->assertSame('Your order 381468 is now ready for pickup', $customerMail->subjectLine);
        $this->assertStringContainsString('Your order, 381468, is now ready for pickup.', $rendered);
        $this->assertStringContainsString('To arrange a suitable collection time, please contact James on 0400 130 190.', $rendered);
        $this->assertStringContainsString('Ready for pickup now', $rendered);
        $this->assertStringContainsString('Still expected later', $rendered);
        $this->assertStringContainsString('Microbit Base', $rendered);
        $this->assertStringContainsString('Expected availability April 20th 2026', $rendered);
        $this->assertStringNotContainsString('### Still expected', $rendered);
        $this->assertStringContainsString('View Order', $rendered);
    }

    public function test_shipped_customer_update_notice_renders_shared_delivery_detail_once(): void
    {
        $customerMail = new StoreOrderCustomerUpdateNotice('Jack Example', [[
            'order_number' => '381474',
            'status_label' => 'Shipped',
            'notification_type' => 'shipped',
            'order_url' => 'https://test.stemmechanics.com.au/tracking/shipped-token',
            'item_sections' => [
                [
                    'heading' => 'All items shipped',
                    'detail' => 'Shipped March 15th 2026 | Estimated arrival: 3-7 business days | Australia Post',
                    'detail_parts' => [
                        [
                            'prefix' => null,
                            'text' => 'Shipped March 15th 2026',
                            'url' => null,
                        ],
                        [
                            'prefix' => null,
                            'text' => 'Estimated arrival: 3-7 business days',
                            'url' => null,
                        ],
                        [
                            'prefix' => null,
                            'text' => 'Australia Post',
                            'url' => null,
                        ],
                        [
                            'prefix' => 'Tracking ',
                            'text' => 'ABC123',
                            'url' => 'https://tracking.example.test/ABC123',
                        ],
                    ],
                    'items' => [
                        [
                            'title' => 'Microbit',
                            'quantity' => 1,
                            'detail' => null,
                        ],
                        [
                            'title' => 'Pinball template',
                            'quantity' => 1,
                            'detail' => null,
                        ],
                    ],
                ],
            ],
            'updates' => [],
        ]]);

        $rendered = $customerMail->render();

        $this->assertStringContainsString('All items shipped', $rendered);
        $this->assertStringContainsString('font-size: 13px', $rendered);
        $this->assertStringContainsString('Shipped March 15th 2026', $rendered);
        $this->assertStringContainsString('Estimated arrival: 3-7 business days', $rendered);
        $this->assertStringContainsString('Australia Post', $rendered);
        $this->assertStringContainsString('href="https://tracking.example.test/ABC123"', $rendered);
        $this->assertStringContainsString('>ABC123</a>', $rendered);
        $this->assertStringContainsString('Microbit', $rendered);
        $this->assertStringContainsString('Pinball template', $rendered);
    }
}
