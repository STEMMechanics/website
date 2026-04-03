<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketOrderConfirmation;
use App\Models\InvoicePaymentAllocation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use App\Providers\QRCodeProvider;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class WorkshopTicketOrderEmailService
{
    public function queueCombinedEmail(WorkshopTicketEmail $delivery): bool
    {
        $lockedDelivery = null;

        DB::transaction(function () use ($delivery, &$lockedDelivery): void {
            $lockedDelivery = WorkshopTicketEmail::query()
                ->lockForUpdate()
                ->find($delivery->id);

            if (! $lockedDelivery instanceof WorkshopTicketEmail) {
                return;
            }

            if ($lockedDelivery->queued_at !== null) {
                $lockedDelivery = null;
                return;
            }

            $lockedDelivery->status = WorkshopTicketEmail::STATUS_QUEUED;
            $lockedDelivery->queued_at = now();
            $lockedDelivery->failed_at = null;
            $lockedDelivery->error_message = null;
            $lockedDelivery->save();
        });

        if (! $lockedDelivery instanceof WorkshopTicketEmail) {
            return false;
        }

        try {
            $this->dispatchCombinedEmail($lockedDelivery);

            return true;
        } catch (Throwable $e) {
            WorkshopTicketEmail::query()
                ->whereKey($lockedDelivery->id)
                ->update([
                    'status' => WorkshopTicketEmail::STATUS_PENDING,
                    'queued_at' => null,
                    'failed_at' => now(),
                    'error_message' => mb_substr($e->getMessage(), 0, 5000),
                ]);

            throw $e;
        }
    }

    private function dispatchCombinedEmail(WorkshopTicketEmail $delivery): void
    {
        $ticketIds = collect($delivery->ticket_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($ticketIds === []) {
            throw new RuntimeException('No tickets were found for this workshop ticket email.');
        }

        $tickets = Ticket::query()
            ->with(['workshop.location', 'workshop.hero', 'invoice', 'reissuedFromTicket', 'reissuedToTicket'])
            ->whereIn('id', $ticketIds)
            ->orderBy('id')
            ->get();

        if ($tickets->isEmpty()) {
            throw new RuntimeException('No tickets were found for this workshop ticket email.');
        }

        /** @var Ticket $firstTicket */
        $firstTicket = $tickets->first();

        $invoice = null;
        if ((int) ($delivery->invoice_id ?? 0) > 0) {
            $invoice = Invoice::query()
                ->with(['user', 'lines', 'allocations.customerPayment'])
                ->find((int) $delivery->invoice_id);
        }

        $payment = null;
        if ((int) ($delivery->payment_id ?? 0) > 0) {
            $payment = Payment::query()->find((int) $delivery->payment_id);
        }

        $recipient = strtolower(trim((string) ($delivery->recipient_email ?? '')));
        if ($recipient === '') {
            $recipient = strtolower(trim((string) ($firstTicket->email ?: ($invoice instanceof Invoice ? $invoice->billing_email : ''))));
        }
        if ($recipient === '') {
            throw new RuntimeException('A recipient email address is required.');
        }

        $recipientName = trim((string) ($delivery->recipient_name ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($firstTicket->firstname.' '.$firstTicket->surname));
        }
        if ($recipientName === '') {
            $recipientName = $recipient;
        }

        $paymentBreakdown = $this->resolvePaymentBreakdown(
            invoice: $invoice,
            payment: $payment,
            deliveryPaymentMethod: (string) ($delivery->payment_method ?? ''),
            deliveryAmount: is_numeric($delivery->amount ?? null) ? (float) $delivery->amount : null,
        );

        $attachments = [];
        $workshop = $firstTicket->workshop instanceof Workshop ? $firstTicket->workshop : null;
        $isClassroomAccess = $workshop instanceof Workshop && $workshop->usesClassroomRegistration();

        if ($invoice instanceof Invoice) {
            $invoicePdf = $this->buildInvoicePdfBinary($invoice);
            if ($invoicePdf !== null) {
                $attachments[] = [
                    'type' => 'invoice',
                    'content' => $invoicePdf,
                    'filename' => $this->invoicePdfFilename($invoice),
                    'mime' => 'application/pdf',
                ];
            }
        }

        if ($invoice instanceof Invoice && $payment instanceof Payment) {
            $receiptPdf = $this->buildPaymentReceiptPdfBinary($invoice, $payment);
            if ($receiptPdf !== null) {
                $attachments[] = [
                    'type' => 'receipt',
                    'content' => $receiptPdf,
                    'filename' => ($payment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').((int) $payment->id).'.pdf',
                    'mime' => 'application/pdf',
                ];
            }
        }

        if ($invoice instanceof Invoice && $paymentBreakdown['credit_applied_amount'] > 0.0001) {
            $creditReceiptPdf = $this->buildCreditReceiptPdfBinary($invoice, $payment, $paymentBreakdown);
            if ($creditReceiptPdf !== null) {
                $attachments[] = [
                    'type' => 'credit_receipt',
                    'content' => $creditReceiptPdf,
                    'filename' => $this->creditReceiptPdfFilename($invoice, $payment),
                    'mime' => 'application/pdf',
                ];
            }
        }

        if (! $isClassroomAccess) {
            foreach ($tickets as $ticket) {
                if (! $ticket instanceof Ticket) {
                    continue;
                }

                $ticketPdf = $this->buildTicketPdfBinary($ticket);
                if ($ticketPdf === null) {
                    continue;
                }

                $attachments[] = [
                    'type' => 'ticket',
                    'content' => $ticketPdf,
                    'filename' => $this->ticketPdfFilename($ticket),
                    'mime' => 'application/pdf',
                ];
            }
        }

        $ticketRows = $tickets->map(function (Ticket $ticket): array {
            return [
                'reference' => $ticket->ensureReferenceCode(),
                'name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                'email' => (string) ($ticket->email ?? ''),
            ];
        })->values()->all();

        $amount = $paymentBreakdown['order_amount'];

        dispatch(new SendEmail($recipient, new TicketOrderConfirmation(
            recipientName: $recipientName,
            workshop: [
                'title' => $workshop instanceof Workshop ? (string) $workshop->title : '',
                'time' => $workshop instanceof Workshop ? (string) $workshop->getTicketTimeRangeLabel() : '-',
                'location' => $workshop instanceof Workshop ? (string) $workshop->getLocationDisplay(true) : '-',
                'registration' => $isClassroomAccess ? 'classroom' : 'tickets',
                'courseUrl' => $workshop instanceof Workshop ? route('workshop.show', $workshop) : null,
                'classroomUrl' => $workshop instanceof Workshop && $workshop->classSession ? route('class.show', $workshop->classSession) : null,
                'forumUrl' => $workshop instanceof Workshop && $workshop->classSession?->forumCategory
                    ? route('forum.category.show', $workshop->classSession->forumCategory->slug)
                    : ($workshop instanceof Workshop && $workshop->classroomForumCategory ? route('forum.category.show', $workshop->classroomForumCategory->slug) : null),
            ],
            tickets: $ticketRows,
            paymentMethodLabel: $paymentBreakdown['payment_method_label'],
            amount: $amount,
            invoice: $invoice ? [
                'number' => (string) $invoice->invoice_number,
                'status' => (string) $invoice->status,
            ] : null,
            attachments: $attachments,
            ticketCount: $tickets->count(),
            creditAppliedAmount: $paymentBreakdown['credit_applied_amount'],
            paymentAmount: $paymentBreakdown['payment_amount'],
            creditReferenceSummary: $paymentBreakdown['credit_reference_summary'],
        )))->onQueue('mail');

        $this->dispatchHolderTicketEmails($delivery, $tickets, $recipient, $recipientName);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ticket>  $tickets
     */
    private function dispatchHolderTicketEmails(
        WorkshopTicketEmail $delivery,
        $tickets,
        string $purchaserEmail,
        string $purchaserName
    ): void {
        $normalizedPurchaser = strtolower(trim($purchaserEmail));

        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }

            try {
                $recipient = strtolower(trim((string) ($ticket->email ?? '')));
                if ($recipient === '' || $recipient === $normalizedPurchaser) {
                    continue;
                }

                $ticketPdf = $this->buildTicketPdfBinary($ticket);
                $ticketAttachment = $ticketPdf !== null && ! ($ticket->workshop?->usesClassroomRegistration() ?? false) ? [
                    'content' => $ticketPdf,
                    'filename' => $this->ticketPdfFilename($ticket),
                    'mime' => 'application/pdf',
                ] : null;

                $workshopInfo = [
                    'title' => (string) ($ticket->workshop->title ?? ''),
                    'time' => (string) ($ticket->workshop?->getTicketTimeRangeLabel() ?? '-'),
                    'location' => (string) ($ticket->workshop?->getLocationDisplay(true) ?? '-'),
                    'registration' => (string) ($ticket->workshop?->registration ?? ''),
                    'courseUrl' => $ticket->workshop instanceof Workshop ? route('workshop.show', $ticket->workshop) : null,
                    'classroomUrl' => $ticket->workshop instanceof Workshop && $ticket->workshop->classSession ? route('class.show', $ticket->workshop->classSession) : null,
                    'forumUrl' => $ticket->workshop instanceof Workshop && $ticket->workshop->classroomForumCategory ? route('forum.category.show', $ticket->workshop->classroomForumCategory->slug) : null,
                ];
                $ticketInfo = [
                    'reference' => $ticket->ensureReferenceCode(),
                    'name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                    'email' => (string) ($ticket->email ?? ''),
                    'phone' => (string) ($ticket->phone ?? ''),
                ];

                dispatch(new SendEmail($recipient, new TicketAttendeeUpdate(
                    mode: 'new_holder',
                    recipientName: trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))),
                    purchaserName: $purchaserName !== '' ? $purchaserName : (string) ($delivery->recipient_name ?? ''),
                    workshop: $workshopInfo,
                    ticket: $ticketInfo,
                    attachment: $ticketAttachment
                )))->onQueue('mail');
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return array{order_amount: float, payment_amount: float, credit_applied_amount: float, payment_method_label: string, credit_reference_summary: string}
     */
    private function resolvePaymentBreakdown(
        ?Invoice $invoice,
        ?Payment $payment,
        string $deliveryPaymentMethod,
        ?float $deliveryAmount
    ): array {
        $orderAmount = round(max(0, (float) ($deliveryAmount ?? ($invoice instanceof Invoice ? (float) $invoice->total_amount : 0))), 2);
        $paymentAmount = $payment instanceof Payment ? round(max(0, (float) $payment->total_amount), 2) : 0.0;
        $paymentMethodLabel = $this->paymentMethodLabel($deliveryPaymentMethod, $payment);
        $creditAppliedAmount = 0.0;
        $creditReferenceSummary = '';
        if ($invoice instanceof Invoice) {
            $creditAllocations = $invoice->allocations
                ->filter(fn ($allocation): bool => (string) data_get($allocation, 'customerPayment.payment_method', '') === Payment::PAYMENT_METHOD_CREDIT);
            $creditAppliedAmount = round((float) $creditAllocations->sum('allocated_amount'), 2);
            $creditReferenceSummary = $creditAllocations->map(function ($allocation): string {
                $creditPayment = data_get($allocation, 'customerPayment');
                if (! $creditPayment instanceof Payment) {
                    return '';
                }

                $paymentReference = trim((string) ($creditPayment->reference ?? ''));

                return 'Payment #'.(int) $creditPayment->id.($paymentReference !== '' ? ' ('.$paymentReference.')' : '');
            })->filter()->values()->implode(', ');
        }

        if ($deliveryPaymentMethod === 'credit') {
            $paymentMethodLabel = 'Account Credit';
            $creditAppliedAmount = $orderAmount;
            $paymentAmount = 0.0;
        } elseif ($payment instanceof Payment && $orderAmount > $paymentAmount + 0.0001) {
            $creditAppliedAmount = max($creditAppliedAmount, round($orderAmount - $paymentAmount, 2));
        }

        if ($creditAppliedAmount > 0.0001 && $deliveryPaymentMethod !== 'credit') {
            $paymentMethodLabel = 'Account Credit + '.$paymentMethodLabel;
        }

        return [
            'order_amount' => $orderAmount,
            'payment_amount' => $paymentAmount,
            'credit_applied_amount' => $creditAppliedAmount,
            'payment_method_label' => $paymentMethodLabel,
            'credit_reference_summary' => $creditReferenceSummary,
        ];
    }


    private function paymentMethodLabel(string $deliveryPaymentMethod, ?Payment $payment): string
    {
        if ($payment instanceof Payment) {
            return Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_CREDIT_CARD));
        }

        return match ($deliveryPaymentMethod) {
            'credit' => 'Account Credit',
            'account_terms' => 'Account Terms',
            'credit_card' => 'Credit Card',
            'pay_at_door' => 'Pay at Door',
            'bank_transfer' => 'Bank Transfer',
            'free' => 'Free',
            default => ucwords(str_replace('_', ' ', $deliveryPaymentMethod)),
        };
    }

    private function buildTicketPdfBinary(Ticket $ticket): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

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

    private function buildPaymentReceiptPdfBinary(Invoice $invoice, Payment $payment): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        $invoice->loadMissing(['allocations.customerPayment', 'user']);

        $gatewayProcessedAtRaw = trim((string) ($payment->square_gateway_updated_at ?? $payment->square_gateway_created_at ?? ''));
        $gatewayProcessedAtLabel = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAtLabel = \Illuminate\Support\Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (Throwable) {
                $gatewayProcessedAtLabel = '';
            }
        }

        $paymentMethodLabel = Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_CREDIT_CARD));

        return $this->renderPaymentReceiptPdf([
            'isRefund' => $payment->isRefund(),
            'receiptTitle' => $payment->isRefund() ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $payment->isRefund() ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => (float) $payment->total_amount,
            'gstAmount' => abs((float) $payment->gst_amount),
            'paymentMethod' => $paymentMethodLabel,
            'paymentMethodLabel' => $paymentMethodLabel,
            'paidOn' => $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($payment->reference ?? ''),
            'gatewayProvider' => (string) ($payment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($payment->gateway_status ?? ''),
            'transactionId' => trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id)),
            'squareOrderId' => (string) ($payment->square_order_id ?? ''),
            'cardBrand' => (string) ($payment->square_card_brand ?? ''),
            'cardLast4' => (string) ($payment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($payment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $gatewayProcessedAtLabel,
            'footerMessage' => $payment->isRefund() ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
            'creditAppliedAmount' => 0.0,
            'orderTotalAmount' => null,
            'creditReferenceSummary' => null,
            'isCreditReceipt' => false,
        ]);
    }

    private function buildCreditReceiptPdfBinary(Invoice $invoice, ?Payment $payment, array $paymentBreakdown): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        $invoice->loadMissing(['allocations.customerPayment', 'user']);

        $creditAppliedAmount = round(max(0, (float) ($paymentBreakdown['credit_applied_amount'] ?? 0)), 2);
        if ($creditAppliedAmount <= 0.0001) {
            return null;
        }

        $creditReferenceSummary = trim((string) ($paymentBreakdown['credit_reference_summary'] ?? ''));
        $creditReceiptNumber = $this->creditReceiptNumber($invoice, $payment);

        $creditPaidOn = now()->format('M j, Y g:i a');
        if ($payment instanceof Payment && $payment->received_on !== null) {
            $creditPaidOn = $payment->received_on->format('M j, Y g:i a');
        } else {
            $creditAllocationPayment = $invoice->allocations
                ->filter(function (InvoicePaymentAllocation $allocation): bool {
                    $creditPayment = $allocation->customerPayment;

                    return $creditPayment instanceof Payment
                        && (string) ($creditPayment->payment_method ?? '') === Payment::PAYMENT_METHOD_CREDIT;
                })
                ->sortBy(function (InvoicePaymentAllocation $allocation): int {
                    return $allocation->customerPayment instanceof Payment ? (int) $allocation->customerPayment->id : 0;
                })
                ->first();

            if ($creditAllocationPayment instanceof InvoicePaymentAllocation && $creditAllocationPayment->customerPayment instanceof Payment && $creditAllocationPayment->customerPayment->received_on !== null) {
                $creditPaidOn = $creditAllocationPayment->customerPayment->received_on->format('M j, Y g:i a');
            }
        }

        return $this->renderPaymentReceiptPdf([
            'isRefund' => false,
            'isCreditReceipt' => true,
            'receiptTitle' => 'Credit Receipt',
            'receiptNumberLabel' => 'CREDIT RECEIPT NO',
            'amountLabel' => 'Amount Applied',
            'receiptNumber' => $creditReceiptNumber,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => $creditAppliedAmount,
            'gstAmount' => 0.0,
            'paymentMethod' => 'Account Credit',
            'paymentMethodLabel' => 'Account Credit',
            'paidOn' => $creditPaidOn,
            'reference' => $creditReferenceSummary !== '' ? $creditReferenceSummary : 'Account credit applied to invoice '.(string) $invoice->invoice_number,
            'gatewayProvider' => '',
            'gatewayStatus' => '',
            'transactionId' => '',
            'squareOrderId' => '',
            'cardBrand' => '',
            'cardLast4' => '',
            'squareReceiptUrl' => '',
            'gatewayProcessedAt' => '',
            'footerMessage' => 'This receipt confirms account credit applied to the invoice.',
            'creditAppliedAmount' => null,
            'orderTotalAmount' => null,
            'creditReferenceSummary' => null,
        ]);
    }

    private function renderPaymentReceiptPdf(array $data): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        return DomPdf::loadView('pdf.payment-receipt', $data)->setOption([
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

    private function creditReceiptPdfFilename(Invoice $invoice, ?Payment $payment): string
    {
        return 'credit-receipt-'.$this->creditReceiptNumber($invoice, $payment).'.pdf';
    }

    private function creditReceiptNumber(Invoice $invoice, ?Payment $payment): string
    {
        $baseNumber = $payment instanceof Payment
            ? (int) $payment->id
            : (int) ($invoice->invoice_number ?: $invoice->id);

        return (string) max(1, $baseNumber + 1);
    }
}
