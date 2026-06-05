<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\TicketOrderConfirmation;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Providers\QRCodeProvider;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use RuntimeException;
use Throwable;

class ManualWorkshopTicketEmailService
{
    public function sendCreatedTicket(Ticket $ticket, ?Invoice $invoice, string $type): void
    {
        $recipient = strtolower(trim((string) ($ticket->email ?? '')));
        if ($recipient === '') {
            throw new RuntimeException('Manual ticket email requires a recipient email address.');
        }

        $attachments = [];
        $ticketAttachment = $this->buildTicketAttachment($ticket);
        if ($ticketAttachment === null) {
            throw new RuntimeException('Unable to generate the ticket PDF attachment.');
        }

        $attachments[] = $ticketAttachment;

        if ($invoice instanceof Invoice) {
            $invoiceAttachment = $this->buildInvoiceAttachment($invoice);
            if ($invoiceAttachment !== null) {
                $attachments[] = $invoiceAttachment;
            }
        }

        $recipientName = trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? '')));
        $workshop = $ticket->workshop;

        dispatch(new SendEmail($recipient, new TicketOrderConfirmation(
            recipientName: $recipientName !== '' ? $recipientName : $recipient,
            workshop: [
                'title' => (string) ($workshop->title ?? ''),
                'time' => (string) ($workshop->getTicketTimeRangeLabel() ?? '-'),
                'location' => (string) ($workshop->getLocationDisplay(true) ?? '-'),
            ],
            tickets: [[
                'reference' => $ticket->ensureReferenceCode(),
                'name' => $recipientName !== '' ? $recipientName : '-',
                'email' => (string) ($ticket->email ?? ''),
                'earlyBird' => $ticket->isEarlyBirdTicket(),
            ]],
            paymentMethodLabel: $type === 'reserve' ? 'Pay at Door' : 'Free',
            amount: (float) ($invoice instanceof Invoice ? $invoice->total_amount : 0),
            invoice: $invoice ? [
                'number' => (string) $invoice->invoice_number,
                'status' => (string) $invoice->status,
            ] : null,
            attachments: $attachments,
            ticketCount: 1,
        )))->onQueue('mail');
    }

    private function buildTicketAttachment(Ticket $ticket): ?array
    {
        $ticketPdf = $this->buildTicketPdfBinary($ticket);
        if ($ticketPdf === null) {
            return null;
        }

        return [
            'type' => 'ticket',
            'content' => $ticketPdf,
            'filename' => $this->ticketPdfFilename($ticket),
            'mime' => 'application/pdf',
        ];
    }

    private function buildInvoiceAttachment(Invoice $invoice): ?array
    {
        $invoicePdf = $this->buildInvoicePdfBinary($invoice);
        if ($invoicePdf === null) {
            return null;
        }

        return [
            'type' => 'invoice',
            'content' => $invoicePdf,
            'filename' => $this->invoicePdfFilename($invoice),
            'mime' => 'application/pdf',
        ];
    }

    private function buildTicketPdfBinary(Ticket $ticket): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        $ticket->loadMissing('workshop.location', 'workshop.hero', 'invoice', 'reissuedFromTicket', 'reissuedToTicket');
        $referenceCode = $ticket->ensureReferenceCode();

        $ticketQrSvg = null;
        $ticketQrDataUri = null;
        try {
            $ticketQrSvg = (new QRCodeProvider())->getQRCodeImage($referenceCode, 240);
            if (trim((string) $ticketQrSvg) !== '') {
                $ticketQrDataUri = 'data:image/svg+xml;base64,'.base64_encode($ticketQrSvg);
            }
        } catch (Throwable) {
            $ticketQrSvg = null;
            $ticketQrDataUri = null;
        }

        return DomPdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'workshop' => $ticket->workshop,
            'ticketQrSvg' => $ticketQrSvg,
            'ticketQrDataUri' => $ticketQrDataUri,
            'ticketReferenceCode' => $referenceCode,
            'ticketHeroImagePath' => $this->resolveTicketHeroImagePath($ticket),
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);
    }

    private function buildInvoicePdfBinary(Invoice $invoice): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        $invoice->loadMissing('user', 'lines');
        $itemPages = [$invoice->lines->map(function ($line): array {
            return [
                'description' => (string) $line->description,
                'notes' => (string) ($line->notes ?? ''),
                'quantity' => (float) $line->quantity,
                'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
                'line_total_ex_tax' => (float) $line->line_total_ex_tax,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => (float) $line->tax_amount,
                'line_total_inc_tax' => (float) $line->line_total_inc_tax,
            ];
        })->values()->all()];

        if ($itemPages[0] === []) {
            $itemPages = [[]];
        }

        return DomPdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'itemPages' => $itemPages,
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);
    }

    private function resolveTicketHeroImagePath(Ticket $ticket): ?string
    {
        $hero = $ticket->workshop?->hero;
        if (! $hero) {
            return null;
        }

        try {
            $thumbnailVariant = $hero->getClosestVariant('thumbnail');
            $thumbnailVariantName = trim((string) ($thumbnailVariant['variant'] ?? ''));
            $thumbnailPath = (string) ($thumbnailVariant['file'] ?? '');
            if ($thumbnailVariantName !== '' && $thumbnailPath !== '' && is_file($thumbnailPath)) {
                return $thumbnailPath;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function ticketPdfFilename(Ticket $ticket): string
    {
        $slug = trim((string) ($ticket->workshop->title ?? 'workshop'));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'workshop');
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'workshop';
        }

        $reference = $ticket->ensureReferenceCode();

        return 'ticket-'.$reference.'-'.$slug.'.pdf';
    }

    private function invoicePdfFilename(Invoice $invoice): string
    {
        $number = trim((string) ($invoice->invoice_number ?? $invoice->id));
        $number = preg_replace('/[^a-z0-9._-]+/i', '-', $number) ?: (string) $invoice->id;

        return 'invoice-'.$number.'.pdf';
    }
}
