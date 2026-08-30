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
        $invoice->loadMissing(['user.primaryOrganisation', 'lines']);
        $templates = app(InvoiceEmailTemplateService::class);
        $template = $templates->resolve($invoice);
        $recipients = $this->parseEmails($templates->expandContactEmail($template['recipient_emails'], $invoice), true);
        $ccRecipients = $this->parseEmails($templates->expandContactEmail($template['cc_emails'], $invoice), false);
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
        foreach ($recipients as $recipient) {
            $mailable = new FinanceDocumentPdf(
                documentType: 'invoice', documentNumber: (string) $invoice->invoice_number,
                recipientName: $name, pdfContent: $pdf,
                pdfFilename: 'invoice-'.preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $invoice->invoice_number).'.pdf',
                fullMessage: $templates->expandContactEmail($template['email_message'], $invoice), documentTotal: (float) $invoice->total_amount,
                documentOutstanding: (float) $invoice->displayOutstandingAmount(), documentDue: $invoice->due_date?->format('M j, Y'),
                purchaseOrderNumber: (string) ($invoice->purchase_order_number ?? ''), payUrl: $payUrl,
                subjectLine: $templates->expandContactEmail($template['subject_line'], $invoice),
            );
            foreach ($ccRecipients as $ccRecipient) {
                if (strcasecmp($ccRecipient, $recipient) !== 0) {
                    $mailable->cc($ccRecipient);
                }
            }
            Mail::to($recipient)->send($mailable);
        }
    }

    /** @return array<int, string> */
    private function parseEmails(string $input, bool $required): array
    {
        $emails = collect(preg_split('/[;,]/', $input) ?: [])
            ->map(fn ($email): string => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if (($required && $emails->isEmpty()) || $emails->contains(fn (string $email): bool => ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('The scheduled invoice email template contains an invalid recipient address.');
        }

        return $emails->all();
    }
}
