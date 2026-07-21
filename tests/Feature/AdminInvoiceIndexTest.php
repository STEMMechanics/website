<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvoiceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invoice_index_shows_custom_invoice_titles_and_coloured_status_badges(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Parker',
            'surname' => 'Lee',
            'email' => 'parker.lee@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Parker Lee',
            'billing_email' => 'parker.lee@example.com',
            'status' => Invoice::STATUS_PAID,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'total_amount' => 132.00,
            'subtotal_amount' => 120.00,
            'gst_amount' => 12.00,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Custom build plan',
            'notes' => 'Travel and setup details should stay out of the summary.',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'description' => 'Materials pack',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('Parker Lee');
        $response->assertSeeText('Custom build plan');
        $response->assertSeeText('Materials pack');
        $response->assertDontSeeText('Travel and setup details should stay out of the summary.');
        $response->assertSeeText('Issued '.now()->subDays(10)->format('M j, Y'));
        $response->assertSeeText('Due '.now()->subDays(2)->format('M j, Y'));
        $response->assertSee('border-emerald-200 bg-emerald-50 text-emerald-800', false);
        $response->assertSee('hidden md:table-cell text-center', false);
        $response->assertSee('space-y-4 md:hidden', false);
    }

    public function test_admin_invoice_index_uses_the_email_modal_for_invoice_email_actions(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Invoice',
            'surname' => 'Owner',
            'email' => 'owner@example.com',
        ]);

        Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Invoice Owner',
            'billing_email' => 'owner@example.com',
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 132.00,
            'subtotal_amount' => 120.00,
            'gst_amount' => 12.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSee('openInvoiceEmailModal', false);
        $response->assertSeeText('Recipient Email');
        $response->assertSeeText('Subject');
        $response->assertSeeText('purchase order number');
        $response->assertSeeText('View and Pay Invoice button');
        $response->assertSee('data-bwignore="true"', false);
        $response->assertSeeText('Send Invoice Email');
    }

    public function test_admin_invoice_index_groups_ticket_summaries_by_workshop(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Ticket',
            'surname' => 'Holder',
            'email' => 'ticket-holder@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Ticket Holder',
            'billing_email' => 'ticket-holder@example.com',
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->subDays(4)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 90.00,
            'subtotal_amount' => 81.82,
            'gst_amount' => 8.18,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'ticket',
            'description' => 'Newtons Cradle - Ticket A1',
            'quantity' => 1,
            'details_json' => ['workshop_title' => 'Newtons Cradle'],
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'kind' => 'ticket',
            'description' => 'Newtons Cradle - Ticket A2',
            'quantity' => 1,
            'details_json' => ['workshop_title' => 'Newtons Cradle'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('Newtons Cradle x 2');
    }

    public function test_admin_invoice_index_can_filter_by_status(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        Invoice::factory()->create([
            'invoice_number' => 'INV-DRAFT-1001',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 80.00,
            'subtotal_amount' => 72.73,
            'gst_amount' => 7.27,
        ]);
        $overdueInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-OVERDUE-1002',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_OVERDUE,
            'issue_date' => now()->subDays(21)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);
        Invoice::factory()->create([
            'invoice_number' => 'INV-COMPUTED-OVERDUE-1003',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(21)->toDateString(),
            'due_date' => now()->subDays(1)->toDateString(),
            'total_amount' => 95.00,
            'subtotal_amount' => 86.36,
            'gst_amount' => 8.64,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index', [
            'status' => Invoice::STATUS_OVERDUE,
        ]));

        $response->assertOk();
        $response->assertSeeText('INV-OVERDUE-1002');
        $response->assertSeeText('INV-COMPUTED-OVERDUE-1003');
        $response->assertDontSeeText('INV-DRAFT-1001');
    }

    public function test_admin_invoice_index_shows_outstanding_and_overdue_totals(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        Invoice::factory()->create([
            'invoice_number' => 'INV-OVERDUE-1001',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->subDays(21)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'total_amount' => 75.00,
            'subtotal_amount' => 68.18,
            'gst_amount' => 6.82,
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'INV-ACTIVE-1002',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('Still outstanding');
        $response->assertSeeText('$195.00');
        $response->assertSeeText('Overdue');
        $response->assertSeeText('$75.00');
    }

    public function test_admin_invoice_index_does_not_count_cancelled_invoices_in_outstanding_total(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        Invoice::factory()->create([
            'invoice_number' => 'INV-ACTIVE-1001',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 75.00,
            'subtotal_amount' => 68.18,
            'gst_amount' => 6.82,
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'INV-CANCELLED-1002',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_CANCELLED,
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('Still outstanding');
        $response->assertSeeText('$75.00');
        $response->assertDontSeeText('$195.00');
        $response->assertSeeText('INV-CANCELLED-1002');
        $response->assertSeeText('Balance: $0.00');
    }

    public function test_admin_invoice_index_does_not_count_written_off_invoices_in_outstanding_total(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        Invoice::factory()->create([
            'invoice_number' => 'INV-ACTIVE-1101',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 75.00,
            'subtotal_amount' => 68.18,
            'gst_amount' => 6.82,
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'INV-WRITTEN-OFF-1102',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_WRITTEN_OFF,
            'written_off_at' => now(),
            'written_off_reason' => 'Uncollectable workshop ticket.',
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('Still outstanding');
        $response->assertSeeText('$75.00');
        $response->assertDontSeeText('$195.00');
        $response->assertSeeText('INV-WRITTEN-OFF-1102');
        $response->assertSeeText('Written off');
        $response->assertSeeText('Balance: $0.00');
        $response->assertSee('title="Invoice is already written off."', false);
    }

    public function test_admin_invoice_index_disables_cancel_action_for_cancelled_invoices(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        Invoice::factory()->create([
            'invoice_number' => 'INV-CANCELLED-2001',
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_CANCELLED,
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));

        $response->assertOk();
        $response->assertSeeText('INV-CANCELLED-2001');
        $response->assertSee('title="Invoice is already cancelled."', false);
        $response->assertSee('disabled', false);
    }

    public function test_overdue_invoice_statuses_can_be_refreshed_and_badged_in_the_navbar(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $overdueInvoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->subDays(21)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'total_amount' => 75.00,
            'subtotal_amount' => 68.18,
            'gst_amount' => 6.82,
        ]);

        $paidInvoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_PAID,
            'issue_date' => now()->subDays(21)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));
        $response->assertOk();
        $response->assertSeeText('Overdue');
        $response->assertSee('border-rose-200 bg-rose-50 text-rose-800', false);

        $this->artisan('invoices:mark-overdue')
            ->expectsOutput('Marked 1 invoice as overdue.')
            ->assertExitCode(0);

        $this->assertSame(Invoice::STATUS_OVERDUE, (string) $overdueInvoice->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, (string) $paidInvoice->fresh()->status);

        $response = $this->actingAs($admin)->get(route('admin.invoice.index'));
        $response->assertOk();
        $response->assertSeeText('Overdue');
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
