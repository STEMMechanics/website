<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\StoreLowStockAdminAlert;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoreLowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_command_queues_admin_email_once_for_newly_low_products(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $product = Product::factory()->create([
            'title' => 'Low Stock Kit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'inventory_quantity' => 2,
            'low_stock_threshold' => 5,
            'private_notes' => "02/04/26 - ordered 12 from supplier\nAwaiting confirmation",
            'low_stock_alert_sent_at' => null,
        ]);

        Artisan::call('store:products:send-low-stock-alerts');

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreLowStockAdminAlert
                && count($job->mailable->products) === 1
                && $job->mailable->products[0]['title'] === 'Low Stock Kit'
                && $job->mailable->products[0]['available'] === 2
                && $job->mailable->products[0]['low_stock_threshold'] === 5;
        });

        $this->assertNotNull($product->fresh()->low_stock_alert_sent_at);

        Queue::fake();
        Artisan::call('store:products:send-low-stock-alerts');
        Queue::assertNothingPushed();
    }
}
