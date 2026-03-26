<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Media;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuoteInvoiceLinkAndPrivateFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_multiple_invoices_can_link_to_the_same_quote(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);
        /** @var Quote $quote */
        $firstInvoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_DRAFT,
            'quote_id' => $quote->id,
        ]);
        /** @var Invoice $firstInvoice */
        $secondInvoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        /** @var Invoice $secondInvoice */
        $response = $this->actingAs($admin)->from(route('admin.invoice.edit', $secondInvoice))->put(route('admin.invoice.update', $secondInvoice), [
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'purchase_order_number' => 'PO-5678',
            'notes' => 'Updated',
            'line_items_json' => json_encode([]),
            'quote_id' => $quote->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame((int) $quote->id, (int) $firstInvoice->fresh()->quote_id);
        $this->assertSame((int) $quote->id, (int) $secondInvoice->fresh()->quote_id);
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

    public function test_invoice_store_defaults_due_date_to_next_business_day_when_issue_date_is_on_weekend(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post(route('admin.invoice.store'), [
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'issue_date' => '2026-03-21',
            'due_date' => '',
            'purchase_order_number' => 'PO-1234',
            'notes' => 'Weekend due date test',
            'line_items_json' => json_encode([]),
        ]);

        $response->assertRedirect(route('admin.invoice.index'));
        $response->assertSessionHasNoErrors();

        $invoice = Invoice::query()->latest('id')->first();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('2026-04-20', $invoice->due_date?->toDateString());
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
            'status' => \App\Models\Quote::STATUS_OPEN,
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
            'status' => \App\Models\Quote::STATUS_OPEN,
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

        /** @var Quote $freshQuote */
        $freshQuote = $quote->fresh();
        $this->assertSame($newQuoteNumber, $freshQuote->quote_number);
        $response->assertRedirect(route('admin.quote.edit', $freshQuote));
        $response->assertSessionHasNoErrors();
    }

    public function test_quote_update_with_save_and_email_reopens_email_dialog_for_open_quotes(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
            'status' => Quote::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => 'Q-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'status' => Quote::STATUS_OPEN,
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
            'save_and_email' => '1',
        ]);

        /** @var Quote|null $freshQuote */
        $freshQuote = $quote->fresh();
        $response->assertRedirect(route('admin.quote.edit', $freshQuote));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('quote-email-open', true);
    }

    public function test_quote_update_preserves_typed_line_item_metadata(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => 'Q-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'status' => \App\Models\Quote::STATUS_OPEN,
            'quote_date' => now()->toDateString(),
            'title' => 'Typed quote',
            'description' => 'Description',
            'notes' => 'Notes',
            'private_notes' => 'Internal note',
            'acceptance_emails_invoice' => '1',
            'line_items_json' => json_encode([
                [
                    'kind' => 'product',
                    'description' => 'Marbles',
                    'notes' => '',
                    'quantity' => 2,
                    'unit_price' => 5,
                    'gst_applicable' => true,
                    'store_context' => [
                        'product_id' => 44,
                        'variant_id' => null,
                        'product_sku' => 'MARBLES',
                    ],
                ],
                [
                    'kind' => 'workshop',
                    'description' => 'Holiday Workshop',
                    'notes' => 'Apr 1',
                    'quantity' => 1,
                    'unit_price' => 120,
                    'gst_applicable' => true,
                    'workshop_context' => [
                        'workshop_id' => 77,
                        'title' => 'Holiday Workshop',
                    ],
                ],
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        /** @var Quote $freshQuote */
        $freshQuote = $quote->fresh();
        $this->assertTrue((bool) $freshQuote->acceptance_emails_invoice);
        $this->assertFalse((bool) $freshQuote->acceptance_creates_order);
        $this->assertSame('Internal note', (string) $freshQuote->private_notes);
        $this->assertSame('product', data_get($freshQuote->line_items, '0.kind'));
        $this->assertSame(44, data_get($freshQuote->line_items, '0.store_context.product_id'));
        $this->assertSame('workshop', data_get($freshQuote->line_items, '1.kind'));
        $this->assertSame(77, data_get($freshQuote->line_items, '1.workshop_context.workshop_id'));
    }

    public function test_quote_update_rejects_store_product_lines_without_a_selected_product(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);

        $response = $this->actingAs($admin)->from(route('admin.quote.edit', $quote))->put(route('admin.quote.update', $quote), [
            'quote_number' => 'Q-TEST-'.uniqid(),
            'user_id' => $owner->id,
            'status' => \App\Models\Quote::STATUS_OPEN,
            'quote_date' => now()->toDateString(),
            'title' => 'Typed quote',
            'description' => 'Description',
            'notes' => 'Notes',
            'line_items_json' => json_encode([
                [
                    'kind' => 'product',
                    'description' => 'Marbles',
                    'notes' => '',
                    'quantity' => 2,
                    'unit_price' => 5,
                    'gst_applicable' => true,
                ],
            ]),
        ]);

        /** @var Quote $freshQuote */
        $freshQuote = $quote->fresh();
        $response->assertRedirect(route('admin.quote.edit', $freshQuote));
        $response->assertSessionHasErrors('line_items_json');
        $this->assertSame([], $freshQuote->line_items ?? []);
    }

    public function test_quote_edit_lists_all_linked_invoices(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'line_items' => [],
        ]);
        /** @var Quote $quote */
        Invoice::factory()->create([
            'invoice_number' => 'INV-ALPHA',
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
        ]);
        Invoice::factory()->create([
            'invoice_number' => 'INV-BETA',
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
        ]);
        /** @var Invoice $linkedOrderInvoice */
        $linkedOrderInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-ORDER',
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
        ]);
        /** @var \App\Models\StoreOrder $linkedOrder */
        $linkedOrder = \App\Models\StoreOrder::factory()->create([
            'user_id' => $owner->id,
            'invoice_id' => $linkedOrderInvoice->id,
            'quote_id' => $quote->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.edit', $quote));

        $response->assertOk();
        $response->assertSeeText('Linked Invoices');
        $response->assertSeeText('INV-ALPHA');
        $response->assertSeeText('INV-BETA');
        $response->assertSeeText('Linked Orders');
        $response->assertSeeText($linkedOrder->order_number);
        $response->assertDontSee('name="linked_invoice_id"', false);
    }

    public function test_create_invoice_from_quote_preserves_existing_linked_invoices(): void
    {
        Carbon::setTestNow('2026-03-21 10:00:00');
        try {
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
            /** @var Quote $freshQuote */
            $freshQuote = $quote->fresh();
            $this->assertSame(\App\Models\Quote::STATUS_ACCEPTED, (string) $freshQuote->status);
            $this->assertSame((string) $owner->id, (string) $newInvoice->user_id);
            $this->assertSame('2026-03-21', $newInvoice->issue_date->toDateString());
            $this->assertSame('2026-04-20', $newInvoice->due_date->toDateString());
            $freshPreviousInvoice = $previousInvoice->fresh();
            $this->assertInstanceOf(Invoice::class, $freshPreviousInvoice);
            $this->assertSame((int) $quote->id, (int) $freshPreviousInvoice->quote_id);
            $this->assertSame(2, Invoice::query()->where('quote_id', $quote->id)->count());

            $this->assertSame(1, $newInvoice->lines()->count());
            $line = $newInvoice->lines()->first();
            $this->assertInstanceOf(InvoiceLine::class, $line);
            $this->assertSame('Facilitator', $line->description);
            $this->assertSame('1 hour', $line->notes);
            $this->assertSame(2.0, (float) $line->quantity);

            $this->assertDatabaseHas('mediables', [
                'media_name' => $media->name,
                'mediable_id' => (string) $newInvoice->id,
                'mediable_type' => Invoice::class,
                'collection' => 'private',
            ]);
        } finally {
            Carbon::setTestNow();
        }
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
