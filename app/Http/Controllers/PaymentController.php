<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SquareApiService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function accountIndex(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $userId = (string) Auth::id();

        $query = Payment::query()
            ->where('user_id', $userId)
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->with([
                'refunds',
                'refunds.allocations.invoice',
                'refunds.allocations.taxAdjustment.invoice',
                'allocations.invoice',
                'allocations.taxAdjustment.invoice',
            ])
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $searchId = ctype_digit($search) ? (int) $search : null;

            $query->where(function ($builder) use ($search, $searchId): void {
                $builder->where('payment_method', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%');

                if ($searchId !== null) {
                    $builder->orWhere('id', $searchId)
                        ->orWhereHas('refunds', fn ($refundQuery) => $refundQuery->where('id', $searchId));
                }
            });
        }

        $payments = $query
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        $creditPayments = Payment::query()
            ->where('user_id', $userId)
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount')
            ->withSum('refunds as refunded_amount_sum', 'total_amount')
            ->get();

        $accountCredit = (float) $creditPayments->sum(function (Payment $payment): float {
            $total = (float) $payment->total_amount;
            $allocated = (float) ($payment->allocated_amount_sum ?? 0);
            $refunded = (float) ($payment->refunded_amount_sum ?? 0);

            return max(0, round($total - $allocated - $refunded, 2));
        });

        return view('account.payments', [
            'payments' => $payments,
            'accountCredit' => $accountCredit,
        ]);
    }

    public function index(Request $request)
    {
        $query = Payment::query()
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->with([
                'user',
                'refunds',
                'refunds.user',
                'refunds.allocations.invoice',
                'refunds.allocations.taxAdjustment',
                'allocations.invoice',
            ])
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('payment_method', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('refunds', function ($refundQuery) use ($search) {
                        $refundQuery->where('payment_method', 'like', '%'.$search.'%')
                            ->orWhere('reference', 'like', '%'.$search.'%')
                            ->orWhere('id', (int) $search);
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->boolean('unallocated_only')) {
            $query->whereRaw('(
                total_amount - (
                    SELECT COALESCE(SUM(ipa.allocated_amount), 0)
                    FROM invoice_payment_allocations ipa
                    WHERE ipa.payment_id = payments.id
                )
            ) - (
                SELECT COALESCE(SUM(r.total_amount), 0)
                FROM payments r
                WHERE r.refund_of_payment_id = payments.id
            ) > 0.0001');
        }

        $payments = $query->orderBy('received_on', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('admin.payment.index', [
            'customerPayments' => $payments,
        ]);
    }

    public function create(Request $request)
    {
        $initialAllocations = [];
        $prefillUserId = '';
        $prefillTotalAmount = null;
        $invoices = Invoice::query()
            ->with('user')
            ->where('total_amount', '>', 0)
            ->orderBy('issue_date', 'desc')
            ->get();
        $invoiceRemainingById = $invoices->mapWithKeys(function (Invoice $invoice) {
            $remaining = $invoice->outstandingAmount();

            return [(string) $invoice->id => $remaining];
        })->all();

        $invoiceParam = trim((string) $request->query('invoice', ''));
        if ($invoiceParam !== '') {
            $seedInvoice = Invoice::query()
                ->where('total_amount', '>', 0)
                ->where('invoice_number', $invoiceParam)
                ->first();

            if (! $seedInvoice && ctype_digit($invoiceParam)) {
                $seedInvoice = Invoice::query()
                    ->where('total_amount', '>', 0)
                    ->find((int) $invoiceParam);
            }

            if ($seedInvoice) {
                $outstanding = $seedInvoice->outstandingAmount();
                $initialAllocations[] = [
                    'invoice_id' => (int) $seedInvoice->id,
                    'allocated_amount' => $outstanding > 0 ? $outstanding : (float) $seedInvoice->absoluteTotalAmount(),
                ];
                $prefillTotalAmount = $outstanding > 0 ? $outstanding : (float) $seedInvoice->absoluteTotalAmount();
                $prefillUserId = (string) ($seedInvoice->user_id ?? '');
            }
        }

        return view('admin.payment.edit', [
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => $invoices,
            'invoiceRemainingById' => $invoiceRemainingById,
            'paymentMethods' => Payment::PAYMENT_METHODS,
            'initialAllocations' => $initialAllocations,
            'prefillUserId' => $prefillUserId,
            'prefillTotalAmount' => $prefillTotalAmount,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        DB::transaction(function () use ($validated, $request): void {
            $payment = new Payment();
            $payment->fill($validated);
            $payment->kind = Payment::KIND_PAYMENT;
            $payment->total_amount = round((float) ($validated['total_amount'] ?? 0), 2);
            $payment->gst_amount = 0;
            $payment->created_by = Auth::id();
            $payment->save();

            $this->syncAllocations($payment, $request);
        });

        session()->flash('message', 'Payment has been recorded');
        session()->flash('message-title', 'Payment recorded');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.payment.index');
    }

    public function edit(Payment $payment)
    {
        $invoiceQuery = Invoice::query()->with('user');
        if ($payment->refund_of_payment_id === null) {
            $invoiceQuery->where('total_amount', '>', 0);
        }
        $invoices = $invoiceQuery->orderBy('issue_date', 'desc')->get();
        $invoiceRemainingById = $invoices->mapWithKeys(function (Invoice $invoice) {
            $remaining = $invoice->outstandingAmount();

            return [(string) $invoice->id => $remaining];
        })->all();

        return view('admin.payment.edit', [
            'customerPayment' => $payment->load(
                'allocations.invoice',
                'allocations.taxAdjustment',
                'refundOf',
                'refunds.allocations.invoice',
                'refunds.user'
            ),
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => $invoices,
            'invoiceRemainingById' => $invoiceRemainingById,
            'paymentMethods' => Payment::PAYMENT_METHODS,
            'initialAllocations' => [],
            'prefillUserId' => '',
            'prefillTotalAmount' => null,
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($payment, $validated): void {
            $payment->notes = $validated['notes'] ?? null;
            $payment->save();
        });

        session()->flash('message', 'Payment notes have been updated');
        session()->flash('message-title', 'Notes updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Payment $payment)
    {
        session()->flash('message', 'Cancelling payments is no longer supported.');
        session()->flash('message-title', 'Action blocked');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.payment.edit', $payment);
    }

    public function chargeWithSquare(
        Request $request,
        Payment $payment,
        SquareApiService $squareApi
    ): RedirectResponse {
        if (! $squareApi->isEnabled()) {
            return $this->squareErrorRedirect('Square is not enabled.');
        }

        $validated = $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'autocomplete' => ['nullable', 'boolean'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?: 'custpay-'.$payment->id.'-'.now()->format('Uu');
        $locationId = (string) config('services.square.location_id');
        if ($locationId === '') {
            return $this->squareErrorRedirect('Square location ID is not configured.');
        }

        $amountMoney = (int) round(((float) $payment->total_amount) * 100);

        try {
            $response = $squareApi->createPayment([
                'idempotency_key' => $idempotencyKey,
                'source_id' => $validated['source_id'],
                'location_id' => $locationId,
                'reference_id' => 'payment:'.$payment->id,
                'autocomplete' => (bool) ($validated['autocomplete'] ?? true),
                'amount_money' => [
                    'amount' => $amountMoney,
                    'currency' => 'AUD',
                ],
                'note' => trim(implode(' | ', array_filter([
                    'Payment #'.$payment->id,
                    $payment->reference ? 'Reference: '.$payment->reference : null,
                    $payment->notes ? 'Notes: '.mb_substr((string) $payment->notes, 0, 180) : null,
                ]))),
            ]);
        } catch (RuntimeException $e) {
            return $this->squareErrorRedirect('Charge failed: '.$e->getMessage());
        }

        $squarePayment = (array) ($response['payment'] ?? []);
        if ($squarePayment === []) {
            return $this->squareErrorRedirect('Charge failed: no payment object was returned.');
        }
        $squareStatus = strtoupper(trim((string) ($squarePayment['status'] ?? 'UNKNOWN')));
        $autocomplete = (bool) ($validated['autocomplete'] ?? true);
        $allowedStatuses = $autocomplete ? ['COMPLETED'] : ['APPROVED', 'COMPLETED'];
        if (! in_array($squareStatus, $allowedStatuses, true)) {
            $statusDetail = trim((string) ($squarePayment['card_details']['status'] ?? ''));
            $statusMessage = $statusDetail !== ''
                ? 'Square status: '.$squareStatus.' (card: '.$statusDetail.')'
                : 'Square status: '.$squareStatus;

            return $this->squareErrorRedirect('Charge was not accepted. '.$statusMessage);
        }

        $payment->payment_method = 'credit_card';
        $payment->kind = Payment::KIND_PAYMENT;
        $payment->gateway_provider = 'square';
        $payment->gateway_status = (string) ($squarePayment['status'] ?? 'UNKNOWN');
        $payment->gateway_reference_id = (string) ($squarePayment['reference_id'] ?? 'payment:'.$payment->id);
        $payment->square_payment_id = (string) ($squarePayment['id'] ?? null);
        $payment->square_order_id = (string) ($squarePayment['order_id'] ?? null);
        $payment->square_location_id = (string) ($squarePayment['location_id'] ?? null);
        $payment->square_receipt_url = (string) ($squarePayment['receipt_url'] ?? null);
        $payment->square_card_brand = (string) ($squarePayment['card_details']['card']['card_brand'] ?? null);
        $payment->square_card_last4 = (string) ($squarePayment['card_details']['card']['last_4'] ?? null);
        $payment->square_paid_money_amount = (int) ($squarePayment['amount_money']['amount'] ?? $amountMoney);
        $payment->square_refunded_money_amount = (int) ($payment->square_refunded_money_amount ?? 0);
        $payment->square_gateway_created_at = $this->squareDateTime($squarePayment['created_at'] ?? null);
        $payment->square_gateway_updated_at = $this->squareDateTime($squarePayment['updated_at'] ?? null);
        $payment->save();

        session()->flash('message', 'Square charge created successfully');
        session()->flash('message-title', 'Charge created');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function refundWithSquare(
        Request $request,
        Payment $payment,
        SquareApiService $squareApi
    ): RedirectResponse {
        if ($this->isCreditGrantPayment($payment)) {
            return $this->squareErrorRedirect('Credit payments are not refundable.');
        }

        if (! $squareApi->isEnabled()) {
            return $this->squareErrorRedirect('Square is not enabled.');
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        if (! is_string($payment->square_payment_id) || trim($payment->square_payment_id) === '') {
            return $this->squareErrorRedirect('No Square payment is linked to this payment.');
        }

        $remainingSquareCents = $payment->square_remaining_refundable_money;
        if ($remainingSquareCents <= 0) {
            return $this->squareErrorRedirect('No refundable amount remaining.');
        }

        $unallocatedByRecords = $this->paymentUnallocatedAmount($payment);
        if ($unallocatedByRecords <= 0.0001) {
            return $this->squareErrorRedirect('Allocated payment amounts must be refunded from the invoice tax adjustment flow.');
        }

        $remainingByRecordsCents = (int) round($this->remainingRefundableAmount($payment) * 100);
        if ($remainingByRecordsCents <= 0) {
            return $this->squareErrorRedirect('This payment is already fully refunded in your records.');
        }
        $remainingUnallocatedCents = (int) round($unallocatedByRecords * 100);
        if ($remainingUnallocatedCents <= 0) {
            return $this->squareErrorRedirect('Allocated payment amounts must be refunded from the invoice tax adjustment flow.');
        }

        $refundAmount = $validated['amount'] !== null
            ? (int) round(((float) $validated['amount']) * 100)
            : min($remainingSquareCents, $remainingByRecordsCents);
        $refundAmount = min($refundAmount, $remainingSquareCents, $remainingByRecordsCents, $remainingUnallocatedCents);
        if ($refundAmount <= 0) {
            return $this->squareErrorRedirect('Invalid refund amount.');
        }

        $idempotencyKey = $validated['idempotency_key'] ?: 'custpay-refund-'.$payment->id.'-'.now()->format('Uu');

        try {
            $response = $squareApi->createRefund([
                'idempotency_key' => $idempotencyKey,
                'payment_id' => $payment->square_payment_id,
                'amount_money' => [
                    'amount' => $refundAmount,
                    'currency' => 'AUD',
                ],
                'reason' => trim((string) ($validated['reason'] ?? 'Refund for payment #'.$payment->id)),
            ]);
        } catch (RuntimeException $e) {
            return $this->squareErrorRedirect('Refund failed: '.$e->getMessage());
        }

        $refund = (array) ($response['refund'] ?? []);
        if ($refund === []) {
            return $this->squareErrorRedirect('Refund failed: no refund object was returned.');
        }

        $refundValue = (int) ($refund['amount_money']['amount'] ?? $refundAmount);
        $refundValue = min($refundValue, $refundAmount);

        DB::transaction(function () use ($payment, $refund, $refundValue, $validated): void {
            $payment->gateway_provider = 'square';
            $currentRefunded = (int) ($payment->square_refunded_money_amount ?? 0);
            $paidValue = (int) ($payment->square_paid_money_amount ?? 0);
            $payment->square_refunded_money_amount = min($paidValue, max(0, $currentRefunded + $refundValue));
            $payment->square_gateway_updated_at = $this->squareDateTime($refund['updated_at'] ?? null) ?? $payment->square_gateway_updated_at;
            $payment->save();

            $reason = trim((string) ($validated['reason'] ?? 'Square refund'));
            $this->createRefundPaymentRecord(
                originalPayment: $payment,
                refundAmount: round($refundValue / 100, 2),
                reason: $reason !== '' ? $reason : 'Square refund',
                paymentMethod: (string) ($payment->payment_method ?: Payment::PAYMENT_METHOD_CREDIT_CARD),
                gatewayProvider: 'square',
                gatewayStatus: (string) ($refund['status'] ?? 'PENDING'),
                gatewayReferenceId: (string) ($refund['id'] ?? ''),
                squareGatewayCreatedAt: $this->squareDateTime($refund['created_at'] ?? null),
                squareGatewayUpdatedAt: $this->squareDateTime($refund['updated_at'] ?? null),
            );
        });

        session()->flash('message', 'Square refund created successfully');
        session()->flash('message-title', 'Refund created');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function refundManual(Request $request, Payment $payment): RedirectResponse
    {
        if ($this->isCreditGrantPayment($payment)) {
            session()->flash('message', 'Credit payments are not refundable.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if ($this->isSquareManagedPayment($payment)) {
            session()->flash('message', 'This payment is managed by Square. Use the Square refund action.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in([
                Payment::PAYMENT_METHOD_CASH,
                Payment::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $remaining = $this->remainingRefundableAmount($payment);
        if ($remaining <= 0.0001) {
            session()->flash('message', 'This payment is already fully refunded in your records.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }
        $unallocated = $this->paymentUnallocatedAmount($payment);
        if ($unallocated <= 0.0001) {
            session()->flash('message', 'Allocated payment amounts must be refunded from the invoice tax adjustment flow.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $requestedAmount = $validated['amount'] !== null ? round((float) $validated['amount'], 2) : $remaining;
        $maxRefundAmount = min($remaining, $unallocated);
        if ($validated['amount'] !== null && $requestedAmount > ($maxRefundAmount + 0.0001)) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount cannot exceed available refundable balance of $'.number_format($maxRefundAmount, 2).'.',
            ]);
        }
        $refundAmount = min($requestedAmount, $maxRefundAmount);
        if ($refundAmount <= 0.0001) {
            session()->flash('message', 'Invalid refund amount.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $reason = trim((string) ($validated['reason'] ?? 'Manual refund'));

        DB::transaction(function () use ($payment, $refundAmount, $reason, $validated): void {
            $this->createRefundPaymentRecord(
                originalPayment: $payment,
                refundAmount: $refundAmount,
                reason: $reason !== '' ? $reason : 'Manual refund',
                paymentMethod: (string) $validated['payment_method'],
                gatewayProvider: null,
                gatewayStatus: null,
                gatewayReferenceId: null,
            );
        });

        session()->flash('message', 'Manual refund recorded successfully');
        session()->flash('message-title', 'Refund recorded');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function receiptPdf(Request $request, Payment $payment)
    {
        $payment->loadMissing('user', 'allocations.invoice', 'allocations.taxAdjustment');

        $pdf = $this->buildPaymentReceiptPdf($payment);
        $filename = $this->getPaymentReceiptPdfFilename($payment);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function accountReceiptPdf(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->loadMissing('user', 'allocations.invoice', 'allocations.taxAdjustment');

        $pdf = $this->buildPaymentReceiptPdf($payment);
        $filename = $this->getPaymentReceiptPdfFilename($payment);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'received_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'min:1', Rule::in(Payment::PAYMENT_METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
            'allocations_json' => ['nullable', 'string'],
        ]);
    }

    private function syncAllocations(Payment $payment, Request $request): void
    {
        $allocations = $this->extractAllocations($request);
        $totalAllocated = 0.0;
        $previousInvoiceIds = $payment->allocations()->pluck('invoice_id')->map(fn ($id) => (int) $id)->all();

        foreach ($allocations as $allocation) {
            $totalAllocated += (float) $allocation['allocated_amount'];
        }

        if ($totalAllocated > ((float) $payment->total_amount + 0.0001)) {
            throw ValidationException::withMessages([
                'allocations_json' => 'Allocated amount cannot exceed payment total amount.',
            ]);
        }

        $requestedByInvoice = collect($allocations)
            ->groupBy('invoice_id')
            ->map(fn ($rows) => round((float) collect($rows)->sum('allocated_amount'), 2));

        foreach ($requestedByInvoice as $invoiceId => $requestedAmount) {
            $invoice = Invoice::query()->find((int) $invoiceId);
            if (! $invoice) {
                continue;
            }

            if (! $this->paymentCanAllocateToInvoice($payment, $invoice)) {
                throw ValidationException::withMessages([
                    'allocations_json' => 'Invoice '.$invoice->invoice_number.' cannot be allocated from this payment type.',
                ]);
            }

            $maxAllocatable = $invoice->outstandingAmount($payment->id);

            if ((float) $requestedAmount > ($maxAllocatable + 0.0001)) {
                throw ValidationException::withMessages([
                    'allocations_json' => 'Allocation for invoice '.$invoice->invoice_number.' exceeds its remaining balance of $'.number_format($maxAllocatable, 2).'.',
                ]);
            }
        }

        $payment->allocations()->delete();
        if (! empty($allocations)) {
            $payment->allocations()->createMany($allocations);
        }

        $invoiceIds = collect($previousInvoiceIds)
            ->merge(array_column($allocations, 'invoice_id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->syncInvoicesFromAllocations($invoiceIds);
    }

    private function extractAllocations(Request $request): array
    {
        $allocationsJson = $request->input('allocations_json', '[]');

        if (! is_string($allocationsJson) || trim($allocationsJson) === '') {
            return [];
        }

        $decoded = json_decode($allocationsJson, true);
        if (! is_array($decoded)) {
            return [];
        }

        $allocations = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $invoiceId = (int) ($item['invoice_id'] ?? 0);
            $allocatedAmount = (float) ($item['allocated_amount'] ?? 0);

            if ($invoiceId <= 0 || $allocatedAmount <= 0) {
                continue;
            }

            $invoiceExists = Invoice::query()->whereKey($invoiceId)->exists();
            if (! $invoiceExists) {
                continue;
            }

            $allocations[] = [
                'invoice_id' => $invoiceId,
                'allocated_amount' => round($allocatedAmount, 2),
            ];
        }

        return $allocations;
    }

    private function squareErrorRedirect(string $message): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('message-title', 'Square error');
        session()->flash('message-type', 'danger');

        return redirect()->back();
    }

    private function syncInvoicesFromAllocations(array $invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::query()->find($invoiceId);
            if (! $invoice) {
                continue;
            }

            $allocated = $invoice->settledAmount();
            $invoiceTotal = $invoice->dueAmount();
            $isPaid = $allocated >= ($invoiceTotal - 0.0001);

            if ($isPaid) {
                $wasPaid = (string) $invoice->status === \App\Models\Invoice::STATUS_PAID;
                $invoice->status = \App\Models\Invoice::STATUS_PAID;
                $invoice->save();

                if ($this->invoiceHasTicketContent($invoice)) {
                    Ticket::query()
                        ->where('invoice_id', $invoice->id)
                        ->whereIn('status', [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER])
                        ->update(['status' => Ticket::STATUS_DONE]);
                }
                continue;
            }

            if ($invoice->status === \App\Models\Invoice::STATUS_PAID) {
                // If allocations are reversed/removed and the invoice is no longer fully covered,
                // move it back out of paid state.
                $invoice->status = \App\Models\Invoice::STATUS_ISSUED;
                $invoice->save();
            }
        }
    }

    private function invoiceHasTicketContent(Invoice $invoice): bool
    {
        if ($invoice->lines()->where('kind', 'ticket')->exists()) {
            return true;
        }

        return Ticket::query()->where('invoice_id', $invoice->id)->exists();
    }

    private function remainingRefundableAmount(Payment $payment): float
    {
        $original = max(0, round((float) $payment->total_amount, 2));
        $refunded = (float) $payment->refunds()->sum('total_amount');

        return max(0, round($original - $refunded, 2));
    }

    private function createRefundPaymentRecord(
        Payment $originalPayment,
        float $refundAmount,
        string $reason,
        ?string $paymentMethod,
        ?string $gatewayProvider,
        ?string $gatewayStatus,
        ?string $gatewayReferenceId,
        $squareGatewayCreatedAt = null,
        $squareGatewayUpdatedAt = null
    ): Payment {
        $refundPayment = new Payment();
        $refundPayment->refund_of_payment_id = $originalPayment->id;
        $refundPayment->kind = Payment::KIND_REFUND;
        $refundPayment->user_id = $originalPayment->user_id;
        $refundPayment->created_by = Auth::id();
        $refundPayment->received_on = now();
        $refundPayment->payment_method = trim((string) $paymentMethod) !== ''
            ? (string) $paymentMethod
            : (string) $originalPayment->payment_method;
        $refundPayment->reference = trim(implode(' | ', array_filter([
            'Refund for payment #'.$originalPayment->id,
            $originalPayment->reference ? 'Original: '.$originalPayment->reference : null,
        ])));
        $refundPayment->total_amount = abs(round($refundAmount, 2));
        $refundPayment->gst_amount = 0;
        $refundPayment->notes = $reason;
        $refundPayment->gateway_provider = $gatewayProvider;
        $refundPayment->gateway_status = $gatewayStatus;
        $refundPayment->gateway_reference_id = $gatewayReferenceId;
        $refundPayment->square_gateway_created_at = $squareGatewayCreatedAt;
        $refundPayment->square_gateway_updated_at = $squareGatewayUpdatedAt;
        $refundPayment->save();

        return $refundPayment;
    }

    private function buildPaymentReceiptPdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Payment receipt PDF generation requires barryvdh/laravel-dompdf.');
        }

        $allocationRows = $payment->allocations
            ->filter(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && ($allocation->invoice || $allocation->taxAdjustment))
            ->map(fn ($allocation) => [
                'document' => $allocation->taxAdjustment
                    ? ('Tax Adjustment '.$allocation->taxAdjustment->adjustment_number)
                    : ('Invoice '.(string) ($allocation->invoice->invoice_number ?? '-')),
                'amount' => (float) $allocation->allocated_amount,
            ])
            ->values();

        $invoiceNumbers = $payment->allocations
            ->filter(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice)
            ->map(fn ($allocation) => trim((string) ($allocation->invoice->invoice_number ?? '')))
            ->filter(fn ($number) => $number !== '')
            ->unique()
            ->values();
        $hasTaxAdjustmentAllocations = $payment->allocations
            ->contains(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->taxAdjustment);

        $allocatedTotal = (float) $allocationRows->sum('amount');
        $totalAmount = abs((float) $payment->total_amount);
        $creditAmount = max(0, round($totalAmount - $allocatedTotal, 2));

        $invoiceSummaryParts = $allocationRows
            ->map(function ($row) {
                $amount = (float) $row['amount'];
                $prefix = $amount < 0 ? '-' : '';

                return $row['document'].' ('.$prefix.'$'.number_format(abs($amount), 2).')';
            })
            ->values()
            ->all();
        if ($creditAmount > 0.0001 || count($invoiceSummaryParts) === 0) {
            $invoiceSummaryParts[] = 'CREDIT ($'.number_format($creditAmount, 2).')';
        }
        $invoiceLabel = 'Invoice / Credit Allocation (as at '.now()->format('M j, Y').')';
        $invoiceSummary = implode(', ', $invoiceSummaryParts);

        // Match the customer-facing invoice receipt style when this payment maps to invoice(s) only.
        if (! $hasTaxAdjustmentAllocations && $invoiceNumbers->isNotEmpty()) {
            $invoiceLabel = $invoiceNumbers->count() > 1 ? 'Invoice Numbers' : 'Invoice Number';
            $invoiceSummary = $invoiceNumbers->implode(', ');
        }

        $amountRaw = (float) $payment->total_amount;
        $isRefund = $payment->isRefund();
        $gatewayProcessedAt = $payment->square_gateway_updated_at?->format('M j, Y g:i a')
            ?? $payment->square_gateway_created_at?->format('M j, Y g:i a')
            ?? '';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => $isRefund,
            'receiptTitle' => $isRefund ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $isRefund ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceLabel' => $invoiceLabel,
            'invoiceNumber' => $invoiceSummary,
            'customerName' => $payment->user?->getName() ?: 'Customer',
            'amountPaid' => $amountRaw,
            'gstAmount' => abs((float) $payment->gst_amount),
            'paymentMethod' => Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
            'paidOn' => $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($payment->reference ?? ''),
            'gatewayProvider' => (string) ($payment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($payment->gateway_status ?? ''),
            'transactionId' => trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id)),
            'squareOrderId' => (string) ($payment->square_order_id ?? ''),
            'cardBrand' => (string) ($payment->square_card_brand ?? ''),
            'cardLast4' => (string) ($payment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($payment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $gatewayProcessedAt,
            'footerMessage' => $isRefund ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function getPaymentReceiptPdfFilename(Payment $payment): string
    {
        return 'payment-receipt-'.($payment->id).'.pdf';
    }

    private function isSquareManagedPayment(Payment $payment): bool
    {
        if (trim((string) ($payment->square_payment_id ?? '')) !== '') {
            return true;
        }

        return strtolower(trim((string) ($payment->gateway_provider ?? ''))) === 'square';
    }

    private function isCreditGrantPayment(Payment $payment): bool
    {
        return (string) ($payment->payment_method ?? '') === Payment::PAYMENT_METHOD_CREDIT;
    }

    private function paymentCanAllocateToInvoice(Payment $payment, Invoice $invoice): bool
    {
        $paymentKind = (string) ($payment->kind ?? Payment::KIND_PAYMENT);

        return $invoice->expectedSettlementKind() === $paymentKind;
    }

    private function paymentUnallocatedAmount(Payment $payment): float
    {
        $allocated = (float) $payment->allocations()->sum('allocated_amount');
        $refunded = (float) $payment->refunds()->sum('total_amount');
        $unallocatedBeforeRefund = max(0, round((float) $payment->total_amount - $allocated, 2));

        return max(0, round($unallocatedBeforeRefund - $refunded, 2));
    }

    private function squareDateTime($value): ?\Illuminate\Support\Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
