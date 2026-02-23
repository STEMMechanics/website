<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_account_invoice_pages(): void
    {
        $response = $this->get(route('account.invoice.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_another_users_invoice_in_account_area(): void
    {
        [$owner, $otherUser] = $this->usersPair();
        $invoice = $this->createInvoiceForUser($owner, 'INV-1001');

        $response = $this->actingAs($otherUser)
            ->get(route('account.invoice.show', $invoice));

        $response->assertForbidden();
    }

    public function test_user_cannot_view_another_users_payment_receipt_pdf(): void
    {
        [$owner, $otherUser] = $this->usersPair();
        $payment = $this->createPaymentForUser($owner);

        $response = $this->actingAs($otherUser)
            ->get(route('account.payment.receipt', $payment));

        $response->assertForbidden();
    }

    public function test_user_cannot_view_another_users_ticket_pdf(): void
    {
        [$owner, $otherUser] = $this->usersPair();
        $ticket = $this->createTicketForUser($owner);

        $response = $this->actingAs($otherUser)
            ->get(route('account.ticket.pdf', $ticket));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_access_admin_audit_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.server.audit'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_audit_page(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.server.audit'));

        $response->assertOk();
        $response->assertSee('Audit Log');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function usersPair(): array
    {
        return [
            User::factory()->create(),
            User::factory()->create(),
        ];
    }

    private function createInvoiceForUser(User $user, string $invoiceNumber): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'user_id' => $user->id,
            'billing_name' => $user->getName(),
            'billing_email' => $user->email,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
            'total_amount' => 110.00,
        ]);
    }

    private function createPaymentForUser(User $user): Payment
    {
        return Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Test Payment',
            'total_amount' => 20.00,
            'gst_amount' => 0.00,
        ]);
    }

    private function createTicketForUser(User $user): Ticket
    {
        $location = Location::factory()->create();
        $heroMediaName = 'test-hero-'.Str::lower(Str::random(8)).'.png';

        DB::table('media')->insert([
            'name' => $heroMediaName,
            'title' => 'Test Hero',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'variants' => json_encode([]),
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workshop = Workshop::query()->create([
            'title' => 'Security Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'status' => 'open',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $user->id,
            'hero_media_name' => $heroMediaName,
        ]);

        return Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'firstname' => 'Owner',
            'surname' => 'User',
            'email' => $user->email,
            'phone' => '0400123123',
        ]);
    }
}
