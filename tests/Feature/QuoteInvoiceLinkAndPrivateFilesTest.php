<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Media;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteInvoiceLinkAndPrivateFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_quote_cannot_link_invoice_from_different_user(): void
    {
        $admin = $this->createAdminUser();
        $quoteOwner = User::factory()->create();
        $invoiceOwner = User::factory()->create();

        $quote = Quote::factory()->create([
            'user_id' => $quoteOwner->id,
            'line_items' => [],
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $invoiceOwner->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => 'Q-TEST-'.uniqid(),
            'user_id' => $quoteOwner->id,
            'quote_date' => now()->toDateString(),
            'title' => 'Updated quote',
            'description' => 'Description',
            'notes' => 'Notes',
            'line_items_json' => json_encode([
                [
                    'description' => 'Consulting',
                    'notes' => '',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'gst_applicable' => true,
                ],
            ]),
            'linked_invoice_id' => $invoice->id,
        ]);

        $response->assertRedirect(route('admin.quote.edit', $quote));
        $response->assertSessionHasErrors('linked_invoice_id');
        $this->assertNull($invoice->fresh()->quote_id);
    }

    public function test_invoice_can_link_quote_for_same_user_and_store_private_files(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create(['user_id' => $owner->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $media = Media::query()->create([
            'name' => 'invoice-private-doc.txt',
            'title' => 'Invoice Private File',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'text/plain',
            'size' => 32,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.invoice.edit', $invoice))->put(route('admin.invoice.update', $invoice), [
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'purchase_order_number' => 'PO-1234',
            'notes' => 'Updated',
            'line_items_json' => json_encode([]),
            'quote_id' => $quote->id,
            'private_files' => $media->name,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame((int) $quote->id, (int) $invoice->fresh()->quote_id);
        $this->assertDatabaseHas('mediables', [
            'media_name' => $media->name,
            'mediable_id' => (string) $invoice->id,
            'mediable_type' => Invoice::class,
            'collection' => 'private',
        ]);
    }

    public function test_quote_private_files_are_saved_to_private_collection(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);

        $media = Media::query()->create([
            'name' => 'quote-private-doc.txt',
            'title' => 'Quote Private File',
            'hash' => str_repeat('f', 64),
            'mime_type' => 'text/plain',
            'size' => 48,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => 'Q-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'quote_date' => now()->toDateString(),
            'title' => 'Updated quote',
            'description' => 'Description',
            'notes' => 'Notes',
            'line_items_json' => json_encode([
                [
                    'description' => 'Service',
                    'notes' => '',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'gst_applicable' => true,
                ],
            ]),
            'private_files' => $media->name,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mediables', [
            'media_name' => $media->name,
            'mediable_id' => (string) $quote->id,
            'mediable_type' => Quote::class,
            'collection' => 'private',
        ]);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
