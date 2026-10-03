<?php

namespace App\Support;

class InvoiceEmailSubject
{
    /**
     * @param  array<int, string>  $documentTypes  Supported values: invoice, receipt, refund_receipt, credit_receipt, ticket, tax_adjustment.
     */
    public static function forInvoice(string $invoiceNumber, array $documentTypes, int $ticketCount = 0): string
    {
        $invoiceNumber = trim($invoiceNumber);
        $normalizedTypes = array_map(
            static fn ($type): string => strtolower(trim((string) $type)),
            $documentTypes
        );
        $types = array_fill_keys($normalizedTypes, true);

        $receiptCount = count(array_filter(
            $normalizedTypes,
            static fn (string $type): bool => in_array($type, ['receipt', 'refund_receipt', 'credit_receipt'], true)
        ));
        $refundReceiptCount = count(array_filter($normalizedTypes, static fn (string $type): bool => $type === 'refund_receipt'));
        $regularReceiptCount = $receiptCount - $refundReceiptCount;
        $taxAdjustmentCount = count(array_filter($normalizedTypes, static fn (string $type): bool => $type === 'tax_adjustment'));
        $ticketCount = max($ticketCount, count(array_filter($normalizedTypes, static fn (string $type): bool => $type === 'ticket')));
        $hasReceipt = $receiptCount > 0;
        $hasTickets = $ticketCount > 0;
        $hasTaxAdjustment = $taxAdjustmentCount > 0;

        $documents = [];
        if ($hasReceipt) {
            $receiptLabel = $regularReceiptCount === 0 ? 'refund receipt' : 'receipt';
            if ($receiptCount > 1) {
                $receiptLabel .= 's';
            }
            $documents[] = $receiptLabel;
        }
        if ($hasTickets) {
            $documents[] = $ticketCount === 1 ? 'ticket' : 'tickets';
        }
        if ($hasTaxAdjustment) {
            $documents[] = 'tax adjustment'.($taxAdjustmentCount > 1 ? 's' : '');
        }

        if ($documents === []) {
            return 'Your invoice '.$invoiceNumber.' from STEMMechanics';
        }

        $documentDescription = count($documents) === 1
            ? $documents[0]
            : implode(', ', array_slice($documents, 0, -1)).' and '.end($documents);

        if (preg_match('/^payment\s*#\s*(.+)$/i', $invoiceNumber, $matches) === 1) {
            $reference = 'payment #'.$matches[1];
        } else {
            $reference = str_contains($invoiceNumber, ',')
                ? 'invoices '.$invoiceNumber
                : 'invoice '.$invoiceNumber;
        }

        return 'Your '.$documentDescription.' for '.$reference.' from STEMMechanics';
    }

    /**
     * @param  array<int, string>  $documentTypes
     */
    public static function forTicketOrder(?string $invoiceNumber, array $documentTypes, int $ticketCount, string $workshopTitle): string
    {
        $invoiceNumber = trim((string) $invoiceNumber);
        if ($invoiceNumber !== '') {
            return self::forInvoice($invoiceNumber, $documentTypes, $ticketCount);
        }

        $normalizedTypes = array_map(
            static fn ($type): string => strtolower(trim((string) $type)),
            $documentTypes
        );
        $types = array_fill_keys($normalizedTypes, true);

        $documents = [];
        if (isset($types['receipt']) || isset($types['refund_receipt']) || isset($types['credit_receipt'])) {
            $documents[] = 'receipt';
        }
        if (isset($types['ticket']) || $ticketCount > 0) {
            $documents[] = $ticketCount === 1 ? 'ticket' : 'tickets';
        }
        if ($documents === []) {
            $documents[] = 'order details';
        }

        $documentDescription = count($documents) === 1
            ? $documents[0]
            : implode(', ', array_slice($documents, 0, -1)).' and '.end($documents);
        $workshopTitle = trim($workshopTitle) ?: 'your STEMMechanics order';

        return 'Your '.$documentDescription.' for '.$workshopTitle.' from STEMMechanics';
    }
}
