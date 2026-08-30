<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organisation;

class InvoiceEmailTemplateService
{
    /** @return array{recipient_emails:string,cc_emails:string,subject_line:string,email_message:string} */
    public function organisationDefaults(): array
    {
        return [
            'recipient_emails' => '{{email}}',
            'cc_emails' => '',
            'subject_line' => 'Your Invoice {{id}} from STEMMechanics',
            'email_message' => "Hi {{name}},\n\nAttached is invoice **{{id}}** for the workshop program. The total cost is {{total}} and is due on {{due}}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}",
        ];
    }

    /** @return array{recipient_emails:string,cc_emails:string,subject_line:string,email_message:string,source:string} */
    public function resolve(Invoice $invoice): array
    {
        $invoice->loadMissing('user.primaryOrganisation');

        if ($invoice->email_template_set) {
            return [
                'recipient_emails' => $this->normaliseStoredTemplateValue($invoice->email_template_to),
                'cc_emails' => $this->normaliseStoredTemplateValue($invoice->email_template_cc),
                'subject_line' => $this->normaliseStoredTemplateValue($invoice->email_template_subject),
                'email_message' => $this->normaliseStoredTemplateValue($invoice->email_template_message),
                'source' => 'invoice',
            ];
        }

        $organisation = $invoice->user?->primaryOrganisation;
        if ($organisation instanceof Organisation && $this->organisationHasTemplate($organisation)) {
            return [
                'recipient_emails' => $this->normaliseStoredTemplateValue($organisation->invoice_email_to),
                'cc_emails' => $this->normaliseStoredTemplateValue($organisation->invoice_email_cc),
                'subject_line' => $this->normaliseStoredTemplateValue($organisation->invoice_email_subject),
                'email_message' => $this->normaliseStoredTemplateValue($organisation->invoice_email_message),
                'source' => 'organisation',
            ];
        }

        return [
            'recipient_emails' => $this->contactEmail($invoice),
            'cc_emails' => '',
            'subject_line' => $this->defaultSubject($invoice),
            'email_message' => $this->defaultMessage($invoice),
            'source' => 'site',
        ];
    }

    /** @param array{recipient_emails:string,cc_emails:string,subject_line:string,email_message:string} $template */
    public function save(Invoice $invoice, array $template): void
    {
        $invoice->update([
            'email_template_set' => true,
            'email_template_to' => $this->normaliseStoredTemplateValue($template['recipient_emails']),
            'email_template_cc' => $this->normaliseStoredTemplateValue($template['cc_emails']),
            'email_template_subject' => $this->normaliseStoredTemplateValue($template['subject_line']),
            'email_template_message' => $this->normaliseStoredTemplateValue($template['email_message']),
        ]);
    }

    public function expandContactEmail(string $value, Invoice $invoice): string
    {
        return str_replace('{{email}}', $this->contactEmail($invoice), $value);
    }

    public function contactEmail(Invoice $invoice): string
    {
        return strtolower(trim((string) ($invoice->billing_email ?: $invoice->user?->email)));
    }

    private function organisationHasTemplate(Organisation $organisation): bool
    {
        return collect([
            $organisation->invoice_email_to,
            $organisation->invoice_email_cc,
            $organisation->invoice_email_subject,
            $organisation->invoice_email_message,
        ])->contains(fn ($value): bool => trim((string) $value) !== '');
    }

    private function normaliseStoredTemplateValue(mixed $value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function defaultSubject(Invoice $invoice): string
    {
        $invoiceNumber = trim((string) $invoice->invoice_number);

        return 'Your Invoice '.($invoiceNumber !== '' ? $invoiceNumber : 'TBD').' from STEMMechanics';
    }

    private function defaultMessage(Invoice $invoice): string
    {
        $nameSource = trim((string) ($invoice->user?->getName() ?? $invoice->billing_name ?? ''));
        $name = trim((string) strtok($nameSource, ' '));
        $name = $name !== '' ? $name : ($nameSource !== '' ? $nameSource : 'there');
        $invoiceNumber = trim((string) $invoice->invoice_number);
        $total = money((float) $invoice->total_amount);
        $due = $invoice->due_date?->format('M j, Y') ?? 'the due date on file';

        if ((string) $invoice->status === Invoice::STATUS_CANCELLED) {
            return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. This invoice has been cancelled and no amount is owing.\n\nPlease don't hesitate to reach out if you have any questions.";
        }
        if ((string) $invoice->status === Invoice::STATUS_WRITTEN_OFF) {
            return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. This invoice has been written off and no amount is owing.\n\nPlease don't hesitate to reach out if you have any questions.";
        }

        $isPaidInFull = (float) $invoice->displayOutstandingAmount() <= 0.0001;
        if ($invoice->isTicketInvoice()) {
            return $isPaidInFull
                ? "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for your workshop ticket booking. The total cost was {$total}, and this invoice has now been paid in full.\n\nPlease don't hesitate to reach out if you have any questions."
                : "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for your workshop ticket booking. The total cost is {$total} and is due on {$due}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
        }

        return $isPaidInFull
            ? "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. The total cost was {$total}, and this invoice has now been paid in full.\n\nPlease don't hesitate to reach out if you have any questions."
            : "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. The total cost is {$total} and is due on {$due}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
    }
}
