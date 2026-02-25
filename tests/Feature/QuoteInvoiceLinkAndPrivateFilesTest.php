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
        /** @var Quote $quote */

        $invoice = Invoice::factory()->create([
            'user_id' => $invoiceOwner->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        /** @var Invoice $invoice */

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
        /** @var Quote $quote */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        /** @var Invoice $invoice */

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
        $freshInvoice = $invoice->fresh();
        $this->assertInstanceOf(Invoice::class, $freshInvoice);
        $this->assertSame((int) $quote->id, (int) $freshInvoice->quote_id);
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
        /** @var Quote $quote */

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

    public function test_quote_update_redirects_to_new_quote_number_when_changed(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);
        /** @var Quote $quote */

        $newQuoteNumber = 'Q-TEST-'.uniqid();

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => $newQuoteNumber,
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
        ]);

        $this->assertSame($newQuoteNumber, $quote->fresh()?->quote_number);
        $response->assertRedirect(route('admin.quote.edit', $quote->fresh()));
        $response->assertSessionHasNoErrors();
    }

    public function test_create_invoice_from_quote_links_quote_copies_files_and_unlinks_previous_invoice(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();

        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Pinball Workshop',
            'description' => 'Delivered onsite',
            'notes' => 'Bring safety glasses',
            'line_items' => [
                [
                    'description' => 'Facilitator',
                    'notes' => '1 hour',
                    'quantity' => 2,
                    'unit_price' => 120,
                    'gst_applicable' => true,
                ],
            ],
        ]);
        /** @var Quote $quote */

        $previousInvoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_DRAFT,
            'quote_id' => $quote->id,
        ]);
        /** @var Invoice $previousInvoice */

        $media = Media::query()->create([
            'name' => 'quote-private-invoice-copy.txt',
            'title' => 'Quote Private File',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'text/plain',
            'size' => 64,
            'user_id' => $owner->id,
        ]);
        $quote->updateFiles($media->name, 'private');

        $response = $this->actingAs($admin)
            ->post(route('admin.quote.create-invoice', $quote));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $newInvoice = Invoice::query()
            ->where('quote_id', $quote->id)
            ->where('id', '!=', $previousInvoice->id)
            ->latest('id')
            ->first();

        $this->assertInstanceOf(Invoice::class, $newInvoice);
        $this->assertSame((string) $owner->id, (string) $newInvoice->user_id);
        $freshPreviousInvoice = $previousInvoice->fresh();
        $this->assertInstanceOf(Invoice::class, $freshPreviousInvoice);
        $this->assertNull($freshPreviousInvoice->quote_id);

        $this->assertSame(1, $newInvoice->lines()->count());
        $line = $newInvoice->lines()->first();
        $this->assertInstanceOf(\App\Models\InvoiceLine::class, $line);
        $this->assertSame('Facilitator', $line->description);
        $this->assertSame('1 hour', $line->notes);
        $this->assertSame(2.0, (float) $line->quantity);

        $this->assertDatabaseHas('mediables', [
            'media_name' => $media->name,
            'mediable_id' => (string) $newInvoice->id,
            'mediable_type' => Invoice::class,
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
