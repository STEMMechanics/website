<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCartAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_pages_render_ajax_cart_controls(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSee('submitCartForm($event.target)', false);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ]);

        $this->get(route('shop.cart.show'))
            ->assertOk()
            ->assertSee('changeCartQuantity(line.key', false)
            ->assertSee('removeCartLine(line.key)', false);
    }

    public function test_cart_endpoints_return_json_payloads_for_ajax_requests(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $addResponse = $this->withHeaders($headers)->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ]);

        $addResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.is_empty', false)
            ->assertJsonPath('cart.summary.item_count', 2)
            ->assertJsonPath('cart.lines.0.quantity', 2);
        $this->assertFalse(session()->has('message'));

        $lineKey = (string) $addResponse->json('cart.lines.0.key');
        $this->assertNotSame('', $lineKey);

        $updateResponse = $this->withHeaders($headers)->post(route('shop.cart.update'), [
            'quantities' => [$lineKey => 3],
            'shipping_country' => 'Australia',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.item_count', 3)
            ->assertJsonPath('cart.lines.0.quantity', 3);
        $this->assertFalse(session()->has('message'));

        $removeResponse = $this->withHeaders($headers)->post(route('shop.cart.remove'), [
            'line_key' => $lineKey,
            'shipping_country' => 'Australia',
        ]);

        $removeResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.is_empty', true)
            ->assertJsonPath('cart.summary.item_count', 0)
            ->assertJsonCount(0, 'cart.lines');
        $this->assertFalse(session()->has('message'));
    }
}
