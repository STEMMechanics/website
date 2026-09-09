<?php

namespace Tests\Feature;

use App\Mail\TicketOrderConfirmation;
use Tests\TestCase;

class TicketOrderConfirmationTemplateTest extends TestCase
{
    public function test_paid_booking_includes_one_store_summary_and_no_itemisation(): void
    {
        $mail = $this->mail(1, 39.70, 'paid', 39.70, 0, [
            'number' => '1003', 'delivery' => 'Regular shipping', 'pickup' => false,
            'url' => 'https://example.com/order/1003', 'invoice_number' => '8711',
            'items' => [['title' => 'Product should stay on the invoice', 'quantity' => 5]],
            'shipments' => [['title_primary' => 'Ships now', 'delivery_estimate_label' => '3–7 business days']],
        ], ['ticket', 'invoice', 'receipt']);
        $html = $mail->render();
        $this->assertStringContainsString('Your ticket, invoice and payment receipt are attached to this email.', $html);
        $this->assertStringContainsString('Your ticket</h3>', $html);
        $this->assertStringContainsString('Store Order #1003', $html);
        $this->assertStringContainsString('View Store Order', $html);
        $this->assertStringContainsString('Estimated delivery: 3–7 business days', $html);
        $this->assertStringContainsString('Total paid', $html);
        $this->assertSame(1, substr_count($html, '#8711'));
        $this->assertStringNotContainsString('Product should stay on the invoice', $html);
        $this->assertStringNotContainsString('Equipment Order', $html);
        $this->assertStringNotContainsString('Other workshops', $html);
    }

    public function test_multiple_free_tickets_use_plural_grammar_without_store_or_payment_claims(): void
    {
        $mail = $this->mail(2, 0, null, 0, 0, null, ['ticket', 'ticket']);
        $html = $mail->render();
        $this->assertStringContainsString('Your tickets are attached to this email.', $html);
        $this->assertStringContainsString('Your tickets</h3>', $html);
        $this->assertStringContainsString('Ticket TEST1', $html);
        $this->assertStringContainsString('Ticket TEST2', $html);
        $this->assertStringContainsString('Manage tickets', $html);
        $this->assertStringContainsString('No payment required', $html);
        $this->assertStringNotContainsString('Total paid', $html);
        $this->assertStringNotContainsString('Store Order', $html);
        $this->assertStringNotContainsString('Payment method', $html);
        $this->assertStringNotContainsString('invoice and payment receipt are attached', $html);
    }

    public function test_unpaid_booking_only_promises_attached_documents_and_shows_amount_due(): void
    {
        $mail = $this->mail(1, 15, 'issued', 0, 0, null, ['invoice']);
        $html = $mail->render();
        $this->assertStringContainsString('Your invoice is attached to this email.', $html);
        $this->assertStringContainsString('Amount due', $html);
        $this->assertStringContainsString('$15.00', $html);
        $this->assertStringContainsString('Manage ticket', $html);
        $this->assertStringNotContainsString('Total paid', $html);
        $this->assertStringNotContainsString('payment receipt are attached', $html);
        $this->assertStringNotContainsString('Store Order', $html);
    }

    public function test_credit_pickup_course_and_plain_text_preserve_required_details(): void
    {
        $mail = $this->mail(2, 40, 'paid', 20, 20, [
            'number' => '1003', 'pickup' => true, 'delivery' => 'Pickup',
            'url' => 'https://example.com/order/1003', 'shipments' => [],
        ], ['ticket', 'ticket', 'invoice', 'receipt', 'credit_receipt']);
        $mail->workshop['title'] = 'Butterflies & Trainers';
        $mail->workshop['schedule'] = ['Wednesday 11 November, 3:30 pm', 'Wednesday 18 November, 3:30 pm'];
        $mail->workshop['participantInformation'] = '<p>Please bring a water bottle.</p>';
        $html = $mail->render();
        $this->assertStringContainsString('Account credit applied', $html);
        $this->assertStringContainsString('Payment received', $html);
        $this->assertStringContainsString('Your tickets, invoice, payment receipt and credit receipt are attached', $html);
        $this->assertStringContainsString('We’ll let you know when your order is ready to collect.', $html);
        $this->assertStringContainsString('Wednesday 18 November', $html);
        $this->assertStringContainsString('Please bring a water bottle.', $html);
        $this->assertStringNotContainsString('Regular shipping', $html);
        $text = view('emails.ticket-order-confirmation-text', array_merge(get_object_vars($mail), $mail->viewData))->render();
        $this->assertStringContainsString('Butterflies & Trainers', $text);
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringContainsString('Store Order #1003', $text);
        $this->assertStringContainsString('Total paid: $40.00', $text);
        $this->assertStringContainsString('Please bring a water bottle.', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    private function mail(int $count, float $amount, ?string $status, float $paid, float $credit, ?array $store, array $documents): TicketOrderConfirmation
    {
        return new TicketOrderConfirmation(
            recipientName: 'James Collins',
            workshop: ['title' => 'Butterfly Trainers', 'time' => 'Wednesday, 11 November 2026, 3:30 pm – 4:30 pm', 'location' => 'Stratford Library'],
            tickets: array_map(fn ($i) => ['name' => 'Attendee '.$i, 'reference' => 'TEST'.$i], range(1, $count)),
            paymentMethodLabel: $credit > 0 ? 'Account Credit + Credit Card' : 'Credit Card',
            amount: $amount,
            invoice: $status ? ['number' => '8711', 'status' => $status] : null,
            attachments: array_map(fn ($type, $i) => ['type' => $type, 'filename' => $type.$i.'.pdf', 'content' => 'test attachment'], $documents, array_keys($documents)),
            ticketCount: $count,
            creditAppliedAmount: $credit,
            paymentAmount: $paid,
            equipmentOrder: $store,
        );
    }
}
