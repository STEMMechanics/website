<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\StoreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOrderPickListPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_order_pick_list_pdf_renders_a_basic_internal_layout(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-1000',
            'billing_name' => 'Casey Customer',
            'billing_email' => 'casey@example.com',
            'billing_phone' => '0400123123',
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'order_number' => '1000',
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Casey Customer',
            'billing_email' => 'casey@example.com',
            'billing_phone' => '0400123123',
            'shipping_name' => 'Casey Customer',
            'shipping_address' => '12 Sample Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'paid_at' => now(),
        ]);

        $html = view('pdf.store-order-pick-list', [
            'order' => $order->loadMissing('invoice', 'user'),
            'pickListItems' => [
                [
                    'title' => 'Cardboard Pinball Machine Kit',
                    'sku' => 'kit-cardboard-pinball-machine',
                    'ordered_quantity' => 2,
                    'open_quantity' => 2,
                    'detail' => '2 ready now',
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Pick/Packing List', $html);
        $this->assertStringContainsString('Customer details', $html);
        $this->assertStringContainsString('Order', $html);
        $this->assertStringContainsString('Invoice', $html);
        $this->assertStringContainsString('Status', $html);
        $this->assertStringContainsString('Date', $html);
        $this->assertStringContainsString('Cardboard Pinball Machine Kit', $html);
        $this->assertStringContainsString('To Pick', $html);
        $this->assertStringNotContainsString('hello.', $html);
        $this->assertStringNotContainsString('Open', $html);
        $this->assertStringNotContainsString('Thank you for choosing STEMMechanics.', $html);
    }
}
