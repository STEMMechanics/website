<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOutstandingBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_tickets_page_shows_shared_invoice_outstanding_total_once(): void
    {
        $user = User::factory()->create([
            'email' => 'bank-transfer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
        ]);

        $firstTicket = Ticket::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Ticket::STATUS_PENDING_XFER,
            'invoice_id' => $invoice->id,
        ]);

        Ticket::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Ticket::STATUS_PENDING_XFER,
            'invoice_id' => $invoice->id,
            'workshop_id' => $firstTicket->workshop_id,
        ]);

        $this->actingAs($user)
            ->get(route('account.ticket.index'))
            ->assertOk()
            ->assertSeeText('Total still to pay')
            ->assertSeeText('$110.00')
            ->assertSeeText('Still to pay: $110.00')
            ->assertSeeText('1 invoice')
            ->assertDontSeeText('$220.00');
    }

    public function test_magic_ticket_page_shows_shared_invoice_outstanding_total_once(): void
    {
        $user = User::factory()->create([
            'email' => 'magic-bank-transfer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
        ]);

        $firstTicket = Ticket::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Ticket::STATUS_PENDING_XFER,
            'invoice_id' => $invoice->id,
        ]);

        Ticket::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Ticket::STATUS_PENDING_XFER,
            'invoice_id' => $invoice->id,
            'workshop_id' => $firstTicket->workshop_id,
        ]);

        $token = Token::create([
            'user_id' => $user->id,
            'type' => 'tickets-access',
            'data' => ['email' => $user->email],
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('tickets.magic', ['token' => $token->id]))
            ->assertOk()
            ->assertSeeText('Total still to pay')
            ->assertSeeText('$110.00')
            ->assertSeeText('Still to pay: $110.00')
            ->assertSeeText('1 invoice')
            ->assertDontSeeText('$220.00');
    }
}
