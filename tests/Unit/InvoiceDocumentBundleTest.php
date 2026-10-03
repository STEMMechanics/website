<?php

namespace Tests\Unit;

use App\Mail\InvoiceDocumentBundle;
use Tests\TestCase;

class InvoiceDocumentBundleTest extends TestCase
{
    public function test_invoice_only_email_describes_only_the_invoice_attachment(): void
    {
        $rendered = (new InvoiceDocumentBundle(
            'Partner Contact',
            '8724',
            null,
            [['filename' => 'invoice-8724.pdf', 'content' => 'invoice pdf']],
        ))->render();

        $this->assertStringContainsString('Attached is your invoice #8724 for payment.', $rendered);
        $this->assertStringNotContainsString('related documents', $rendered);
    }

    public function test_invoice_email_describes_an_attached_payment_receipt(): void
    {
        $rendered = (new InvoiceDocumentBundle(
            'Partner Contact',
            '8724',
            null,
            [
                ['filename' => 'invoice-8724.pdf', 'content' => 'invoice pdf'],
                ['filename' => 'payment-receipt-3.pdf', 'content' => 'receipt pdf'],
            ],
        ))->render();

        $this->assertStringContainsString('Attached are your invoice #8724 and payment receipt.', $rendered);
    }
}
