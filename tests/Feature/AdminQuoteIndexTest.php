<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuoteIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_quote_index_renders_mobile_cards_and_quote_details(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Quinn',
            'surname' => 'Taylor',
            'email' => 'quinn.taylor@example.com',
        ]);

        Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-100001',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-04-01',
            'title' => 'Custom workshop proposal',
            'total_amount' => 123.45,
            'subtotal_amount' => 112.23,
            'gst_amount' => 11.22,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.index'));

        $response->assertOk();
        $response->assertSee('space-y-4 md:hidden', false);
        $response->assertSeeText('Q-100001');
        $response->assertSeeText('Custom workshop proposal');
        $response->assertSeeText('Quinn Taylor');
        $response->assertSeeText('Apr 1, 2026');
    }

    public function test_admin_quote_index_shows_placeholder_when_quote_has_no_invoices(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'No',
            'surname' => 'Invoice',
            'email' => 'no.invoice@example.com',
        ]);

        Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-NO-INV-1',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-04-01',
            'title' => 'No invoice quote',
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.index'));

        $response->assertOk();
        $response->assertSeeText('Q-NO-INV-1');
        $response->assertSee('title="No linked invoices"', false);
        $response->assertSeeText('--');
        $response->assertDontSeeText('View linked invoices');
    }

    public function test_admin_quote_index_shows_single_linked_invoice_action_and_column(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Single',
            'surname' => 'Invoice',
            'email' => 'single.invoice@example.com',
        ]);

        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-SINGLE-INV-1',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-04-02',
            'title' => 'Single invoice quote',
            'total_amount' => 150.00,
            'subtotal_amount' => 136.36,
            'gst_amount' => 13.64,
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
            'invoice_number' => 'INV-SINGLE-1',
            'status' => Invoice::STATUS_PAID,
            'issue_date' => '2026-04-03',
            'due_date' => '2026-04-10',
            'total_amount' => 150.00,
            'subtotal_amount' => 136.36,
            'gst_amount' => 13.64,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.index'));

        $response->assertOk();
        $response->assertSeeText('Q-SINGLE-INV-1');
        $response->assertSeeText('INV-SINGLE-1');
        $response->assertSeeText('Apr 3, 2026');
        $response->assertSeeText('Paid');
        $response->assertSee(route('admin.invoice.edit', $invoice), false);
        $response->assertSee('title="Open linked invoice INV-SINGLE-1"', false);
        $response->assertSeeText('Linked Invoices');
    }

    public function test_admin_quote_index_shows_multiple_linked_invoices_with_popup_links(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Multi',
            'surname' => 'Invoice',
            'email' => 'multi.invoice@example.com',
        ]);

        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-MULTI-INV-1',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-04-04',
            'title' => 'Multiple invoice quote',
            'total_amount' => 200.00,
            'subtotal_amount' => 181.82,
            'gst_amount' => 18.18,
        ]);
        $firstInvoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
            'invoice_number' => 'INV-MULTI-1',
            'status' => Invoice::STATUS_SENT,
            'issue_date' => '2026-04-05',
            'due_date' => '2026-04-12',
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);
        $secondInvoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'quote_id' => $quote->id,
            'invoice_number' => 'INV-MULTI-2',
            'status' => Invoice::STATUS_PAID,
            'issue_date' => '2026-04-06',
            'due_date' => '2026-04-13',
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.index'));

        $response->assertOk();
        $response->assertSeeText('Q-MULTI-INV-1');
        $response->assertSeeText('INV-MULTI-1');
        $response->assertSeeText('INV-MULTI-2');
        $response->assertSeeText('Sent');
        $response->assertSeeText('Paid');
        $response->assertSee('title="View linked invoices"', false);
        $response->assertSee(route('admin.invoice.edit', $firstInvoice), false);
        $response->assertSee(route('admin.invoice.edit', $secondInvoice), false);
        $response->assertSeeText('2 invoices');
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
