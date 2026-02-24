<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\TaxAdjustment;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaxAdjustmentController extends Controller
{
    public function __construct(private readonly DocumentNumberService $documentNumbers)
    {
    }

    public function create(Invoice $invoice): View
    {
        if ((string) $invoice->status === Invoice::STATUS_DRAFT) {
            session()->flash('message', 'Adjustments can only be created from issued invoices.');
            session()->flash('message-title', 'Action blocked');
            session()->flash('message-type', 'warning');

            abort(404);
        }

        $invoice->loadMissing('lines', 'taxAdjustments.lines');
        $refundedQtyByLine = $this->lineRefundedQuantities($invoice);
        $refundableLines = $invoice->lines
            ->filter(fn ($line) => abs((float) $line->quantity) > 0.0001)
            ->map(function ($line) use ($refundedQtyByLine) {
                $originalQty = round(abs((float) $line->quantity), 2);
                $refundedQty = round((float) ($refundedQtyByLine[$line->id] ?? 0), 2);
                $remainingQty = max(0, round($originalQty - $refundedQty, 2));

                return [
                    'line' => $line,
                    'original_qty' => $originalQty,
                    'refunded_qty' => $refundedQty,
                    'remaining_qty' => $remainingQty,
                ];
            })
            ->values();

        return view('admin.tax-adjustment.create', [
            'invoice' => $invoice,
            'refundableLines' => $refundableLines,
            'maxAllowedCreditAmount' => $this->maxAllowedCreditAmountForNew($invoice),
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        if ((string) $invoice->status === Invoice::STATUS_DRAFT) {
            session()->flash('message', 'Adjustments can only be created from issued invoices.');
            session()->flash('message-title', 'Action blocked');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.invoice.edit', $invoice);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'refund_qty' => ['nullable', 'array'],
            'refund_qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice->loadMissing('lines', 'taxAdjustments.lines');
        $lineRefundedQty = $this->lineRefundedQuantities($invoice);
        $refundQtyInput = is_array($validated['refund_qty'] ?? null) ? $validated['refund_qty'] : [];
        $selectedLines = [];

        foreach ($invoice->lines as $line) {
            $lineId = (int) $line->id;
            $lineQty = max(0, abs((float) $line->quantity));
            if ($lineQty <= 0.0001) {
                continue;
            }
            $alreadyRefunded = (float) ($lineRefundedQty[$lineId] ?? 0);
            $remainingQty = max(0, round($lineQty - $alreadyRefunded, 2));
            $requestedQty = isset($refundQtyInput[$lineId]) ? (float) $refundQtyInput[$lineId] : 0.0;

            if ($requestedQty <= 0.0001) {
                continue;
            }
            if ($requestedQty > ($remainingQty + 0.0001)) {
                throw ValidationException::withMessages([
                    'refund_qty.'.$lineId => 'Refund quantity for line '.$line->line_number.' exceeds remaining quantity ('.$remainingQty.').',
                ]);
            }

            $unitEx = abs((float) $line->unit_price_ex_tax);
            $taxRate = max(0, (float) $line->tax_rate);
            $lineEx = round($requestedQty * $unitEx, 2);
            $taxAmount = round($lineEx * $taxRate, 2);
            $lineInc = round($lineEx + $taxAmount, 2);

            $selectedLines[] = [
                'invoice_line_id' => $lineId,
                'line_number' => count($selectedLines) + 1,
                'description' => (string) $line->description,
                'notes' => (string) ($line->notes ?? ''),
                'quantity' => round($requestedQty, 2),
                'unit_price_ex_tax' => round($unitEx, 2),
                'tax_rate' => $taxRate,
                'line_total_ex_tax' => $lineEx,
                'tax_amount' => $taxAmount,
                'line_total_inc_tax' => $lineInc,
            ];
        }

        if ($selectedLines === []) {
            throw ValidationException::withMessages([
                'refund_qty' => 'Select at least one line item refund quantity greater than zero.',
            ]);
        }

        $creditTotal = round((float) collect($selectedLines)->sum('line_total_inc_tax'), 2);
        $maxAllowedCredit = $this->maxAllowedCreditAmountForNew($invoice);
        if ($creditTotal > ($maxAllowedCredit + 0.0001)) {
            throw ValidationException::withMessages([
                'refund_qty' => 'Selected refund exceeds remaining invoice amount of $'.number_format($maxAllowedCredit, 2).'.',
            ]);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        $adjustment = null;
        $summary = null;
        DB::transaction(function () use ($invoice, $selectedLines, $reason, &$adjustment, &$summary): void {
            $subtotal = round((float) collect($selectedLines)->sum('line_total_ex_tax'), 2);
            $gst = round((float) collect($selectedLines)->sum('tax_amount'), 2);
            $total = round((float) collect($selectedLines)->sum('line_total_inc_tax'), 2);

            $adjustment = new TaxAdjustment();
            $adjustment->invoice_id = $invoice->id;
            $adjustment->adjustment_number = $this->documentNumbers->nextTaxAdjustmentNumber();
            $adjustment->issue_date = now()->startOfDay();
            $adjustment->subtotal_amount = -1 * $subtotal;
            $adjustment->gst_amount = -1 * $gst;
            $adjustment->total_amount = -1 * $total;
            $adjustment->notes = trim(implode("\n", array_filter([
                'Tax adjustment note for invoice '.$invoice->invoice_number,
                $reason,
            ])));
            $adjustment->save();

            foreach ($selectedLines as $line) {
                $adjustment->lines()->create($line);
            }

            $summary = $this->reconcileCreditAllocations($invoice, $adjustment);
        });

        $allocated = (float) ($summary['allocated'] ?? 0);
        $consumedOutstanding = (float) ($summary['consumed_outstanding'] ?? 0);
        $remaining = (float) ($summary['remaining'] ?? 0);
        $suffix = $remaining > 0.0001 ? ' $'.number_format($remaining, 2).' remains as unapplied credit.' : '';
        session()->flash(
            'message',
            'Tax adjustment note '.$adjustment->adjustment_number.' created. Reduced invoice outstanding by $'.number_format($consumedOutstanding, 2)
            .' and created payment credit allocations of $'.number_format($allocated, 2).'.'.$suffix
        );
        session()->flash('message-title', 'Adjustment created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]);
    }

    public function edit(Invoice $invoice, TaxAdjustment $taxAdjustment): View
    {
        $this->abortIfNotOwnedByInvoice($invoice, $taxAdjustment);
        $taxAdjustment->loadMissing('lines.invoiceLine');

        return view('admin.tax-adjustment.edit', [
            'invoice' => $invoice,
            'taxAdjustment' => $taxAdjustment,
        ]);
    }

    public function pdf(Invoice $invoice, TaxAdjustment $taxAdjustment)
    {
        $this->abortIfNotOwnedByInvoice($invoice, $taxAdjustment);
        $taxAdjustment->loadMissing('lines', 'invoice.user');

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Adjustment PDF generation requires barryvdh/laravel-dompdf.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.tax-adjustment', [
            'invoice' => $invoice,
            'adjustment' => $taxAdjustment,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);

        return $pdf->stream('tax-adjustment-'.$taxAdjustment->adjustment_number.'.pdf');
    }

    public function emailPdf(Request $request, Invoice $invoice, TaxAdjustment $taxAdjustment): RedirectResponse
    {
        unset($request);
        $this->abortIfNotOwnedByInvoice($invoice, $taxAdjustment);
        $taxAdjustment->loadMissing('lines', 'invoice.user');

        $recipient = trim((string) ($invoice->user->email ?? ''));
        if ($recipient === '') {
            session()->flash('message', 'Tax adjustment email failed: assigned user has no email');
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            session()->flash('message', 'Tax adjustment email failed: PDF generation package is missing.');
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.tax-adjustment', [
            'invoice' => $invoice,
            'adjustment' => $taxAdjustment,
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();
        $mailable = new FinanceDocumentPdf(
            documentType: 'tax adjustment note',
            documentNumber: (string) $taxAdjustment->adjustment_number,
            recipientName: $invoice->user?->getName() ?? $recipient,
            pdfContent: $pdfBinary,
            pdfFilename: 'tax-adjustment-'.$taxAdjustment->adjustment_number.'.pdf',
            initiatedByEmail: $initiatedByEmail,
            initiatedByName: $initiatedByName,
        );
        if ($initiatedByEmail !== null && strcasecmp($initiatedByEmail, $recipient) !== 0) {
            $mailable->cc($initiatedByEmail);
        }

        try {
            dispatch(new SendEmail($recipient, $mailable))->onQueue('mail');
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Tax adjustment email failed: '.$e->getMessage());
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Tax adjustment PDF emailed to '.$recipient);
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function update(Request $request, Invoice $invoice, TaxAdjustment $taxAdjustment): RedirectResponse
    {
        unset($request);
        $this->abortIfNotOwnedByInvoice($invoice, $taxAdjustment);
        session()->flash('message', 'Tax adjustments are immutable once created.');
        session()->flash('message-title', 'Update blocked');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $taxAdjustment]);
    }

    private function abortIfNotOwnedByInvoice(Invoice $invoice, TaxAdjustment $taxAdjustment): void
    {
        if ((int) $taxAdjustment->invoice_id !== (int) $invoice->id) {
            abort(404);
        }
    }

    private function getMailInitiatorIdentity(): array
    {
        $user = auth()->user();
        $email = trim((string) ($user->email ?? ''));
        $name = trim((string) $user->getName());

        return [
            $email !== '' ? $email : null,
            $name !== '' ? $name : null,
        ];
    }

    private function reconcileCreditAllocations(Invoice $invoice, TaxAdjustment $taxAdjustment): array
    {
        InvoicePaymentAllocation::query()
            ->where('tax_adjustment_id', $taxAdjustment->id)
            ->delete();

        $remaining = abs(round((float) $taxAdjustment->total_amount, 2));
        if ($remaining <= 0.0001) {
            return ['allocated' => 0.0, 'remaining' => 0.0];
        }
        $outstandingBefore = $this->outstandingBeforeThisAdjustment($invoice, $taxAdjustment);
        $consumedOutstanding = min($remaining, $outstandingBefore);
        $remaining = max(0, round($remaining - $consumedOutstanding, 2));
        if ($remaining <= 0.0001) {
            return ['allocated' => 0.0, 'remaining' => 0.0, 'consumed_outstanding' => $consumedOutstanding];
        }

        $rows = InvoicePaymentAllocation::query()
            ->with('customerPayment')
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get();

        $netByPayment = $rows
            ->groupBy('payment_id')
            ->map(fn ($allocations) => round((float) $allocations->sum('allocated_amount'), 2))
            ->filter(fn (float $amount) => $amount > 0.0001);

        $allocated = 0.0;
        foreach ($netByPayment as $paymentId => $netAllocated) {
            if ($remaining <= 0.0001) {
                break;
            }

            $sourcePayment = $rows->firstWhere('payment_id', (int) $paymentId)?->customerPayment;
            if (! $sourcePayment instanceof Payment) {
                continue;
            }
            if ((string) ($sourcePayment->kind ?? Payment::KIND_PAYMENT) !== Payment::KIND_PAYMENT) {
                continue;
            }

            $portion = min($remaining, (float) $netAllocated);
            $portion = round($portion, 2);
            if ($portion <= 0.0001) {
                continue;
            }

            InvoicePaymentAllocation::query()->create([
                'payment_id' => (int) $paymentId,
                'invoice_id' => $invoice->id,
                'tax_adjustment_id' => $taxAdjustment->id,
                'allocated_amount' => -1 * $portion,
            ]);

            $remaining = max(0, round($remaining - $portion, 2));
            $allocated = round($allocated + $portion, 2);
        }

        return [
            'allocated' => $allocated,
            'remaining' => $remaining,
            'consumed_outstanding' => $consumedOutstanding,
        ];
    }

    private function maxAllowedCreditAmountForNew(Invoice $invoice): float
    {
        $otherCredits = (float) TaxAdjustment::query()
            ->where('invoice_id', $invoice->id)
            ->sum(DB::raw('ABS(total_amount)'));

        return max(0, round(abs((float) $invoice->total_amount) - $otherCredits, 2));
    }

    private function lineRefundedQuantities(Invoice $invoice): array
    {
        return DB::table('tax_adjustment_lines')
            ->join('tax_adjustments', 'tax_adjustments.id', '=', 'tax_adjustment_lines.tax_adjustment_id')
            ->where('tax_adjustments.invoice_id', $invoice->id)
            ->whereNotNull('tax_adjustment_lines.invoice_line_id')
            ->groupBy('tax_adjustment_lines.invoice_line_id')
            ->selectRaw('tax_adjustment_lines.invoice_line_id as invoice_line_id, SUM(tax_adjustment_lines.quantity) as refunded_qty')
            ->pluck('refunded_qty', 'invoice_line_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    private function outstandingBeforeThisAdjustment(Invoice $invoice, TaxAdjustment $taxAdjustment): float
    {
        $otherIssuedCredits = (float) TaxAdjustment::query()
            ->where('invoice_id', $invoice->id)
            ->where('id', '!=', $taxAdjustment->id)
            ->sum(DB::raw('ABS(total_amount)'));
        $netInvoiceDue = max(0, round(abs((float) $invoice->total_amount) - $otherIssuedCredits, 2));

        $paidAgainstInvoice = (float) InvoicePaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where('allocated_amount', '>', 0)
            ->whereHas('customerPayment', function ($query): void {
                $query->where('kind', Payment::KIND_PAYMENT);
            })
            ->sum('allocated_amount');

        return max(0, round($netInvoiceDue - $paidAgainstInvoice, 2));
    }
}
