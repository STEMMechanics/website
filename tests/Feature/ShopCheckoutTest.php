<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_shop_checkout_redirects_to_payment_before_creating_an_order(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'notes' => 'Please email me once it is ready.',
        ]);

        $response->assertRedirect(route('shop.checkout.payment'));
        $this->assertSame(0, StoreOrder::query()->count());

        $this->get(route('shop.checkout.payment'))
            ->assertOk()
            ->assertSee('Step 2 of 2')
            ->assertSee('Your order is created only after this payment step succeeds.');
    }
}
