<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopDownloadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_digital_order_exposes_downloads(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'worksheet.pdf',
            'title' => 'Worksheet',
            'mime_type' => 'application/pdf',
            'size' => 12,
            'user_id' => (string) $owner->id,
            'hash' => 'worksheet-hash',
        ]);
        Storage::disk('media')->put('worksheet-hash', 'worksheet-bytes');

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 0,
        ]);
        $product->updateFiles($media->name, 'downloads');

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ]);

        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'billing_phone' => '0400654321',
        ])->assertRedirect();

        $order = StoreOrder::query()->with('items.downloads')->firstOrFail();
        $download = $order->items->first()->downloads->first();

        $this->assertNotNull($download);
        $this->assertTrue($order->isPaid());
        $this->assertSame(StoreOrder::STATUS_FULFILLED, (string) $order->status);

        $this->get(route('shop.order.show', [
            'storeOrder' => $order,
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Downloads')
            ->assertSee('Worksheet');

        $this->get(route('shop.order.download', [
            'storeOrder' => $order,
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]))
            ->assertOk()
            ->assertDownload('worksheet.pdf');
    }

    public function test_unpaid_order_blocks_download_access(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'download.zip',
            'title' => 'Download ZIP',
            'mime_type' => 'application/zip',
            'size' => 20,
            'user_id' => (string) $owner->id,
            'hash' => 'download-hash',
        ]);
        Storage::disk('media')->put('download-hash', 'zip-bytes');

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 12.50,
        ]);
        $product->updateFiles($media->name, 'downloads');

        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_ISSUED,
            'subtotal_amount' => 11.36,
            'gst_amount' => 1.14,
            'total_amount' => 12.50,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'contains_digital' => true,
            'contains_physical' => false,
            'status' => StoreOrder::STATUS_PENDING_PAYMENT,
            'billing_name' => 'Taylor Example',
            'billing_email' => 'taylor@example.com',
            'billing_phone' => '0400789000',
            'subtotal_amount' => 12.50,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 1.14,
            'total_amount' => 12.50,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_slug' => $product->slug,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'quantity' => 1,
            'unit_price' => 12.50,
            'line_price_amount' => 12.50,
            'line_total_amount' => 12.50,
            'line_gst_amount' => 1.14,
            'unit_shipping_rate' => 0,
            'line_shipping_amount' => 0,
        ]);
        $download = StoreOrderItemDownload::factory()->create([
            'store_order_item_id' => $item->id,
            'media_name' => $media->name,
            'title' => 'Download ZIP',
        ]);

        $this->assertFalse($order->isPaid());

        $this->get(route('shop.order.download', [
            'storeOrder' => $order,
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]))->assertForbidden();
    }
}
