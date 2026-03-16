<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOrderAccountIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_orders_page_uses_ticket_style_layout_and_supports_search(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-381500',
            'status' => Invoice::STATUS_PAID,
        ]);

        $matchingOrder = StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_number' => '381500',
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'total_amount' => 52.00,
            'shipping_method_code' => 'regular',
        ]);

        $product = Product::factory()->create([
            'title' => 'Microbit Base',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $matchingOrder->id,
            'product_id' => $product->id,
            'product_title' => 'Microbit Base',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 2,
        ]);

        $cancelledInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-381501',
        ]);

        StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $cancelledInvoice->id,
            'order_number' => '381501',
            'status' => StoreOrder::STATUS_CANCELLED,
        ]);

        $otherInvoice = Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'invoice_number' => 'INV-999999',
        ]);

        StoreOrder::factory()->create([
            'user_id' => $otherUser->id,
            'invoice_id' => $otherInvoice->id,
            'order_number' => '999999',
        ]);

        $this->actingAs($user)
            ->get(route('account.order.index'))
            ->assertOk()
            ->assertSee('Show cancelled')
            ->assertSee('Search')
            ->assertSee('381500')
            ->assertSee('Delivery order')
            ->assertSee('2 items')
            ->assertSee('Invoice INV-381500')
            ->assertSee(route('account.order.show', $matchingOrder), false)
            ->assertSee(route('account.invoice.pdf', $invoice), false)
            ->assertDontSee('999999');

        $this->actingAs($user)
            ->get(route('account.order.index', ['search' => 'Microbit']))
            ->assertOk()
            ->assertSee('381500')
            ->assertDontSee('381501');
    }
}
