<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Downloads')
            ->assertSee('Worksheet')
            ->assertSee('Unlock Worksheet')
            ->assertSee('Verify Email to Download');

        $this->get(route('shop.order.tracking.download', [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]))
            ->assertOk()
            ->assertSee('Confirm the order email')
            ->assertSee('Unlock Download');

        $response = $this->post(route('shop.order.tracking.download.verify', [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]), [
            'email' => 'jamie@example.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('Your download is starting')
            ->assertSee('click here to download it now');

        $signedDownloadUrl = URL::temporarySignedRoute('shop.order.tracking.download', now()->addMinutes(15), [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]);

        $this->get($signedDownloadUrl)
            ->assertOk()
            ->assertDownload('worksheet.pdf');
    }

    public function test_paid_digital_order_portal_backfills_missing_download_snapshots_from_product_files(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'speaker-template.pdf',
            'title' => 'Speaker Template',
            'mime_type' => 'application/pdf',
            'size' => 28,
            'user_id' => (string) $owner->id,
            'hash' => 'speaker-template-hash',
        ]);
        Storage::disk('media')->put('speaker-template-hash', 'speaker-template-bytes');

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'title' => 'Speaker Template',
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 0,
        ]);
        $product->updateFiles($media->name, 'downloads');

        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 0,
            'gst_amount' => 0,
            'total_amount' => 0,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'contains_digital' => true,
            'contains_physical' => false,
            'status' => StoreOrder::STATUS_FULFILLED,
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'billing_phone' => '0400654321',
            'subtotal_amount' => 0,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 0,
            'total_amount' => 0,
            'paid_at' => now(),
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Speaker Template',
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'quantity' => 1,
            'line_price_amount' => 0,
            'line_total_amount' => 0,
            'line_gst_amount' => 0,
            'line_shipping_amount' => 0,
        ]);

        $this->assertDatabaseCount('store_order_item_downloads', 0);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Downloads')
            ->assertSee('Speaker Template')
            ->assertSee('Unlock Speaker Template');

        $this->assertDatabaseHas('store_order_item_downloads', [
            'store_order_item_id' => $item->id,
            'media_name' => 'speaker-template.pdf',
            'title' => 'Speaker Template',
        ]);

        $download = StoreOrderItemDownload::query()->where('store_order_item_id', $item->id)->firstOrFail();

        $response = $this->post(route('shop.order.tracking.download.verify', [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]), [
            'email' => 'jamie@example.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('Your download is starting')
            ->assertSee('click here to download it now');

        $signedDownloadUrl = URL::temporarySignedRoute('shop.order.tracking.download', now()->addMinutes(15), [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]);

        $this->get($signedDownloadUrl)
            ->assertOk()
            ->assertDownload('speaker-template.pdf');
    }

    public function test_guest_download_verification_accepts_the_current_linked_account_email(): void
    {
        Storage::fake('media');

        $owner = User::factory()->create();
        $orderUser = User::factory()->create([
            'email' => 'current@example.com',
        ]);
        $media = Media::query()->create([
            'name' => 'worksheet.pdf',
            'title' => 'Worksheet',
            'mime_type' => 'application/pdf',
            'size' => 12,
            'user_id' => (string) $owner->id,
            'hash' => 'worksheet-hash-current',
        ]);
        Storage::disk('media')->put('worksheet-hash-current', 'worksheet-bytes');

        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 10.00,
            'gst_amount' => 0.91,
            'total_amount' => 10.00,
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $orderUser->id,
            'invoice_id' => $invoice->id,
            'contains_digital' => true,
            'contains_physical' => false,
            'status' => StoreOrder::STATUS_FULFILLED,
            'billing_name' => 'Jamie Example',
            'billing_email' => 'old@example.com',
            'billing_phone' => '0400654321',
            'subtotal_amount' => 10.00,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 0.91,
            'total_amount' => 10.00,
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'total_amount' => 10.00,
            'gst_amount' => 0.91,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 10.00,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'quantity' => 1,
            'line_price_amount' => 10.00,
            'line_total_amount' => 10.00,
            'line_gst_amount' => 0.91,
            'line_shipping_amount' => 0,
        ]);
        $download = StoreOrderItemDownload::factory()->create([
            'store_order_item_id' => $item->id,
            'media_name' => $media->name,
            'title' => 'Worksheet',
        ]);
        $download->media()->associate($media);
        $download->save();

        $response = $this->post(route('shop.order.tracking.download.verify', [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]), [
            'email' => 'current@example.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('Your download is starting')
            ->assertSee('click here to download it now');

        $signedDownloadUrl = URL::temporarySignedRoute('shop.order.tracking.download', now()->addMinutes(15), [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]);

        $this->get($signedDownloadUrl)
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

        $this->get(route('shop.order.tracking.download', [
            'accessToken' => $order->access_token,
            'storeOrderItemDownload' => $download,
        ]))->assertForbidden();
    }
}
