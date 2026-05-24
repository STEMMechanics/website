<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Models\InvoicePaymentAllocation;
use App\Models\SquareRefundOperation;
use App\Models\SquareWebhookEvent;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SquareApiService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    private const EFTPOS_MERGE_WINDOW_DAYS = 3;

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
        $paymentReplacementDialogDataById = $payments->getCollection()
            ->mapWithKeys(function (Payment $payment): array {
                $matchingPayments = $this->matchingEftposPaymentsForReplacement($payment)
                    ->map(fn (Payment $matchingPayment): array => $this->paymentReplacementDisplayData($matchingPayment))
                    ->values()
                    ->all();
                $sourceLabel = $this->isSquareManagedPayment($payment)
                    ? 'Square EFTPOS'
                    : 'manual EFTPOS';
                $counterpartyLabel = $this->isSquareManagedPayment($payment)
                    ? 'manual EFTPOS'
                    : 'Square EFTPOS';

                return [(string) $payment->id => [
                    'headline' => 'Override '.$sourceLabel.' payment?',
                    'description' => 'Compare this '.$sourceLabel.' entry with the matching '.$counterpartyLabel.' transaction before replacing it.',
                    'action' => route('admin.payment.square.replace', $payment),
                    'source' => $this->paymentReplacementDisplayData($payment),
                    'candidates' => $matchingPayments,
                ]];
            })
            ->all();

        return view('admin.payment.index', [
            'customerPayments' => $payments,
            'paymentReplacementDialogDataById' => $paymentReplacementDialogDataById,
        ]);
    }

    public function credits(Request $request)
    {
        $query = SquareRefundOperation::query()
            ->with(['invoice.user', 'invoice.storeOrders', 'customerPayment.user', 'ticket.workshop'])
            ->whereIn('status', [
                SquareRefundOperation::STATUS_PENDING,
                SquareRefundOperation::STATUS_COMPLETED,
                SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                SquareRefundOperation::STATUS_FAILED,
            ]);

        $hideCompleted = $request->boolean('hide_completed');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $searchId = ctype_digit($search) ? (int) $search : null;

            $query->where(function ($builder) use ($search, $searchId): void {
                $builder->whereHas('ticket', function ($ticketQuery) use ($search): void {
                    $ticketQuery->where('reference_code', 'like', '%'.$search.'%')
                        ->orWhere('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
                ->orWhereHas('invoice', function ($invoiceQuery) use ($search): void {
                    $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('billing_email', 'like', '%'.$search.'%')
                        ->orWhere('billing_name', 'like', '%'.$search.'%');
                })
                ->orWhereHas('invoice.storeOrders', function ($orderQuery) use ($search): void {
                    $orderQuery->where('order_number', 'like', '%'.$search.'%');
                })
                ->orWhereHas('customerPayment', function ($paymentQuery) use ($search): void {
                    $paymentQuery->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('payment_method', 'like', '%'.$search.'%')
                        ->orWhere('gateway_reference_id', 'like', '%'.$search.'%');
                })
                ->orWhere('failure_message', 'like', '%'.$search.'%');

                if ($searchId !== null) {
                    $builder->orWhere('id', $searchId)
                        ->orWhere('payment_id', $searchId)
                        ->orWhere('invoice_id', $searchId)
                        ->orWhere('ticket_id', $searchId);
                }
            });
        }

        $displayQuery = clone $query;
        if ($hideCompleted) {
            $displayQuery->where('status', '!=', SquareRefundOperation::STATUS_COMPLETED);
        }

        $manualRefunds = $displayQuery
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        $summaryQuery = clone $query;
        $manualRefundCount = (clone $summaryQuery)->count();
        $actionRequiredCount = (clone $summaryQuery)
            ->whereIn('status', [
                SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                SquareRefundOperation::STATUS_FAILED,
            ])
            ->count();
        $pendingCount = (clone $summaryQuery)
            ->where('status', SquareRefundOperation::STATUS_PENDING)
            ->count();
        $completedCount = (clone $summaryQuery)
            ->where('status', SquareRefundOperation::STATUS_COMPLETED)
            ->count();
        $manualRefundTotal = (float) ((clone $summaryQuery)->sum('requested_cents') / 100);

        return view('admin.payment.refunds', [
            'manualRefunds' => $manualRefunds,
            'manualRefundCount' => $manualRefundCount,
            'actionRequiredCount' => $actionRequiredCount,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'manualRefundTotal' => $manualRefundTotal,
            'hideCompleted' => $hideCompleted,
        ]);
    }

    public function completeManualRefund(Request $request, SquareRefundOperation $manualRefund): RedirectResponse
    {
        if (! in_array($manualRefund->status, [
            SquareRefundOperation::STATUS_FAILED,
            SquareRefundOperation::STATUS_MANUAL_REQUIRED,
        ], true)) {
            session()->flash('message', 'This refund item is already completed.');
            session()->flash('message-title', 'Nothing to do');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $leaveAsCredit = $request->boolean('leave_as_credit');

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'leave_as_credit' => ['nullable', 'boolean'],
            'payment_method' => [($leaveAsCredit ? 'nullable' : 'required'), Rule::in([
                Payment::PAYMENT_METHOD_CASH,
                Payment::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
            'received_on' => [($leaveAsCredit ? 'nullable' : 'required'), 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $leaveAsCredit = (bool) ($validated['leave_as_credit'] ?? false);

        $originalPayment = $manualRefund->customerPayment;
        if (! $originalPayment instanceof Payment) {
            session()->flash('message', 'This refund item no longer has a linked customer payment.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $requestedAmount = round(((int) $manualRefund->requested_cents) / 100, 2);
        $remainingRefundable = $this->remainingRefundableAmount($originalPayment);
        $maxRefundAmount = min($requestedAmount, $remainingRefundable);

        if ($maxRefundAmount <= 0.0001) {
            session()->flash('message', 'This payment no longer has refundable funds available.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $refundAmount = ($validated['amount'] ?? null) !== null
            ? round((float) $validated['amount'], 2)
            : $maxRefundAmount;

        if (($validated['amount'] ?? null) !== null && $refundAmount > ($maxRefundAmount + 0.0001)) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount cannot exceed available refundable balance of $'.number_format($maxRefundAmount, 2).'.',
            ]);
        }

        $refundAmount = min($refundAmount, $maxRefundAmount);
        if ($refundAmount <= 0.0001) {
            session()->flash('message', 'Invalid refund amount.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            $reason = $this->manualRefundDefaultReason($manualRefund, $originalPayment);
        }
        $receivedOn = $leaveAsCredit ? now()->setSecond(0) : Carbon::parse((string) $validated['received_on'])->setSecond(0);
        $reference = trim((string) ($validated['reference'] ?? ''));
        $paymentMethod = $leaveAsCredit ? Payment::PAYMENT_METHOD_CREDIT : (string) $validated['payment_method'];

        $refundPayment = null;
        DB::transaction(function () use (&$refundPayment, $manualRefund, $originalPayment, $refundAmount, $reason, $paymentMethod, $receivedOn, $reference, $leaveAsCredit): void {
            if ($leaveAsCredit) {
                $manualRefund->status = SquareRefundOperation::STATUS_COMPLETED;
                $manualRefund->refunded_cents = 0;
                $manualRefund->square_refund_id = null;
                $manualRefund->failure_message = null;
                $manualRefund->processed_at = now();
                $manualRefund->payload = array_replace((array) ($manualRefund->payload ?? []), [
                    'manual_refund' => [
                        'refund_payment_id' => null,
                        'refund_amount' => 0.0,
                        'credit_amount' => round($refundAmount, 2),
                        'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
                        'reference' => $reference !== '' ? $reference : null,
                        'reason' => $reason,
                        'received_on' => $receivedOn->toIso8601String(),
                        'processed_by' => Auth::id(),
                        'processed_at' => now()->toIso8601String(),
                        'resolution' => 'credit_retained',
                    ],
                ]);
                $manualRefund->save();

                return;
            }

            $refundPayment = $this->createRefundPaymentRecord(
                originalPayment: $originalPayment,
                refundAmount: $refundAmount,
                reason: $reason !== '' ? $reason : 'Manual refund',
                paymentMethod: $paymentMethod,
                gatewayProvider: null,
                gatewayStatus: null,
                gatewayReferenceId: null,
                receivedOn: $receivedOn,
                referenceOverride: $reference,
            );

            $manualRefund->status = SquareRefundOperation::STATUS_COMPLETED;
            $manualRefund->refunded_cents = (int) round($refundAmount * 100);
            $manualRefund->square_refund_id = null;
            $manualRefund->failure_message = null;
            $manualRefund->processed_at = now();
            $manualRefund->payload = array_replace((array) ($manualRefund->payload ?? []), [
                'manual_refund' => [
                    'refund_payment_id' => $refundPayment->id,
                    'refund_amount' => round($refundAmount, 2),
                    'payment_method' => $paymentMethod,
                    'reference' => $reference !== '' ? $reference : null,
                    'reason' => $reason,
                    'received_on' => $receivedOn->toIso8601String(),
                    'processed_by' => Auth::id(),
                    'processed_at' => now()->toIso8601String(),
                    'resolution' => 'refund_paid_out',
                ],
            ]);
            $manualRefund->save();
        });

        if ($refundPayment instanceof Payment) {
            $this->sendRefundReceiptEmail($originalPayment, $refundPayment);
            session()->flash('message', 'Manual refund recorded and customer receipt sent.');
        } else {
            session()->flash('message', 'Marked as account credit retained. The queue item has been cleared.');
        }
        session()->flash('message-title', 'Refund recorded');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function create(Request $request)
    {
        $initialAllocations = [
            [
                'invoice_id' => '',
                'allocated_amount' => 0.00,
            ],
        ];
        $prefillUserId = '';
        $prefillTotalAmount = null;
        $invoices = Invoice::query()
            ->with(['user', 'tickets'])
            ->where('total_amount', '>', 0)
            ->orderBy('issue_date', 'desc')
            ->get();
        $invoices = $invoices
            ->filter(fn (Invoice $invoice): bool => (string) $invoice->status !== Invoice::STATUS_DRAFT
                && (float) $invoice->outstandingAmount() > 0.0001)
            ->values();
        $invoiceOptions = $this->paymentInvoiceOptions($invoices);
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
                $initialAllocations = [[
                    'invoice_id' => (int) $seedInvoice->id,
                    'allocated_amount' => $outstanding > 0 ? $outstanding : (float) $seedInvoice->absoluteTotalAmount(),
                ]];
                $prefillTotalAmount = $outstanding > 0 ? $outstanding : (float) $seedInvoice->absoluteTotalAmount();
                $prefillUserId = (string) ($seedInvoice->user_id ?? '');
            }
        }

        return view('admin.payment.edit', [
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => $invoices,
            'invoiceOptions' => $invoiceOptions,
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
        $emailReceipt = $request->boolean('email_receipt', false);
        $payment = null;

        DB::transaction(function () use ($validated, $request, &$payment): void {
            $payment = new Payment();
            $payment->fill($validated);
            $payment->kind = Payment::KIND_PAYMENT;
            $payment->total_amount = round((float) ($validated['total_amount'] ?? 0), 2);
            $payment->gst_amount = 0;
            $payment->cleared_at = $this->resolveClearedAt($payment, $validated, $request);
            $payment->created_by = Auth::id();
            $payment->save();

            $this->syncAllocations($payment, $request);
        });

        if ($emailReceipt && $payment instanceof Payment && $this->sendPaymentReceiptEmail($payment) === false) {
            session()->flash('message', 'Payment has been recorded. Receipt was not emailed because the payment is still pending clearance or has no allocations yet.');
            session()->flash('message-title', 'Payment recorded');
            session()->flash('message-type', 'warning');
        } elseif ($emailReceipt && $payment instanceof Payment) {
            session()->flash('message', 'Payment has been recorded and the receipt has been emailed.');
            session()->flash('message-title', 'Payment recorded');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'Payment has been recorded');
            session()->flash('message-title', 'Payment recorded');
            session()->flash('message-type', 'success');
        }

        return redirect()->route('admin.payment.index');
    }

    public function edit(Payment $payment)
    {
        $invoiceQuery = Invoice::query()->with('user');
        if ($payment->refund_of_payment_id === null) {
            $invoiceQuery->where('total_amount', '>', 0);
        }
        $invoiceQuery->with(['tickets']);
        $invoices = $invoiceQuery
            ->orderBy('issue_date', 'desc')
            ->get()
            ->filter(fn (Invoice $invoice): bool => (string) $invoice->status !== Invoice::STATUS_DRAFT
                && (float) $invoice->outstandingAmount() > 0.0001)
            ->values();
        $invoiceOptions = $this->paymentInvoiceOptions($invoices);
        $invoiceRemainingById = $invoices->mapWithKeys(function (Invoice $invoice) {
            $remaining = $invoice->outstandingAmount();

            return [(string) $invoice->id => $remaining];
        })->all();
        $customerPayment = $payment->load(
            'allocations.invoice.user',
            'allocations.taxAdjustment',
            'refundOf',
            'refunds.allocations.invoice',
            'refunds.user'
        );
        $replacementDialogData = $this->paymentReplacementDialogData(
            $customerPayment,
            $this->matchingEftposPaymentsForReplacement($customerPayment)
        );
        $existingInvoiceOptionsById = collect($this->paymentInvoiceOptions(
            $customerPayment->allocations
                ->map(fn ($allocation) => $allocation->invoice)
                ->filter()
                ->values()
        ))->mapWithKeys(function (array $option): array {
            return [(string) $option['id'] => $option];
        })->all();

        return view('admin.payment.edit', [
            'customerPayment' => $customerPayment,
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => $invoices,
            'invoiceOptions' => $invoiceOptions,
            'existingInvoiceOptionsById' => $existingInvoiceOptionsById,
            'invoiceRemainingById' => $invoiceRemainingById,
            'replacementDialogData' => $replacementDialogData,
            'paymentMethods' => Payment::PAYMENT_METHODS,
            'initialAllocations' => [],
            'prefillUserId' => '',
            'prefillTotalAmount' => null,
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        if ($payment->isRefund()) {
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

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'allocations_json' => ['nullable', 'string'],
            'email_receipt' => ['nullable', 'boolean'],
            'bank_transfer_cleared' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($payment, $validated, $request): void {
            $payment->user_id = (string) $validated['user_id'];
            $payment->reference = $validated['reference'] ?? null;
            $payment->notes = $validated['notes'] ?? null;
            $payment->cleared_at = $this->resolveClearedAt($payment, $validated, $request);
            $payment->save();
            $this->syncAllocations($payment, $request);
        });

        $shouldEmailReceipt = $request->boolean('email_receipt', false);
        if ($shouldEmailReceipt) {
            $payment->refresh();
        }
        if ($shouldEmailReceipt && $this->sendPaymentReceiptEmail($payment) === false) {
            session()->flash('message', 'Payment linkage has been updated. Receipt was not emailed because the payment is still pending clearance or has no allocations yet.');
            session()->flash('message-title', 'Payment updated');
            session()->flash('message-type', 'warning');
            return redirect()->back();
        }

        session()->flash('message', 'Payment linkage has been updated');
        session()->flash('message-title', 'Payment updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function replaceWithSquarePayment(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->isRefund()) {
            session()->flash('message', 'Refund payments cannot be replaced.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ((string) ($payment->payment_method ?? '') !== Payment::PAYMENT_METHOD_EFTPOS) {
            session()->flash('message', 'Only EFTPOS payments can be replaced with a matching Square transaction.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $validated = $request->validate([
            'matched_payment_id' => ['required', 'integer', 'exists:payments,id'],
            'email_receipt' => ['nullable', 'boolean'],
        ]);

        $matchedPayment = Payment::query()
            ->with([
                'user',
                'allocations.invoice',
                'allocations.taxAdjustment',
            ])
            ->withCount(['allocations', 'refunds'])
            ->findOrFail((int) $validated['matched_payment_id']);

        if ((int) $matchedPayment->id === (int) $payment->id) {
            session()->flash('message', 'You must choose a different payment to replace this one.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ((string) ($matchedPayment->payment_method ?? '') !== Payment::PAYMENT_METHOD_EFTPOS) {
            session()->flash('message', 'The selected payment is not an EFTPOS transaction.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ((float) round((float) $payment->total_amount, 2) !== (float) round((float) $matchedPayment->total_amount, 2)) {
            session()->flash('message', 'The selected payment amount does not match.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $sourceReceivedOn = $payment->received_on;
        $targetReceivedOn = $matchedPayment->received_on;
        if (! $sourceReceivedOn instanceof Carbon || ! $targetReceivedOn instanceof Carbon) {
            session()->flash('message', 'Both payments need a recorded date/time before they can be matched.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ($sourceReceivedOn->diffInDays($targetReceivedOn) > self::EFTPOS_MERGE_WINDOW_DAYS) {
            session()->flash('message', 'The selected payment is outside the allowed matching window.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $sourceIsSquareManaged = $this->isSquareManagedPayment($payment);
        $targetIsSquareManaged = $this->isSquareManagedPayment($matchedPayment);
        if ($sourceIsSquareManaged === $targetIsSquareManaged) {
            session()->flash('message', 'A replacement requires one manual EFTPOS payment and one Square EFTPOS payment.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ((int) ($matchedPayment->refunds_count ?? 0) > 0 || $matchedPayment->refunds()->exists()) {
            session()->flash('message', 'The selected payment already has refunds and cannot be used as a replacement.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        if ($payment->refunds()->exists()) {
            session()->flash('message', 'This payment has refunds linked to it and cannot be replaced safely.');
            session()->flash('message-title', 'Replacement blocked');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        DB::transaction(function () use ($payment, $matchedPayment): void {
            $sourcePayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $targetPayment = Payment::query()->lockForUpdate()->findOrFail($matchedPayment->id);

            $sourceIsSquareManaged = $this->isSquareManagedPayment($sourcePayment);
            $targetIsSquareManaged = $this->isSquareManagedPayment($targetPayment);
            if ($sourceIsSquareManaged === $targetIsSquareManaged) {
                throw new RuntimeException('Replacement requires one manual EFTPOS payment and one Square EFTPOS payment.');
            }

            $keepPayment = $sourceIsSquareManaged ? $sourcePayment : $targetPayment;
            $deletePayment = $sourceIsSquareManaged ? $targetPayment : $sourcePayment;

            if ($deletePayment->user_id && ! $keepPayment->user_id) {
                $keepPayment->user_id = $deletePayment->user_id;
            }

            if ($keepPayment->created_by === null && $deletePayment->created_by !== null) {
                $keepPayment->created_by = $deletePayment->created_by;
            }

            if (trim((string) ($keepPayment->reference ?? '')) === '' && trim((string) ($deletePayment->reference ?? '')) !== '') {
                $keepPayment->reference = $deletePayment->reference;
            }

            $deleteNotes = trim((string) ($deletePayment->notes ?? ''));
            $keepNotes = trim((string) ($keepPayment->notes ?? ''));
            if ($deleteNotes !== '') {
                if ($keepNotes === '') {
                    $keepPayment->notes = $deleteNotes;
                } elseif ($keepNotes !== $deleteNotes) {
                    $keepPayment->notes = $keepNotes."\n\nMerged from payment #".$deletePayment->id.":\n".$deleteNotes;
                }
            }

            if ($keepPayment->cleared_at === null && $deletePayment->cleared_at !== null) {
                $keepPayment->cleared_at = $deletePayment->cleared_at;
            }

            $keepPayment->save();

            InvoicePaymentAllocation::query()
                ->where('payment_id', $deletePayment->id)
                ->update(['payment_id' => $keepPayment->id]);

            SquareWebhookEvent::query()
                ->where('payment_id', $deletePayment->id)
                ->update(['payment_id' => $keepPayment->id]);

            $deletePayment->delete();
        });

        $shouldEmailReceipt = $request->boolean('email_receipt', false);
        $receiptWasEmailed = false;
        if ($shouldEmailReceipt) {
            $keepPaymentId = $sourceIsSquareManaged ? $payment->id : $matchedPayment->id;
            $keepPayment = Payment::query()
                ->with(['user', 'allocations.invoice', 'allocations.taxAdjustment'])
                ->findOrFail($keepPaymentId);
            $receiptWasEmailed = $this->sendPaymentReceiptEmail($keepPayment);
        }

        if ($shouldEmailReceipt && ! $receiptWasEmailed) {
            $keepPaymentId = $sourceIsSquareManaged ? $payment->id : $matchedPayment->id;
            $deletePaymentId = $sourceIsSquareManaged ? $matchedPayment->id : $payment->id;
            session()->flash('message', 'Payment #'.$deletePaymentId.' was replaced with payment #'.$keepPaymentId.'. The updated receipt was not emailed because the payment is still pending clearance or has no allocations yet.');
            session()->flash('message-title', 'Payment replaced');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.payment.edit', $keepPaymentId);
        }

        $keepPaymentId = $sourceIsSquareManaged ? $payment->id : $matchedPayment->id;
        $deletePaymentId = $sourceIsSquareManaged ? $matchedPayment->id : $payment->id;
        session()->flash(
            'message',
            'Payment #'.$deletePaymentId.' was replaced with payment #'.$keepPaymentId.'.'.($receiptWasEmailed ? ' The updated receipt was emailed to the customer.' : '')
        );
        session()->flash('message-title', 'Payment replaced');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.payment.edit', $keepPaymentId);
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
            'idempotency_key' => ['nullable', 'string', 'max:45'],
            'autocomplete' => ['nullable', 'boolean'],
        ]);

        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : 'pay-'.$payment->id.'-'.now()->format('Uu');
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
            'idempotency_key' => ['nullable', 'string', 'max:45'],
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

        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : 'ref-'.$payment->id.'-'.now()->format('Uu');

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

        $refundPayment = DB::transaction(function () use ($payment, $refund, $refundValue, $validated) {
            $payment->gateway_provider = 'square';
            $currentRefunded = (int) ($payment->square_refunded_money_amount ?? 0);
            $paidValue = (int) ($payment->square_paid_money_amount ?? 0);
            $payment->square_refunded_money_amount = min($paidValue, max(0, $currentRefunded + $refundValue));
            $payment->square_gateway_updated_at = $this->squareDateTime($refund['updated_at'] ?? null) ?? $payment->square_gateway_updated_at;
            $payment->save();

            $reason = trim((string) ($validated['reason'] ?? 'Square refund'));
            return $this->createRefundPaymentRecord(
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

        $this->sendRefundReceiptEmail($payment, $refundPayment);

        session()->flash('message', 'Square refund created successfully');
        session()->flash('message-title', 'Refund created');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function refundManual(Request $request, Payment $payment): RedirectResponse
    {
        if ($this->isSquareManagedPayment($payment)) {
            session()->flash('message', 'This payment is managed by Square. Use the Square refund action.');
            session()->flash('message-title', 'Refund blocked');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $validated = $request->validate([
            'amount' => [($request->boolean('strict_amount') ? 'required' : 'nullable'), 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in([
                Payment::PAYMENT_METHOD_CASH,
                Payment::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
            'received_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
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
        $receivedOn = Carbon::parse((string) $validated['received_on'])->setSecond(0);
        $reference = trim((string) ($validated['reference'] ?? ''));

        $refundPayment = DB::transaction(function () use ($payment, $refundAmount, $reason, $validated, $receivedOn, $reference) {
            return $this->createRefundPaymentRecord(
                originalPayment: $payment,
                refundAmount: $refundAmount,
                reason: $reason !== '' ? $reason : 'Manual refund',
                paymentMethod: (string) $validated['payment_method'],
                gatewayProvider: null,
                gatewayStatus: null,
                gatewayReferenceId: null,
                receivedOn: $receivedOn,
                referenceOverride: $reference,
            );
        });

        $this->sendRefundReceiptEmail($payment, $refundPayment);

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
            'email_receipt' => ['nullable', 'boolean'],
            'bank_transfer_cleared' => ['nullable', 'boolean'],
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

        if (! $payment->isPendingBankTransfer()) {
            $this->syncInvoicesFromAllocations($invoiceIds);
        }
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
                        ->whereIn('status', [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT])
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
        ?Carbon $receivedOn = null,
        ?string $referenceOverride = null,
        $squareGatewayCreatedAt = null,
        $squareGatewayUpdatedAt = null
    ): Payment {
        $refundPayment = new Payment();
        $refundPayment->refund_of_payment_id = $originalPayment->id;
        $refundPayment->kind = Payment::KIND_REFUND;
        $refundPayment->user_id = $originalPayment->user_id;
        $refundPayment->created_by = Auth::id();
        $refundPayment->received_on = $receivedOn ?? now();
        $refundPayment->payment_method = trim((string) $paymentMethod) !== ''
            ? (string) $paymentMethod
            : (string) $originalPayment->payment_method;
        $defaultReference = trim(implode(' | ', array_filter([
            'Refund for payment #'.$originalPayment->id,
            $originalPayment->reference ? 'Original: '.$originalPayment->reference : null,
        ])));
        $refundPayment->reference = trim($referenceOverride ?? '') !== '' ? trim($referenceOverride) : $defaultReference;
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

    private function manualRefundDefaultReason(SquareRefundOperation $manualRefund, Payment $originalPayment): string
    {
        $queueLabel = 'refund queue item #'.((int) $manualRefund->id);
        $paymentLabel = 'payment #'.((int) $originalPayment->id);
        $squarePaymentId = trim((string) ($originalPayment->square_payment_id ?? ''));
        $squareLabel = $squarePaymentId !== '' ? ' Square payment '.$squarePaymentId : '';
        $failureMessage = trim((string) ($manualRefund->failure_message ?? ''));

        if ($failureMessage !== '') {
            return 'Square refund failed for '.$queueLabel.' ('.$paymentLabel.$squareLabel.'): '.$failureMessage;
        }

        return 'Manual refund recorded for '.$queueLabel.' ('.$paymentLabel.$squareLabel.')';
    }

    private function sendRefundReceiptEmail(Payment $originalPayment, Payment $refundPayment): void
    {
        $recipient = strtolower(trim((string) data_get($originalPayment, 'user.email', '')));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $originalPayment->loadMissing('user', 'allocations.invoice', 'allocations.taxAdjustment.invoice');
        $invoiceNumbers = $originalPayment->allocations
            ->map(function ($allocation): ?string {
                if ($allocation->invoice instanceof Invoice) {
                    return (string) $allocation->invoice->invoice_number;
                }

                if ($allocation->taxAdjustment?->invoice instanceof Invoice) {
                    return (string) $allocation->taxAdjustment->invoice->invoice_number;
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        $pdfBinary = $this->buildPaymentReceiptPdf($refundPayment)->output();
        if ($pdfBinary === '') {
            return;
        }

        dispatch(new SendEmail(
            $recipient,
            new PaymentReceiptPdf(
                recipientName: $originalPayment->user?->getName() ?: 'Customer',
                invoiceNumber: $invoiceNumbers->isNotEmpty()
                    ? $invoiceNumbers->implode(', ')
                    : ('Payment #'.$originalPayment->id),
                receiptNumber: (string) $refundPayment->id,
                amount: money(abs((float) $refundPayment->total_amount)),
                paidOn: $refundPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
                paymentMethod: Payment::paymentMethodLabel((string) ($refundPayment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
                receiptUrl: (string) ($refundPayment->square_receipt_url ?? ''),
                isRefund: true,
                pdfContent: $pdfBinary,
                pdfFilename: $this->getPaymentReceiptPdfFilename($refundPayment),
                invoiceSummary: $invoiceNumbers->isNotEmpty() ? 'Related invoice'.($invoiceNumbers->count() > 1 ? 's' : '').': '.$invoiceNumbers->implode(', ') : null,
            )
        ))->onQueue('mail');
    }

    private function sendPaymentReceiptEmail(Payment $payment): bool
    {
        if (! $payment->receiptCanBeEmailed()) {
            return false;
        }

        $payment->loadMissing('user', 'allocations.invoice', 'allocations.taxAdjustment');

        $recipient = strtolower(trim((string) ($payment->user?->email ?: '')));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$invoiceNumberDisplay, $invoiceSummary] = $this->paymentReceiptInvoiceAllocationSummary($payment);
        if ($invoiceNumberDisplay === '') {
            return false;
        }

        $pdfBinary = $this->buildPaymentReceiptPdf($payment)->output();
        if ($pdfBinary === '') {
            return false;
        }

        dispatch(new SendEmail(
            $recipient,
            new PaymentReceiptPdf(
                recipientName: $payment->user?->getName() ?: 'Customer',
                invoiceNumber: $invoiceNumberDisplay,
                receiptNumber: (string) $payment->id,
                amount: money(abs((float) $payment->total_amount)),
                paidOn: $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
                paymentMethod: Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
                receiptUrl: (string) ($payment->square_receipt_url ?? ''),
                isRefund: false,
                pdfContent: $pdfBinary,
                pdfFilename: $this->getPaymentReceiptPdfFilename($payment),
                invoiceSummary: $invoiceSummary,
                statusSummary: '',
                outstandingBeforeSummary: null,
                appliedAmountSummary: null,
                creditSummary: null,
                creditAppliedAmount: null,
                creditReferenceSummary: null,
                orderTotalAmount: null,
                hasInvoiceAttachment: false,
            )
        ))->onQueue('mail');

        return true;
    }

    private function resolveClearedAt(Payment $payment, array $validated, Request $request): ?Carbon
    {
        $paymentMethod = (string) ($validated['payment_method'] ?? $payment->payment_method ?? '');
        $existingClearedAt = $payment->cleared_at instanceof Carbon ? $payment->cleared_at : null;

        if ($existingClearedAt instanceof Carbon) {
            return $existingClearedAt;
        }

        if ($paymentMethod === Payment::PAYMENT_METHOD_BANK_TRANSFER) {
            return $request->boolean('bank_transfer_cleared')
                ? now()
                : null;
        }

        return now();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function paymentReceiptInvoiceAllocationSummary(Payment $payment): array
    {
        $invoiceAllocations = $payment->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice instanceof Invoice)
            ->map(function ($allocation): array {
                return [
                    'invoice_number' => trim((string) ($allocation->invoice->invoice_number ?? '')),
                    'amount' => (float) $allocation->allocated_amount,
                ];
            })
            ->filter(fn (array $row): bool => $row['invoice_number'] !== '')
            ->groupBy('invoice_number')
            ->map(function ($rows, string $invoiceNumber): array {
                return [
                    'invoice_number' => $invoiceNumber,
                    'amount' => round((float) collect($rows)->sum('amount'), 2),
                ];
            })
            ->values();

        if ($invoiceAllocations->isEmpty()) {
            return ['', null];
        }

        $invoiceNumbersDisplay = $invoiceAllocations->pluck('invoice_number')->implode(', ');
        if ($invoiceAllocations->count() === 1) {
            return [$invoiceNumbersDisplay, null];
        }

        $invoiceSummary = $invoiceAllocations
            ->map(function (array $row): string {
                return $row['invoice_number'].' ($'.number_format((float) $row['amount'], 2).' paid)';
            })
            ->implode("\n");

        return [$invoiceNumbersDisplay, $invoiceSummary];
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

        $hasTaxAdjustmentAllocations = $payment->allocations
            ->contains(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->taxAdjustment);
        [$allocationInvoiceNumbers, $allocationSummary] = $this->paymentReceiptInvoiceAllocationSummary($payment);

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
        if (! $hasTaxAdjustmentAllocations && $allocationInvoiceNumbers !== '') {
            $invoiceLabel = str_contains($allocationInvoiceNumbers, ',') ? 'Invoice Numbers' : 'Invoice Number';
            $invoiceSummary = $allocationSummary ?: $allocationInvoiceNumbers;
        }

        $amountRaw = (float) $payment->total_amount;
        $isRefund = $payment->isRefund();
        $gatewayProcessedAtRaw = trim((string) ($payment->square_gateway_updated_at ?? $payment->square_gateway_created_at ?? ''));
        $gatewayProcessedAt = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAt = \Illuminate\Support\Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (\Throwable) {
                $gatewayProcessedAt = '';
            }
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => $isRefund,
            'receiptTitle' => $isRefund ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $isRefund ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceLabel' => $invoiceLabel,
            'invoiceNumber' => $invoiceSummary,
            'invoiceSummary' => $allocationSummary,
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
        return ($payment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').($payment->id).'.pdf';
    }

    private function isSquareManagedPayment(Payment $payment): bool
    {
        if (trim((string) ($payment->square_payment_id ?? '')) !== '') {
            return true;
        }

        return strtolower(trim((string) ($payment->gateway_provider ?? ''))) === 'square';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    private function matchingEftposPaymentsForReplacement(Payment $payment): Collection
    {
        if (
            $payment->isRefund()
            || (string) ($payment->payment_method ?? '') !== Payment::PAYMENT_METHOD_EFTPOS
            || ! $payment->received_on instanceof Carbon
        ) {
            return collect();
        }

        $amount = round((float) $payment->total_amount, 2);
        $start = $payment->received_on->copy()->subDays(self::EFTPOS_MERGE_WINDOW_DAYS)->startOfDay();
        $end = $payment->received_on->copy()->addDays(self::EFTPOS_MERGE_WINDOW_DAYS)->endOfDay();

        return Payment::query()
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('payment_method', Payment::PAYMENT_METHOD_EFTPOS)
            ->where('id', '!=', $payment->id)
            ->whereBetween('received_on', [$start, $end])
            ->whereBetween('total_amount', [$amount - 0.005, $amount + 0.005])
            ->with(['user'])
            ->withCount(['refunds'])
            ->orderByDesc('received_on')
            ->get()
            ->filter(fn (Payment $candidate): bool => (int) ($candidate->refunds_count ?? 0) === 0
                && $this->isSquareManagedPayment($candidate) !== $this->isSquareManagedPayment($payment))
            ->values();
    }

    private function paymentReplacementDialogData(Payment $sourcePayment, iterable $candidatePayments): array
    {
        $sourceLabel = $this->isSquareManagedPayment($sourcePayment)
            ? 'Square EFTPOS'
            : 'manual EFTPOS';
        $counterpartyLabel = $this->isSquareManagedPayment($sourcePayment)
            ? 'manual EFTPOS'
            : 'Square EFTPOS';

        return [
            'headline' => 'Override '.$sourceLabel.' payment?',
            'description' => 'Compare this '.$sourceLabel.' entry with the matching '.$counterpartyLabel.' transaction before replacing it.',
            'action' => route('admin.payment.square.replace', $sourcePayment),
            'source' => $this->paymentReplacementDisplayData($sourcePayment),
            'candidates' => collect($candidatePayments)
                ->map(fn (Payment $payment): array => $this->paymentReplacementDisplayData($payment))
                ->values()
                ->all(),
        ];
    }

    private function paymentReplacementDisplayData(Payment $payment): array
    {
        $customerName = trim((string) ($payment->user?->getName() ?? ''));
        $reference = trim((string) ($payment->reference ?? ''));
        $notes = trim((string) ($payment->notes ?? ''));

        return [
            'id' => (int) $payment->id,
            'label' => 'Payment #'.((int) $payment->id),
            'date' => $payment->received_on?->format('M j, Y g:i a') ?? '-',
            'customer' => $customerName !== '' ? $customerName : 'No customer linked',
            'method' => Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')),
            'amount' => money((float) $payment->total_amount),
            'reference' => $reference !== '' ? $reference : '-',
            'notes' => $notes !== '' ? $notes : '-',
        ];
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

    /**
     * @param  iterable<Invoice>  $invoices
     * @return array<int, array{
     *     id: string,
     *     user_id: string,
     *     invoice_number: string,
     *     label: string,
     *     selection_label: string,
     *     search: string,
     *     customer_name: string,
     *     customer_email: string,
     *     status_label: string,
     *     payment_state_label: string,
     *     ticket_summary: string,
     *     remaining_amount: float,
     *     total_amount: float
     * }>
     */
    private function paymentInvoiceOptions(iterable $invoices): array
    {
        $options = [];

        foreach ($invoices as $invoice) {
            if (! $invoice instanceof Invoice) {
                continue;
            }

            $customerNameSource = $invoice->user instanceof User
                ? $invoice->user->getName()
                : (string) ($invoice->billing_name ?? '');
            $customerName = trim((string) $customerNameSource);
            if ($customerName === '') {
                $customerName = 'No customer';
            }

            $customerEmailSource = trim((string) ($invoice->billing_email ?? ''));
            if ($customerEmailSource === '' && $invoice->user instanceof User) {
                $customerEmailSource = (string) ($invoice->user->email ?? '');
            }
            $customerEmail = strtolower(trim($customerEmailSource));
            $statusLabel = Invoice::statusLabel((string) $invoice->status);
            $remainingAmount = round((float) $invoice->outstandingAmount(), 2);
            $totalAmount = round((float) $invoice->dueAmount(), 2);
            $paidAmount = round(max(0, $totalAmount - $remainingAmount), 2);
            $invoiceNumber = trim((string) $invoice->invoice_number);
            $ticketSummary = $this->paymentInvoiceTicketSummary($invoice);
            $paymentStateLabel = $remainingAmount <= 0.0001
                ? 'Paid in full'
                : ($paidAmount > 0.0001
                    ? 'Partially paid'
                    : 'Unpaid');
            $summaryParts = array_filter([
                'Remaining '.money($remainingAmount),
                'Total '.money($totalAmount),
                $ticketSummary !== '' ? $ticketSummary : null,
            ]);

            $labelParts = array_filter([
                $invoiceNumber,
                $customerName.($customerEmail !== '' ? ' <'.$customerEmail.'>' : ''),
                $statusLabel,
                implode(' | ', $summaryParts),
            ]);

            $searchParts = array_filter([
                (string) $invoice->invoice_number,
                strtolower($customerName),
                $customerEmail,
                strtolower($statusLabel),
                strtolower($ticketSummary),
                strtolower((string) $invoice->id),
            ]);

            $options[] = [
                'id' => (string) $invoice->id,
                'user_id' => (string) ($invoice->user_id ?? ''),
                'invoice_number' => $invoiceNumber,
                'label' => implode(' | ', $labelParts),
                'selection_label' => implode(' · ', array_filter([
                    $invoiceNumber !== '' ? 'Invoice '.$invoiceNumber : 'Invoice #'.$invoice->id,
                    $customerName,
                ])),
                'search' => implode(' ', $searchParts),
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'status_label' => $statusLabel,
                'payment_state_label' => $paymentStateLabel,
                'ticket_summary' => $ticketSummary,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'total_amount' => $totalAmount,
            ];
        }

        return $options;
    }

    private function paymentInvoiceTicketSummary(Invoice $invoice): string
    {
        $tickets = $invoice->tickets
            ->filter(fn (Ticket $ticket): bool => trim((string) ($ticket->reference_code ?? '')) !== '' || trim((string) ($ticket->id ?? '')) !== '')
            ->sortBy(fn (Ticket $ticket): int => (int) $ticket->id)
            ->values();

        if ($tickets->isEmpty()) {
            return '';
        }

        $ticketCount = $tickets->count();
        $ticketRefs = $tickets
            ->take(3)
            ->map(function (Ticket $ticket): string {
                $reference = trim((string) ($ticket->reference_code ?: '#'.$ticket->id));
                $holder = trim((string) trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')));
                $status = trim((string) $ticket->customer_status_label);

                $details = array_filter([$holder !== '' ? $holder : null, $status !== '' ? $status : null]);
                if ($details === []) {
                    return $reference;
                }

                return $reference.' ('.implode(' - ', $details).')';
            })
            ->implode(', ');

        return $ticketCount.' ticket'.($ticketCount === 1 ? '' : 's').': '.$ticketRefs;
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
