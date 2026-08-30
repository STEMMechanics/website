<?php

namespace App\Services;

use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ScheduledInvoiceDeliveryService
{
    public function deliverCustomerEmail(Invoice $invoice): void
    {
        $invoice->loadMissing(['user', 'lines']);
        $recipient = strtolower(trim((string) ($invoice->billing_email ?: $invoice->user?->email)));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The invoice does not have a valid customer email address.');
        }
        $items = $invoice->lines->map(fn ($line): array => [
            'id' => $line->id, 'kind' => (string) $line->kind,
            'description' => (string) $line->description, 'notes' => (string) ($line->notes ?? ''),
            'details_json' => is_array($line->details_json) ? $line->details_json : [],
            'quantity' => (float) $line->quantity, 'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
            'tax_rate' => (float) $line->tax_rate, 'line_total_ex_tax' => (float) $line->line_total_ex_tax,
            'tax_amount' => (float) $line->tax_amount, 'line_total_inc_tax' => (float) $line->line_total_inc_tax,
            'gst_applicable' => (float) $line->tax_rate > 0.0001,
        ])->all();
        $payUrl = (float) $invoice->displayOutstandingAmount() > 0.0001 ? route('invoice.public.pay.show', $invoice) : null;
        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice, 'itemPages' => [$items], 'adjustments' => collect(), 'publicPayUrl' => $payUrl,
        ])->setOption(['enable_font_subsetting' => true])->output();
        $name = trim((string) ($invoice->user?->firstname ?: $invoice->billing_name ?: 'there'));
        $due = $invoice->due_date?->format('M j, Y') ?? 'the due date on file';
        $message = "Hi {$name},\n\nAttached is invoice **{$invoice->invoice_number}**. The total is ".money((float) $invoice->total_amount)." and is due on {$due}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
        Mail::to($recipient)->send(new FinanceDocumentPdf(
            documentType: 'invoice', documentNumber: (string) $invoice->invoice_number,
            recipientName: $name, pdfContent: $pdf,
            pdfFilename: 'invoice-'.preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $invoice->invoice_number).'.pdf',
            fullMessage: $message, documentTotal: (float) $invoice->total_amount,
            documentOutstanding: (float) $invoice->displayOutstandingAmount(), documentDue: $invoice->due_date?->format('M j, Y'),
            purchaseOrderNumber: (string) ($invoice->purchase_order_number ?? ''), payUrl: $payUrl,
            subjectLine: 'Your Invoice '.$invoice->invoice_number.' from STEMMechanics',
        ));
    }
}
