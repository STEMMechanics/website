<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Mail\InvoiceDocumentBundle;
use App\Mail\InvoicePaymentLink;
use App\Mail\PaymentReceiptPdf;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\TaxAdjustment;
use App\Models\Ticket;
use App\Models\Token;
use App\Models\StoreOrder;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\SquareApiService;
use App\Services\StoreOrderService;
use App\Support\InvoiceDueDate;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly StoreOrderService $storeOrders
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with(['user', 'lines', 'allocations.customerPayment.refundOf', 'taxAdjustments']);

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && in_array($status, Invoice::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhereHas('taxAdjustments', function ($adjustmentQuery) use ($search) {
                        $adjustmentQuery->where('adjustment_number', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%');
                    });
            });
        }

        $summaryInvoices = (clone $query)->get();
        $summaryOutstandingAmount = round($summaryInvoices->sum(function (Invoice $invoice): float {
            return $this->outstandingAmountForIndexInvoice($invoice);
        }), 2);
        $summaryOverdueAmount = round($summaryInvoices->sum(function (Invoice $invoice): float {
            return $invoice->isOverdue() ? $this->outstandingAmountForIndexInvoice($invoice) : 0;
        }), 2);

        $invoices = $query->orderBy('issue_date', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('admin.invoice.index', [
            'invoices' => $invoices,
            'summaryOutstandingAmount' => $summaryOutstandingAmount,
            'summaryOverdueAmount' => $summaryOverdueAmount,
        ]);
    }

    public function create()
    {
        return view('admin.invoice.edit', [
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'quotes' => Quote::query()->with('user')->orderByDesc('quote_date')->orderByDesc('created_at')->get(),
            'nextInvoiceNumber' => $this->documentNumbers->previewInvoiceNumber(),
            'lineItemsSeed' => [],
        ]);
    }

    private function outstandingAmountForIndexInvoice(Invoice $invoice): float
    {
        $settlementKind = $invoice->expectedSettlementKind();
        $allocated = (float) $invoice->allocations
            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0)
            ->filter(fn ($allocation) => (string) ($allocation->customerPayment->kind ?? Payment::KIND_PAYMENT) === $settlementKind)
            ->sum('allocated_amount');

        return max(0, round($invoice->dueAmount() - $allocated, 2));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $this->validateQuoteUserMatch($validated['quote_id'] ?? null, $validated['user_id'] ?? null);
        $lineItems = $this->extractLineItems($request);

        $invoice = new Invoice();
        $invoice->fill($validated);
        $invoice->status = $request->boolean('issue_now')
            ? Invoice::STATUS_ISSUED
            : Invoice::STATUS_DRAFT;
        $invoice->subtotal_amount = $this->calculateSubtotal($lineItems);
        $invoice->gst_amount = $this->calculateGst($lineItems);
        $invoice->total_amount = round((float) $invoice->subtotal_amount + (float) $invoice->gst_amount, 2);
        if (! $invoice->due_date) {
            $invoice->due_date = InvoiceDueDate::fromIssueDate($invoice->issue_date);
        }
        if ($invoice->status !== Invoice::STATUS_DRAFT && ! $invoice->issued_at) {
            $invoice->issued_at = now();
        }

        $invoice->save();
        $this->replaceInvoiceLines($invoice, $lineItems);
        $invoice->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));
        if ($request->has('private_files')) {
            $invoice->updateFiles($request->input('private_files'), 'private');
        }

        session()->flash('message', 'Invoice has been created');
        session()->flash('message-title', 'Invoice created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.invoice.index');
    }

    public function edit(Invoice $invoice)
    {
        $invoice->loadMissing(
            'lines',
            'privateFinanceFiles',
            'taxAdjustments.lines',
            'allocations.customerPayment.user',
            'allocations.customerPayment.refundOf',
            'allocations.customerPayment.refunds.user',
            'storeOrders',
        );

        return view('admin.invoice.edit', [
            'invoice' => $invoice,
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'quotes' => Quote::query()->with('user')->orderByDesc('quote_date')->orderByDesc('created_at')->get(),
            'lineItemsSeed' => $this->invoiceLineItemsForPayload($invoice),
        ]);
    }

    public function adjustmentIndex(Invoice $invoice): RedirectResponse
    {
        return redirect()->to(route('admin.invoice.edit', $invoice).'#tax-adjustments');
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $this->validateRequest($request, $invoice);
        $quoteUserId = $invoice->canEditContents()
            ? ($validated['user_id'] ?? null)
            : ($invoice->user_id ?? null);
        $this->validateQuoteUserMatch($validated['quote_id'] ?? null, $quoteUserId);
        $nextStatus = $invoice->canEditContents()
            ? ($request->boolean('issue_now') ? Invoice::STATUS_ISSUED : Invoice::STATUS_DRAFT)
            : (string) $invoice->status;
        if (! $invoice->canTransitionTo($nextStatus)) {
            session()->flash('message', 'Invalid invoice status transition requested.');
            session()->flash('message-title', 'Update blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.invoice.edit', $invoice);
        }

        if (! $invoice->canEditContents()) {
            $invoice->purchase_order_number = $validated['purchase_order_number'] ?? null;
            $invoice->notes = $validated['notes'] ?? null;
            $invoice->quote_id = $validated['quote_id'] ?? null;
            $invoice->save();
            $invoice->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));
            if ($request->has('private_files')) {
                $invoice->updateFiles($request->input('private_files'), 'private');
            }

            session()->flash('message', 'Purchase order number and notes have been updated');
            session()->flash('message-title', 'Invoice updated');
            session()->flash('message-type', 'success');

            return redirect()->back();
        }

        $lineItems = $this->extractLineItems($request);

        $invoice->fill($validated);
        $invoice->status = $nextStatus;
        $invoice->subtotal_amount = $this->calculateSubtotal($lineItems);
        $invoice->gst_amount = $this->calculateGst($lineItems);
        $invoice->total_amount = round((float) $invoice->subtotal_amount + (float) $invoice->gst_amount, 2);
        if (! $invoice->due_date) {
            $invoice->due_date = InvoiceDueDate::fromIssueDate($invoice->issue_date);
        }
        if ($invoice->status !== Invoice::STATUS_DRAFT && ! $invoice->issued_at) {
            $invoice->issued_at = now();
        }

        $invoice->save();
        $this->replaceInvoiceLines($invoice, $lineItems);
        $invoice->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));
        if ($request->has('private_files')) {
            $invoice->updateFiles($request->input('private_files'), 'private');
        }

        if ($request->boolean('save_and_email') && (string) $invoice->status !== Invoice::STATUS_DRAFT) {
            session()->flash('invoice-email-open', true);
        }

        session()->flash('message', 'Invoice has been updated');
        session()->flash('message-title', 'Invoice updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $hasLinkedTickets = Ticket::query()->where('invoice_id', $invoice->id)->exists();
            if ($hasLinkedTickets) {
                session()->flash('message', 'Draft invoice has linked tickets and cannot be deleted.');
                session()->flash('message-title', 'Delete blocked');
                session()->flash('message-type', 'danger');

                return redirect()->route('admin.invoice.edit', $invoice);
            }

            $invoice->delete();

            session()->flash('message', 'Draft invoice deleted.');
            session()->flash('message-title', 'Invoice deleted');
            session()->flash('message-type', 'success');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => route('admin.invoice.index'),
                ]);
            }

            return redirect()->route('admin.invoice.index');
        }

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            session()->flash('message', 'Invoice is already cancelled.');
            session()->flash('message-title', 'No changes');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.invoice.index');
        }

        $allocated = (float) $invoice->allocations()->sum('allocated_amount');
        if ($allocated > 0.0001) {
            session()->flash('message', 'Invoice has payments allocated. Reverse/refund payments before cancellation.');
            session()->flash('message-title', 'Cancellation blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.invoice.edit', $invoice);
        }
        if (! $invoice->canTransitionTo(Invoice::STATUS_CANCELLED)) {
            session()->flash('message', 'This invoice cannot be cancelled from its current status.');
            session()->flash('message-title', 'Cancellation blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.invoice.edit', $invoice);
        }

        $invoice->status = Invoice::STATUS_CANCELLED;
        if (! $invoice->issued_at) {
            $invoice->issued_at = now();
        }
        $invoice->save();

        session()->flash('message', 'Invoice has been cancelled');
        session()->flash('message-title', 'Invoice cancelled');
        session()->flash('message-type', 'warning');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.invoice.index'),
            ]);
        }

        return redirect()->route('admin.invoice.index');
    }

    public function pdf(Invoice $invoice)
    {
        return $this->buildInvoicePdf($invoice)->stream($this->getInvoicePdfFilename($invoice));
    }

    public function accountIndex(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->with(['user', 'allocations.customerPayment', 'taxAdjustments'])
            ->where('user_id', (string) auth()->id())
            ->where('status', '!=', Invoice::STATUS_DRAFT);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%');
            });
        }

        $invoices = $query->orderBy('issue_date', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('account.invoices', [
            'invoices' => $invoices,
        ]);
    }

    public function pdfWithAdjustments(Invoice $invoice)
    {
        return $this->buildInvoicePdf($invoice, includeAdjustments: true)->stream($this->getInvoicePdfFilename($invoice));
    }

    public function accountShow(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        return $this->renderInvoicePortal($invoice, null, true);
    }

    public function accountReceipts(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        $allocatedPaymentIds = $this->receiptPaymentIdsForInvoice($invoice);

        $query = Payment::query()->with(['refundOf', 'allocations.invoice', 'refundOf.allocations.invoice']);
        if ($allocatedPaymentIds === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($builder) use ($allocatedPaymentIds) {
                $builder->whereIn('id', $allocatedPaymentIds)
                    ->orWhereIn('refund_of_payment_id', $allocatedPaymentIds);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search', ''));
            $query->where(function ($builder) use ($search) {
                $builder->where('id', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%')
                    ->orWhere('payment_method', 'like', '%'.$search.'%');
            });
        }

        $receipts = $query->orderByDesc('received_on')->orderByDesc('created_at')->paginate(20)->onEachSide(1);

        return view('account.invoice-receipts', [
            'invoice' => $invoice,
            'receipts' => $receipts,
        ]);
    }

    public function accountReceiptShow(Request $request, Invoice $invoice, Payment $payment): View
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        if (! $this->paymentLinkedToInvoiceForReceipt($invoice, $payment)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $payment->loadMissing('refundOf');
        $creditAppliedAmount = $this->paymentReceiptCreditAppliedAmount($invoice, $payment);
        $creditReferenceSummary = $this->paymentReceiptCreditReferenceSummary($invoice, $payment);
        $paymentMethodLabel = $this->paymentReceiptMethodLabel($invoice, $payment, $creditAppliedAmount);

        return view('account.invoice-receipt-show', [
            'invoice' => $invoice,
            'receipt' => $payment,
            'paymentMethodLabel' => $paymentMethodLabel,
            'creditAppliedAmount' => $creditAppliedAmount,
            'creditReferenceSummary' => $creditReferenceSummary,
            'invoiceTotalAmount' => round((float) $invoice->total_amount, 2),
        ]);
    }

    public function accountPay(Request $request, Invoice $invoice, SquareApiService $squareApi): RedirectResponse
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);
        if ((float) $invoice->total_amount <= 0) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $this->processInvoiceSquarePayment($request, $invoice, $squareApi, true, null);
    }

    public function publicPayShow(Invoice $invoice): View
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true) || (float) $invoice->total_amount <= 0) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $this->renderInvoicePortal($invoice, null, false, true);
    }

    public function publicPayProcess(Request $request, Invoice $invoice, SquareApiService $squareApi): RedirectResponse
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true) || (float) $invoice->total_amount <= 0) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $this->processInvoiceSquarePayment($request, $invoice, $squareApi, false, null);
    }

    public function publicEmailDocuments(Request $request, Invoice $invoice): RedirectResponse
    {
        $returnUrl = $this->safePublicReturnUrl(
            $request,
            route('invoice.public.pay.show', $invoice)
        );

        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $recipient = $this->resolveInvoiceContactEmail($invoice);
        if ($recipient === '') {
            session()->flash('message', 'Unable to email documents: this invoice does not have a contact email.');
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->to($returnUrl);
        }

        $invoice->loadMissing('user', 'allocations.customerPayment', 'taxAdjustments.lines', 'storeOrders');
        $attachments = [];

        $invoicePdf = $this->buildInvoicePdf($invoice)->output();
        $attachments[] = [
            'filename' => $this->getInvoicePdfFilename($invoice),
            'content' => $invoicePdf,
            'mime' => 'application/pdf',
        ];

        foreach ($invoice->taxAdjustments as $adjustment) {
            $attachments[] = [
                'filename' => 'tax-adjustment-'.((string) $adjustment->adjustment_number).'.pdf',
                'content' => $this->buildTaxAdjustmentPdf($invoice, $adjustment)->output(),
                'mime' => 'application/pdf',
            ];
        }

        $paymentIds = $this->receiptPaymentIdsForInvoice($invoice);
        if ($paymentIds !== []) {
            $payments = Payment::query()
                ->where(function ($builder) use ($paymentIds) {
                    $builder->whereIn('id', $paymentIds)
                        ->orWhereIn('refund_of_payment_id', $paymentIds);
                })
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $attachments[] = [
                    'filename' => $this->getPaymentReceiptPdfFilename($payment),
                    'content' => $this->buildPaymentReceiptPdf($invoice, $payment)->output(),
                    'mime' => 'application/pdf',
                ];
            }
        }

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();
        $linkedStoreOrder = $invoice->storeOrders
            ->sortByDesc(fn (StoreOrder $order) => optional($order->created_at)->timestamp ?? (int) $order->id)
            ->first();

        try {
            dispatch(new SendEmail($recipient, new InvoiceDocumentBundle(
                recipientName: $invoice->user?->getName() ?: (string) ($invoice->billing_name ?: $recipient),
                invoiceNumber: (string) $invoice->invoice_number,
                orderNumber: $linkedStoreOrder instanceof StoreOrder ? (string) $linkedStoreOrder->order_number : null,
                attachments: $attachments,
                outstandingAmount: $invoice->outstandingAmount(),
                payUrl: $invoice->outstandingAmount() > 0.0001 ? route('invoice.public.pay.show', $invoice) : null,
                initiatedByEmail: $initiatedByEmail,
                initiatedByName: $initiatedByName,
            )))->onQueue('mail');
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Unable to email documents right now.');
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->to($returnUrl);
        }

        session()->flash('message', 'Invoice, tax adjustment, and receipt documents have been emailed.');
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->to($returnUrl);
    }

    public function accountPdf(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessible($invoice);

        return $this->invoicePdfResponse($request, $invoice, true);
    }

    public function showByMagicToken(Request $request): View
    {
        $token = $this->resolveInvoiceMagicToken($request);
        if (! $token) {
            session()->flash('message', 'That invoice link has expired or is invalid.');
            session()->flash('message-title', 'Link invalid');
            session()->flash('message-type', 'danger');

            abort(404);
        }

        $invoiceId = (int) ($token->data['invoice_id'] ?? 0);
        $invoice = Invoice::query()->with('user')->findOrFail($invoiceId);

        return $this->renderInvoicePortal($invoice, (string) $token->id, false);
    }

    public function magicPdf(Request $request, Invoice $invoice)
    {
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        return $this->invoicePdfResponse($request, $invoice, true);
    }

    public function magicPay(Request $request, Invoice $invoice, SquareApiService $squareApi): RedirectResponse
    {
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        $token = trim((string) $request->input('token', $request->query('token', '')));
        if ($token === '') {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $this->processInvoiceSquarePayment($request, $invoice, $squareApi, false, $token);
    }

    public function receiptPdf(Request $request, Invoice $invoice, Payment $payment)
    {
        if (! $this->paymentLinkedToInvoiceForReceipt($invoice, $payment)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $pdf = $this->buildPaymentReceiptPdf($invoice, $payment);
        $filename = $this->getPaymentReceiptPdfFilename($payment);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    private function invoicePdfResponse(Request $request, Invoice $invoice, bool $includeAdjustments = false)
    {
        $pdf = $this->buildInvoicePdf($invoice, includeAdjustments: $includeAdjustments);
        $filename = $this->getInvoicePdfFilename($invoice);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function accountReceiptPdf(Request $request, Invoice $invoice, Payment $payment)
    {
        $this->authorize('view', $invoice);
        $this->abortIfInvoiceNotAccessibleForRequest($request, $invoice);

        if (! $this->paymentLinkedToInvoiceForReceipt($invoice, $payment)) {
            $authUserId = (string) ($request->user()->id ?? '');
            $paymentUserId = (string) ($payment->user_id ?? '');

            if ($authUserId === '' || $paymentUserId === '' || $authUserId !== $paymentUserId) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $pdf = $this->buildPaymentReceiptPdf($invoice, $payment);
        $filename = $this->getPaymentReceiptPdfFilename($payment);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function emailPdf(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->loadMissing('user');
        $emailMessage = trim((string) $request->input('email_message', ''));
        if ($emailMessage === '') {
            $emailMessage = $this->defaultInvoiceEmailMessage($invoice);
        }
        $invoiceDueDate = $invoice->due_date?->format('M j, Y');
        $invoiceOutstanding = (float) $invoice->outstandingAmount();

        $recipients = $this->resolveInvoiceEmailRecipients($request, $invoice);
        $ccRecipients = $this->resolveInvoiceEmailCcRecipients($request);

        $pdfBinary = $this->buildInvoicePdf($invoice)->output();

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();

        try {
            foreach ($recipients as $recipient) {
                $mailable = new FinanceDocumentPdf(
                    documentType: 'invoice',
                    documentNumber: $invoice->invoice_number,
                    recipientName: $invoice->user?->getName() ?? $recipient,
                    pdfContent: $pdfBinary,
                    pdfFilename: $this->getInvoicePdfFilename($invoice),
                    fullMessage: $emailMessage,
                    documentTotal: (float) $invoice->total_amount,
                    documentOutstanding: $invoiceOutstanding,
                    documentDue: $invoiceDueDate,
                    initiatedByEmail: $initiatedByEmail,
                    initiatedByName: $initiatedByName,
                    payUrl: route('invoice.public.pay.show', $invoice),
                );
                $normalizedCcRecipients = [];
                foreach ($ccRecipients as $ccEmail) {
                    $normalizedCcRecipients[strtolower($ccEmail)] = $ccEmail;
                }

                foreach (array_values($normalizedCcRecipients) as $ccEmail) {
                    if (strcasecmp($ccEmail, $recipient) !== 0) {
                        $mailable->cc($ccEmail);
                    }
                }

                dispatch(new SendEmail($recipient, $mailable))->onQueue('mail');
            }
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Invoice email failed: '.$e->getMessage());
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $this->appendInvoiceEmailPrivateNote($invoice, $recipients, $initiatedByName);

        session()->flash('message', 'Invoice PDF emailed to '.implode(', ', $recipients));
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function emailPaymentLink(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->loadMissing('user');
        $emailMessage = trim((string) $request->input('payment_link_email_message', $request->input('email_message', '')));
        if ($emailMessage === '') {
            $emailMessage = null;
        }

        $recipients = $this->resolveInvoicePaymentLinkRecipients($request, $invoice);
        $ccRecipients = $this->resolveInvoicePaymentLinkCcRecipients($request);

        $payUrl = route('invoice.public.pay.show', $invoice);
        $pdfUrl = route('invoice.public.pay.show', $invoice);

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();

        try {
            foreach ($recipients as $recipient) {
                $mailable = new InvoicePaymentLink(
                    invoiceNumber: (string) $invoice->invoice_number,
                    recipientName: $invoice->user?->getName() ?? (string) ($invoice->billing_name ?: $recipient),
                    payUrl: $payUrl,
                    pdfUrl: $pdfUrl,
                    customMessage: $emailMessage,
                    initiatedByEmail: $initiatedByEmail,
                    initiatedByName: $initiatedByName,
                );

                $allCcRecipients = $ccRecipients;

                $normalizedCcRecipients = [];
                foreach ($allCcRecipients as $ccEmail) {
                    $normalizedCcRecipients[strtolower($ccEmail)] = $ccEmail;
                }

                foreach (array_values($normalizedCcRecipients) as $ccEmail) {
                    if (strcasecmp($ccEmail, $recipient) !== 0) {
                        $mailable->cc($ccEmail);
                    }
                }

                dispatch(new SendEmail($recipient, $mailable))->onQueue('mail');
            }
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Payment link email failed: '.$e->getMessage());
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Invoice payment link emailed to '.implode(', ', $recipients));
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function paymentLink(Request $request, Invoice $invoice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'url' => route('invoice.public.pay.show', $invoice),
        ]);
    }

    private function renderInvoicePortal(Invoice $invoice, ?string $accessToken, bool $isAccountView, bool $isPublic = false): View
    {
        $invoice->loadMissing('user', 'quote', 'lines', 'allocations.customerPayment', 'taxAdjustments.lines', 'storeOrders');
        $this->appendReissueNotesToInvoiceLines($invoice);
        $settlementKind = $invoice->expectedSettlementKind();
        $grossAllocated = round((float) $invoice->allocations
            ->filter(function ($allocation) use ($settlementKind) {
                if (! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? Payment::KIND_PAYMENT) === $settlementKind
                    && ((float) $allocation->allocated_amount) > 0;
            })
            ->sum('allocated_amount'), 2);
        $refundedAllocated = round(abs((float) $invoice->allocations
            ->filter(function ($allocation) use ($settlementKind) {
                if (! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? Payment::KIND_PAYMENT) === $settlementKind
                    && ((float) $allocation->allocated_amount) < 0;
            })
            ->sum('allocated_amount')), 2);
        $netAllocated = round(max(0, $grossAllocated - $refundedAllocated), 2);
        $allocated = $this->invoiceAllocatedTotal($invoice);
        $outstanding = $invoice->outstandingAmount();
        $adjustmentTotal = (float) $invoice->issuedAdjustmentTotalAmount();
        $adjustmentGstTotal = $invoice->relationLoaded('taxAdjustments')
            ? round((float) $invoice->taxAdjustments->sum('gst_amount'), 2)
            : round((float) $invoice->taxAdjustments()->sum('gst_amount'), 2);
        $adjustedTotal = round((float) $invoice->total_amount + $adjustmentTotal, 2);
        $adjustedGst = round((float) $invoice->gst_amount + $adjustmentGstTotal, 2);
        $receiptLinks = $invoice->allocations
            ->filter(function ($allocation) use ($invoice) {
                if (((float) $allocation->allocated_amount) <= 0 || ! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? Payment::KIND_PAYMENT) === $invoice->expectedSettlementKind();
            })
            ->map(function ($allocation) use ($invoice, $isAccountView) {
                $payment = $allocation->customerPayment;
                $baseRoute = $isAccountView ? 'account.invoice.receipt.pdf' : 'invoice.receipt.pdf';
                $viewUrl = $isAccountView
                    ? route($baseRoute, ['invoice' => $invoice, 'payment' => $payment])
                    : URL::signedRoute($baseRoute, ['invoice' => $invoice, 'payment' => $payment]);
                $downloadUrl = $isAccountView
                    ? route($baseRoute, ['invoice' => $invoice, 'payment' => $payment, 'download' => 1])
                    : URL::signedRoute($baseRoute, ['invoice' => $invoice, 'payment' => $payment, 'download' => 1]);

                return [
                    'payment_id' => (int) $payment->id,
                    'view_url' => $viewUrl,
                    'download_url' => $downloadUrl,
                ];
            })
            ->unique('payment_id')
            ->values()
            ->all();

        $linkedQuote = $invoice->quote;
        $linkedQuoteUrl = $isAccountView && $linkedQuote instanceof Quote
            ? route('account.quote.show', $linkedQuote)
            : null;
        $linkedStoreOrder = $invoice->storeOrders
            ->sortByDesc(fn ($order) => optional($order->created_at)->timestamp ?? (int) $order->id)
            ->first();
        $linkedStoreOrderUrl = $linkedStoreOrder instanceof StoreOrder
            ? ($isAccountView
                ? route('account.order.show', $linkedStoreOrder)
                : route('shop.order.tracking', (string) $linkedStoreOrder->access_token))
            : null;

        return view('invoice.portal', [
            'invoice' => $invoice,
            'allocatedAmount' => $allocated,
            'grossAllocatedAmount' => $grossAllocated,
            'refundedAllocatedAmount' => $refundedAllocated,
            'netAllocatedAmount' => $netAllocated,
            'outstandingAmount' => $outstanding,
            'adjustmentTotalAmount' => $adjustmentTotal,
            'adjustmentGstAmount' => $adjustmentGstTotal,
            'adjustedTotalAmount' => $adjustedTotal,
            'adjustedGstAmount' => $adjustedGst,
            'receiptLinks' => $receiptLinks,
            'accountReceiptsUrl' => $isAccountView ? route('account.invoice.receipts', $invoice) : null,
            'linkedQuote' => $linkedQuote,
            'linkedQuoteUrl' => $linkedQuoteUrl,
            'linkedStoreOrder' => $linkedStoreOrder,
            'linkedStoreOrderUrl' => $linkedStoreOrderUrl,
            'accessToken' => $accessToken,
            'isAccountView' => $isAccountView,
            'isPublic' => $isPublic,
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
        ]);
    }

    private function processInvoiceSquarePayment(
        Request $request,
        Invoice $invoice,
        SquareApiService $squareApi,
        bool $isAccountView,
        ?string $token
    ): RedirectResponse {
        $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
        ], [
            'source_id.required' => 'Card details are required.',
        ]);

        if (! $squareApi->isEnabled()) {
            return $this->invoicePaymentErrorRedirect($invoice, $isAccountView, $token, 'Credit card payments are not available right now.');
        }

        $locationId = (string) config('services.square.location_id');
        if ($locationId === '') {
            return $this->invoicePaymentErrorRedirect($invoice, $isAccountView, $token, 'Square location is not configured.');
        }

        $createdPaymentId = null;

        try {
            DB::transaction(function () use ($request, $invoice, $locationId, $squareApi, &$createdPaymentId): void {
                $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                $outstandingAmount = $lockedInvoice->outstandingAmount();

                if ($outstandingAmount <= 0) {
                    throw ValidationException::withMessages([
                        'source_id' => 'This invoice has already been paid.',
                    ]);
                }

                $customerPayment = new Payment();
                $customerPayment->user_id = $lockedInvoice->user_id ?: auth()->id();
                $customerPayment->created_by = auth()->id();
                $customerPayment->kind = Payment::KIND_PAYMENT;
                $customerPayment->received_on = now();
                $customerPayment->payment_method = 'credit_card';
                $customerPayment->reference = 'Invoice '.$lockedInvoice->invoice_number;
                $customerPayment->total_amount = $outstandingAmount;
                $invoiceTotal = max(0.01, (float) $lockedInvoice->total_amount);
                $invoiceGst = max(0.0, (float) $lockedInvoice->gst_amount);
                $customerPayment->gst_amount = round(min($invoiceGst, ($outstandingAmount / $invoiceTotal) * $invoiceGst), 2);
                $customerPayment->notes = 'Online invoice payment';
                $customerPayment->save();
                $amountCents = (int) round($outstandingAmount * 100);

                $response = $squareApi->createPayment([
                    'idempotency_key' => 'inv-'.$lockedInvoice->id.'-pay-'.$customerPayment->id.'-amt-'.$amountCents,
                    'source_id' => trim((string) $request->input('source_id')),
                    'location_id' => $locationId,
                    'reference_id' => 'payment:'.$customerPayment->id,
                    'amount_money' => [
                        'amount' => $amountCents,
                        'currency' => 'AUD',
                    ],
                    'autocomplete' => true,
                    'note' => 'Invoice '.$lockedInvoice->invoice_number.' payment',
                ]);

                $payment = (array) ($response['payment'] ?? []);
                if ($payment === []) {
                    throw new RuntimeException('Credit card payment failed with an invalid Square response.');
                }
                $squareStatus = strtoupper(trim((string) ($payment['status'] ?? 'UNKNOWN')));
                if ($squareStatus !== 'COMPLETED') {
                    $statusDetail = trim((string) ($payment['card_details']['status'] ?? ''));
                    $statusMessage = $statusDetail !== ''
                        ? 'Square status: '.$squareStatus.' (card: '.$statusDetail.')'
                        : 'Square status: '.$squareStatus;

                    throw new RuntimeException('Credit card payment was not completed. '.$statusMessage);
                }

                $customerPayment->gateway_provider = 'square';
                $customerPayment->gateway_status = (string) ($payment['status'] ?? 'UNKNOWN');
                $customerPayment->gateway_reference_id = (string) ($payment['reference_id'] ?? 'payment:'.$customerPayment->id);
                $customerPayment->square_payment_id = (string) ($payment['id'] ?? null);
                $customerPayment->square_order_id = (string) ($payment['order_id'] ?? null);
                $customerPayment->square_location_id = (string) ($payment['location_id'] ?? null);
                $customerPayment->square_receipt_url = (string) ($payment['receipt_url'] ?? null);
                $customerPayment->square_card_brand = (string) ($payment['card_details']['card']['card_brand'] ?? null);
                $customerPayment->square_card_last4 = (string) ($payment['card_details']['card']['last_4'] ?? null);
                $customerPayment->square_paid_money_amount = (int) ($payment['amount_money']['amount'] ?? 0);
                $customerPayment->square_gateway_created_at = $this->squareDateTime($payment['created_at'] ?? null);
                $customerPayment->square_gateway_updated_at = $this->squareDateTime($payment['updated_at'] ?? null);
                $customerPayment->save();
                $createdPaymentId = (int) $customerPayment->id;

                $lockedInvoice->allocations()->create([
                    'payment_id' => $customerPayment->id,
                    'allocated_amount' => $outstandingAmount,
                ]);

                $this->syncInvoicePaidState($lockedInvoice, $customerPayment);
            });
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            if ($message === '') {
                $message = 'Unable to process payment right now.';
            }

            return $this->invoicePaymentErrorRedirect($invoice, $isAccountView, $token, $message);
        } catch (RuntimeException $e) {
            report($e);

            return $this->invoicePaymentErrorRedirect(
                $invoice,
                $isAccountView,
                $token,
                $squareApi->userFacingPaymentErrorMessage($e->getMessage())
            );
        } catch (Throwable $e) {
            report($e);

            return $this->invoicePaymentErrorRedirect($invoice, $isAccountView, $token, 'Unable to process payment right now.');
        }

        if (is_int($createdPaymentId) && $createdPaymentId > 0) {
            try {
                $customerPayment = Payment::query()->find($createdPaymentId);
                if ($customerPayment instanceof Payment) {
                    $receiptViewUrl = $isAccountView
                        ? route('account.invoice.receipt.pdf', ['invoice' => $invoice->id, 'payment' => $customerPayment->id])
                        : URL::temporarySignedRoute(
                            'invoice.receipt.pdf',
                            now()->addDays(30),
                            ['invoice' => $invoice->id, 'payment' => $customerPayment->id]
                        );
                    $receiptDownloadUrl = $isAccountView
                        ? route('account.invoice.receipt.pdf', ['invoice' => $invoice->id, 'payment' => $customerPayment->id, 'download' => 1])
                        : URL::temporarySignedRoute(
                            'invoice.receipt.pdf',
                            now()->addDays(30),
                            ['invoice' => $invoice->id, 'payment' => $customerPayment->id, 'download' => 1]
                        );
                    session()->flash('payment_receipt_view_url', $receiptViewUrl);
                    session()->flash('payment_receipt_download_url', $receiptDownloadUrl);

                    $this->sendPaymentReceiptEmail($invoice, $customerPayment);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $invoice->loadMissing('storeOrders');
        $successMessage = $invoice->storeOrders->isNotEmpty()
            ? 'Payment completed successfully. Your order email and receipt have been emailed.'
            : 'Payment completed successfully. Your receipt has been emailed.';

        session()->flash('message', $successMessage);
        session()->flash('message-title', 'Payment success');
        session()->flash('message-type', 'success');

        return $this->invoicePaymentSuccessRedirect($invoice, $isAccountView, $token);
    }

    private function invoicePaymentSuccessRedirect(Invoice $invoice, bool $isAccountView, ?string $token): RedirectResponse
    {
        if ($isAccountView) {
            return redirect()->route('account.invoice.show', $invoice);
        }

        if ($token === null) {
            return redirect()->route('invoice.public.pay.show', $invoice);
        }

        return redirect()->route('invoice.magic', ['token' => $token]);
    }

    private function invoicePaymentErrorRedirect(Invoice $invoice, bool $isAccountView, ?string $token, string $message): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('message-title', 'Payment failed');
        session()->flash('message-type', 'danger');

        if ($isAccountView) {
            return redirect()->route('account.invoice.show', $invoice);
        }

        if ($token === null) {
            return redirect()->route('invoice.public.pay.show', $invoice);
        }

        return redirect()->route('invoice.magic', ['token' => $token]);
    }

    private function abortIfInvoiceNotAccessibleForRequest(Request $request, Invoice $invoice): void
    {
        $authUser = $request->user();
        $isAdmin = (bool) ($authUser?->isAdmin() ?? false);
        $isAssignedUser = $authUser
            && (string) ($invoice->user_id ?? '') !== ''
            && (string) $invoice->user_id === (string) $authUser->id;
        $hasValidToken = $this->hasValidMagicTokenForInvoice($request, $invoice);

        if (! ($isAdmin || $isAssignedUser || $hasValidToken)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function hasValidMagicTokenForInvoice(Request $request, Invoice $invoice): bool
    {
        $token = $this->resolveInvoiceMagicToken($request);
        if (! $token) {
            return false;
        }

        return (int) ($token->data['invoice_id'] ?? 0) === (int) $invoice->id;
    }

    private function resolveInvoiceMagicToken(Request $request): ?Token
    {
        $tokenString = trim((string) $request->input('token', $request->query('token', '')));
        if ($tokenString === '') {
            return null;
        }

        return Token::query()
            ->where('id', $tokenString)
            ->where('type', 'invoice-access')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function resolveInvoiceContactEmail(Invoice $invoice): string
    {
        $billingEmail = trim((string) ($invoice->billing_email ?? ''));
        if ($billingEmail !== '') {
            return $billingEmail;
        }

        return trim((string) ($invoice->user->email ?? ''));
    }

    private function resolveInvoiceEmailRecipients(Request $request, Invoice $invoice): array
    {
        $input = trim((string) $request->input('recipient_emails', ''));
        if ($input === '') {
            $input = $this->resolveInvoiceContactEmail($invoice);
        }

        if ($input === '') {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Add at least one recipient email address.',
            ]);
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'One or more email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        if (count($normalized) === 0) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Add at least one valid recipient email address.',
            ]);
        }

        return array_values($normalized);
    }

    private function defaultInvoiceEmailMessage(Invoice $invoice): string
    {
        $nameSource = trim((string) ($invoice->user?->getName() ?? $invoice->billing_name ?? ''));
        $name = trim((string) strtok($nameSource, ' '));
        if ($name === '') {
            $name = $nameSource !== '' ? $nameSource : 'there';
        }

        $invoiceNumber = trim((string) ($invoice->invoice_number ?? ''));
        $total = money((float) ($invoice->total_amount ?? 0));
        $due = $invoice->due_date?->format('M j, Y') ?? 'the due date on file';
        $outstanding = (float) $invoice->outstandingAmount();
        $isPaidInFull = $outstanding <= 0.0001;

        if ($invoice->isTicketInvoice()) {
            if ($isPaidInFull) {
                return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for your workshop ticket booking. The total cost was {$total}, and this invoice has now been paid in full.\n\nPlease don't hesitate to reach out if you have any questions.";
            }

            return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for your workshop ticket booking. The total cost is {$total} and is due on {$due}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
        }

        if ($isPaidInFull) {
            return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. The total cost was {$total}, and this invoice has now been paid in full.\n\nPlease don't hesitate to reach out if you have any questions.";
        }

        return "Hi {$name},\n\nAttached is invoice **{$invoiceNumber}** for the workshop program. The total cost is {$total} and is due on {$due}.\n\nPlease don't hesitate to reach out if you have any questions.\n\n{{pay}}";
    }

    /**
     * @param  array<int, string>  $recipients
     */
    private function appendInvoiceEmailPrivateNote(Invoice $invoice, array $recipients, ?string $initiatedByName): void
    {
        $existingNotes = trim((string) ($invoice->notes ?? ''));
        $recipientsList = implode(', ', array_values(array_filter(array_map('trim', $recipients), fn (string $email): bool => $email !== '')));
        if ($recipientsList === '') {
            return;
        }

        $senderLabel = \App\Support\EmailSignatureFormatter::resolve($initiatedByName);
        $timestamp = now()->format('Y-m-d g:i a');
        $line = $timestamp.' - Invoice emailed to '.$recipientsList.' by '.$senderLabel;

        $invoice->notes = $existingNotes !== '' ? $existingNotes."\n".$line : $line;
        $invoice->save();
    }

    private function resolveInvoiceEmailCcRecipients(Request $request): array
    {
        $input = trim((string) $request->input('cc_emails', ''));
        if ($input === '') {
            return [];
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'cc_emails' => 'One or more CC email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        return array_values($normalized);
    }

    private function resolveInvoicePaymentLinkRecipients(Request $request, Invoice $invoice): array
    {
        $input = trim((string) $request->input('payment_link_recipient_emails', ''));
        if ($input === '') {
            $input = $this->resolveInvoiceContactEmail($invoice);
        }

        if ($input === '') {
            throw ValidationException::withMessages([
                'payment_link_recipient_emails' => 'Add at least one recipient email address.',
            ]);
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'payment_link_recipient_emails' => 'One or more email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        if (count($normalized) === 0) {
            throw ValidationException::withMessages([
                'payment_link_recipient_emails' => 'Add at least one valid recipient email address.',
            ]);
        }

        return array_values($normalized);
    }

    private function resolveInvoicePaymentLinkCcRecipients(Request $request): array
    {
        $input = trim((string) $request->input('payment_link_cc_emails', ''));
        if ($input === '') {
            return [];
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'payment_link_cc_emails' => 'One or more CC email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        return array_values($normalized);
    }

    private function paymentLinkedToInvoiceForReceipt(Invoice $invoice, Payment $payment): bool
    {
        $eligibleIds = $this->receiptPaymentIdsForInvoice($invoice);

        return in_array((int) $payment->id, $eligibleIds, true);
    }

    private function receiptPaymentIdsForInvoice(Invoice $invoice): array
    {
        $allocatedPaymentIds = InvoicePaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->pluck('payment_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($allocatedPaymentIds === []) {
            return [];
        }

        $refundIds = Payment::query()
            ->whereIn('refund_of_payment_id', $allocatedPaymentIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return collect($allocatedPaymentIds)
            ->merge($refundIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function safePublicReturnUrl(Request $request, string $fallback): string
    {
        $returnTo = trim((string) $request->input('return_to', ''));
        if ($returnTo === '' || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($returnTo, $appUrl.'/')) {
            return substr($returnTo, strlen($appUrl)) ?: $fallback;
        }

        return $fallback;
    }

    private function sendPaymentReceiptEmail(Invoice $invoice, Payment $customerPayment): void
    {
        $invoice->loadMissing('user', 'storeOrders');
        $recipient = $this->resolveInvoiceContactEmail($invoice);
        if ($recipient === '') {
            return;
        }

        $linkedStoreOrder = $invoice->storeOrders
            ->sortByDesc(fn ($order) => optional($order->created_at)->timestamp ?? (int) $order->id)
            ->first();
        if ($linkedStoreOrder instanceof StoreOrder) {
            $this->storeOrders->sendOrderPaidEmailToCustomer($linkedStoreOrder);

            return;
        }

        $receiptNumber = (string) $customerPayment->id;
        $pdfBinary = $this->buildPaymentReceiptPdf($invoice, $customerPayment)->output();
        $invoicePdfBinary = $this->buildInvoicePdf($invoice)->output();

        dispatch(new SendEmail($recipient, new PaymentReceiptPdf(
            recipientName: $invoice->user?->getName() ?? (string) ($invoice->billing_name ?: $recipient),
            invoiceNumber: (string) $invoice->invoice_number,
            receiptNumber: $receiptNumber,
            amount: money(abs((float) $customerPayment->total_amount)),
            paidOn: ($customerPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a')),
            paymentMethod: Payment::paymentMethodLabel((string) ($customerPayment->payment_method ?? Payment::PAYMENT_METHOD_CREDIT_CARD)),
            receiptUrl: (string) ($customerPayment->square_receipt_url ?? ''),
            isRefund: $customerPayment->isRefund(),
            pdfContent: $pdfBinary,
            pdfFilename: $this->getPaymentReceiptPdfFilename($customerPayment),
            invoiceSummary: $this->paymentReceiptInvoiceSummary($invoice),
            statusSummary: $this->paymentReceiptStatusSummary($invoice),
            outstandingBeforeSummary: $this->paymentReceiptOutstandingBeforeSummary($invoice, $customerPayment),
            appliedAmountSummary: $this->paymentReceiptAppliedAmountSummary($invoice, $customerPayment),
            creditSummary: $this->paymentReceiptCreditSummary($customerPayment),
            creditAppliedAmount: null,
            creditReferenceSummary: null,
            orderTotalAmount: null,
            invoicePdfContent: $invoicePdfBinary,
            invoicePdfFilename: $this->getInvoicePdfFilename($invoice),
            hasInvoiceAttachment: true,
        )))->onQueue('mail');

        $creditAppliedAmount = $this->paymentReceiptCreditAppliedAmount($invoice, $customerPayment);
        if ($creditAppliedAmount > 0.0001) {
            dispatch(new SendEmail($recipient, new PaymentReceiptPdf(
                recipientName: $invoice->user?->getName() ?? (string) ($invoice->billing_name ?: $recipient),
                invoiceNumber: (string) $invoice->invoice_number,
                receiptNumber: $this->nextCreditReceiptNumber($customerPayment),
                amount: money($creditAppliedAmount),
                paidOn: ($customerPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a')),
                paymentMethod: 'Account Credit',
                receiptUrl: null,
                isRefund: false,
                pdfContent: $this->buildInvoiceCreditAppliedReceiptPdf($invoice, $customerPayment, $creditAppliedAmount)->output(),
                pdfFilename: 'credit-applied-'.($invoice->invoice_number).'-'.$receiptNumber.'.pdf',
                invoiceSummary: $this->paymentReceiptInvoiceSummary($invoice),
                statusSummary: $this->paymentReceiptStatusSummary($invoice),
                outstandingBeforeSummary: $this->paymentReceiptOutstandingBeforeSummary($invoice, $customerPayment),
                appliedAmountSummary: money($creditAppliedAmount),
                creditSummary: null,
                creditAppliedAmount: null,
                creditReferenceSummary: null,
                orderTotalAmount: null,
            )))->onQueue('mail');
        }
    }

    private function paymentReceiptInvoiceSummary(Invoice $invoice): ?string
    {
        $invoice->loadMissing('lines');

        $lineSummaries = $invoice->lines
            ->map(function (InvoiceLine $line): ?array {
                $description = preg_replace('/\s+/', ' ', trim((string) ($line->description ?? '')));
                if ($description === '') {
                    return null;
                }

                $quantity = round(max(0.01, (float) ($line->quantity ?? 1)), 2);

                return [
                    'description' => $description,
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->groupBy('description')
            ->map(function ($rows, string $description): string {
                $quantity = round((float) collect($rows)->sum('quantity'), 2);
                if (abs($quantity - 1.0) <= 0.0001) {
                    return $description;
                }

                $quantityLabel = floor($quantity) === $quantity
                    ? (string) ((int) $quantity)
                    : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

                return $quantityLabel.' x '.$description;
            })
            ->values();

        if ($lineSummaries->isEmpty()) {
            $ticketCount = Ticket::query()->where('invoice_id', $invoice->id)->count();
            if ($ticketCount > 0) {
                return $ticketCount.' workshop ticket'.($ticketCount === 1 ? '' : 's');
            }

            return null;
        }

        $summary = $lineSummaries->take(3)->implode(', ');
        if ($lineSummaries->count() > 3) {
            $summary .= ', +'.($lineSummaries->count() - 3).' more';
        }

        return $summary;
    }

    private function paymentReceiptStatusSummary(Invoice $invoice): string
    {
        $outstanding = $invoice->outstandingAmount();

        if ($outstanding <= 0.0001) {
            return 'This invoice is now paid in full.';
        }

        return 'There is '.money($outstanding).' now remaining on this invoice.';
    }

    private function paymentReceiptOutstandingBeforeSummary(Invoice $invoice, Payment $customerPayment): string
    {
        return money($invoice->outstandingAmount((int) $customerPayment->id));
    }

    private function paymentReceiptAppliedAmountSummary(Invoice $invoice, Payment $customerPayment): string
    {
        $appliedAmount = round((float) $customerPayment->allocations()
            ->where('invoice_id', $invoice->id)
            ->sum('allocated_amount'), 2);

        return money($appliedAmount);
    }

    private function paymentReceiptCreditAppliedAmount(Invoice $invoice, Payment $payment): float
    {
        $invoice->loadMissing('allocations.customerPayment');

        return round((float) $invoice->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && (int) ($allocation->payment_id ?? 0) !== (int) $payment->id)
            ->sum('allocated_amount'), 2);
    }

    private function paymentReceiptCreditReferenceSummary(Invoice $invoice, Payment $payment): string
    {
        $invoice->loadMissing('allocations.customerPayment');

        return $invoice->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && (int) ($allocation->payment_id ?? 0) !== (int) $payment->id)
            ->map(function ($allocation): string {
                $creditPayment = data_get($allocation, 'customerPayment');
                if (! $creditPayment instanceof Payment) {
                    return '';
                }

                $paymentReference = trim((string) ($creditPayment->reference ?? ''));

                return 'Payment #'.(int) $creditPayment->id.($paymentReference !== '' ? ' ('.$paymentReference.')' : '');
            })
            ->filter()
            ->values()
            ->implode(', ');
    }

    private function paymentReceiptMethodLabel(Invoice $invoice, Payment $payment, float $creditAppliedAmount): string
    {
        $paymentMethodLabel = Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_OTHER));
        if ($payment->isRefund() || $creditAppliedAmount <= 0.0001) {
            return $paymentMethodLabel;
        }

        if ($paymentMethodLabel === Payment::paymentMethodLabel(Payment::PAYMENT_METHOD_CREDIT)) {
            return $paymentMethodLabel;
        }

        $invoice->loadMissing('allocations.customerPayment');
        $hasNonCurrentAllocation = $invoice->allocations->contains(function ($allocation) use ($payment): bool {
            return abs((float) $allocation->allocated_amount) > 0.0001
                && (int) ($allocation->payment_id ?? 0) !== (int) $payment->id;
        });

        if (! $hasNonCurrentAllocation) {
            return $paymentMethodLabel;
        }

        return 'Account Credit + '.$paymentMethodLabel;
    }

    private function paymentReceiptCreditSummary(Payment $customerPayment): ?string
    {
        $creditAmount = $this->paymentUnallocatedAmount($customerPayment);
        if ($creditAmount <= 0.0001) {
            return null;
        }

        return 'You now have '.money($creditAmount).' sitting in credit on your account. Please contact us to discuss your options.';
    }

    private function paymentUnallocatedAmount(Payment $payment): float
    {
        $allocated = (float) $payment->allocations()->sum('allocated_amount');
        $refunded = (float) $payment->refunds()->sum('total_amount');
        $unallocatedBeforeRefund = max(0, round((float) $payment->total_amount - $allocated, 2));

        return max(0, round($unallocatedBeforeRefund - $refunded, 2));
    }

    private function buildPaymentReceiptPdf(Invoice $invoice, Payment $customerPayment): PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Payment receipt PDF generation requires barryvdh/laravel-dompdf.');
        }

        $amountRaw = (float) $customerPayment->total_amount;
        $isRefund = $customerPayment->isRefund();
        $creditAppliedAmount = $this->paymentReceiptCreditAppliedAmount($invoice, $customerPayment);
        $creditReferenceSummary = $this->paymentReceiptCreditReferenceSummary($invoice, $customerPayment);
        $paymentMethodLabel = $this->paymentReceiptMethodLabel($invoice, $customerPayment, $creditAppliedAmount);

        $gatewayProcessedAtRaw = trim((string) ($customerPayment->square_gateway_updated_at ?? $customerPayment->square_gateway_created_at ?? ''));
        $gatewayProcessedAtLabel = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAtLabel = Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (Throwable) {
                $gatewayProcessedAtLabel = '';
            }
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => $isRefund,
            'receiptTitle' => $isRefund ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $isRefund ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $customerPayment->id,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => $amountRaw,
            'gstAmount' => abs((float) $customerPayment->gst_amount),
            'paymentMethod' => $paymentMethodLabel,
            'paidOn' => $customerPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($customerPayment->reference ?? ''),
            'gatewayProvider' => (string) ($customerPayment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($customerPayment->gateway_status ?? ''),
            'transactionId' => trim((string) ($customerPayment->square_payment_id ?: $customerPayment->gateway_reference_id)),
            'squareOrderId' => (string) ($customerPayment->square_order_id ?? ''),
            'cardBrand' => (string) ($customerPayment->square_card_brand ?? ''),
            'cardLast4' => (string) ($customerPayment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($customerPayment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $gatewayProcessedAtLabel,
            'footerMessage' => $isRefund ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
            'creditAppliedAmount' => $creditAppliedAmount,
            'creditReferenceSummary' => $creditReferenceSummary,
            'orderTotalAmount' => round((float) $invoice->total_amount, 2),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function buildInvoiceCreditAppliedReceiptPdf(Invoice $invoice, Payment $customerPayment, float $creditAppliedAmount): PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Payment receipt PDF generation requires barryvdh/laravel-dompdf.');
        }

        $gatewayProcessedAtRaw = trim((string) ($customerPayment->square_gateway_updated_at ?? $customerPayment->square_gateway_created_at ?? ''));
        $gatewayProcessedAtLabel = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAtLabel = Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (Throwable) {
                $gatewayProcessedAtLabel = '';
            }
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => false,
            'receiptTitle' => 'Credit Receipt',
            'amountLabel' => 'Amount Applied',
            'receiptNumber' => $this->nextCreditReceiptNumber($customerPayment),
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => $creditAppliedAmount,
            'gstAmount' => 0.0,
            'paymentMethod' => 'Account Credit',
            'paidOn' => $customerPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => 'Account credit applied to invoice '.(string) $invoice->invoice_number,
            'gatewayProvider' => (string) ($customerPayment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($customerPayment->gateway_status ?? ''),
            'transactionId' => trim((string) ($customerPayment->square_payment_id ?: $customerPayment->gateway_reference_id)),
            'squareOrderId' => (string) ($customerPayment->square_order_id ?? ''),
            'cardBrand' => '',
            'cardLast4' => '',
            'squareReceiptUrl' => '',
            'gatewayProcessedAt' => $gatewayProcessedAtLabel,
            'footerMessage' => 'This receipt confirms account credit applied to the invoice.',
            'creditAppliedAmount' => null,
            'creditReferenceSummary' => null,
            'orderTotalAmount' => null,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function nextCreditReceiptNumber(Payment $customerPayment): string
    {
        return (string) max(1, (int) $customerPayment->id + 1);
    }

    private function buildTaxAdjustmentPdf(Invoice $invoice, TaxAdjustment $adjustment): PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Tax adjustment PDF generation requires barryvdh/laravel-dompdf.');
        }

        $adjustment->loadMissing('lines');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.tax-adjustment', [
            'invoice' => $invoice,
            'adjustment' => $adjustment,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function getPaymentReceiptPdfFilename(Payment $customerPayment): string
    {
        return ($customerPayment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').($customerPayment->id).'.pdf';
    }

    private function invoiceAllocatedTotal(Invoice $invoice): float
    {
        return $invoice->settledAmount();
    }

    private function syncInvoicePaidState(Invoice $invoice, ?Payment $customerPayment = null): void
    {
        $becamePaid = $invoice->syncPaidState();

        if ($becamePaid && $this->invoiceHasTicketContent($invoice)) {
                Ticket::query()
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('status', [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER])
                    ->update(['status' => Ticket::STATUS_DONE]);
        }
    }

    private function invoiceHasTicketContent(Invoice $invoice): bool
    {
        if ($invoice->lines()->where('kind', 'ticket')->exists()) {
            return true;
        }

        return Ticket::query()->where('invoice_id', $invoice->id)->exists();
    }

    private function validateRequest(Request $request, ?Invoice $invoice = null): array
    {
        $isLocked = $invoice instanceof Invoice && ! $invoice->canEditContents();
        if ($isLocked) {
            return $request->validate([
                'purchase_order_number' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string'],
                'quote_id' => [
                    'nullable',
                    'integer',
                    'exists:quotes,id',
                ],
                'private_file_ids' => ['nullable', 'string'],
            ]);
        }

        return $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('invoices')->ignore($invoice?->id)],
            'user_id' => ['nullable', 'exists:users,id'],
            'issue_now' => ['nullable', 'boolean'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'purchase_order_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'line_items_json' => ['nullable', 'string'],
            'quote_id' => [
                'nullable',
                'integer',
                'exists:quotes,id',
            ],
            'private_file_ids' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function parsePrivateFileIds(mixed $value): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($id) => is_numeric(trim($id)) ? (int) trim($id) : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function validateQuoteUserMatch(mixed $quoteId, mixed $invoiceUserId): void
    {
        $normalizedQuoteId = is_numeric($quoteId) ? (int) $quoteId : 0;
        if ($normalizedQuoteId <= 0) {
            return;
        }

        $normalizedUserId = trim((string) ($invoiceUserId ?? ''));
        if ($normalizedUserId === '') {
            throw ValidationException::withMessages([
                'quote_id' => 'Select a linked user before linking a quote.',
            ]);
        }

        $quote = Quote::query()->find($normalizedQuoteId);
        if (! $quote instanceof Quote) {
            throw ValidationException::withMessages([
                'quote_id' => 'Selected quote could not be found.',
            ]);
        }

        if ((string) ($quote->user_id ?? '') !== $normalizedUserId) {
            throw ValidationException::withMessages([
                'quote_id' => 'Linked quote must belong to the same user as this invoice.',
            ]);
        }
    }

    private function extractLineItems(Request $request): array
    {
        $lineItemsJson = $request->input('line_items_json', '[]');

        if (! is_string($lineItemsJson) || trim($lineItemsJson) === '') {
            return [];
        }

        $decoded = json_decode($lineItemsJson, true);
        if (! is_array($decoded)) {
            return [];
        }

        $lineItems = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeLineItem($item);
            if ($normalized === null) {
                continue;
            }

            $lineItems[] = $normalized;
        }

        return $lineItems;
    }

    private function squareDateTime($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function calculateSubtotal(array $lineItems): float
    {
        $subtotal = 0;

        foreach ($lineItems as $lineItem) {
            $subtotal += (float) ($lineItem['line_total_ex_tax'] ?? 0);
        }

        return round($subtotal, 2);
    }

    private function calculateGst(array $lineItems): float
    {
        $gst = 0;

        foreach ($lineItems as $lineItem) {
            $gst += (float) ($lineItem['tax_amount'] ?? 0);
        }

        return round($gst, 2);
    }

    private function paginateLineItemsForPdf(Invoice $invoice): array
    {
        $items = $this->invoiceLineItemsForPayload($invoice);

        if (count($items) === 0) {
            return [[]];
        }

        $weights = array_map(fn (array $item) => $this->lineItemPdfWeight($item), $items);
        $firstLastCap = 9.0;
        $firstCap = 16.0;
        $middleCap = 26.0;
        $lastCap = 9.0;

        $totalWeight = array_sum($weights);
        if ($totalWeight <= $firstLastCap) {
            return [$items];
        }

        $pages = [];
        $index = 0;

        [$firstPageItems, $index] = $this->packPage($items, $weights, $index, $firstCap);
        $pages[] = $firstPageItems;

        while ($this->remainingWeight($weights, $index) > $lastCap) {
            [$middlePageItems, $index] = $this->packPage($items, $weights, $index, $middleCap);
            $pages[] = $middlePageItems;
        }

        $pages[] = array_slice($items, $index);

        return $pages;
    }

    private function packPage(array $items, array $weights, int $startIndex, float $capacity): array
    {
        $currentWeight = 0.0;
        $currentItems = [];
        $index = $startIndex;
        $count = count($items);

        while ($index < $count) {
            $nextWeight = $weights[$index] ?? 1.0;
            if (count($currentItems) > 0 && ($currentWeight + $nextWeight) > $capacity) {
                break;
            }

            $currentItems[] = $items[$index];
            $currentWeight += $nextWeight;
            $index++;
        }

        if (count($currentItems) === 0 && $startIndex < $count) {
            $currentItems[] = $items[$startIndex];
            $index = $startIndex + 1;
        }

        return [$currentItems, $index];
    }

    private function remainingWeight(array $weights, int $startIndex): float
    {
        $remaining = 0.0;
        $count = count($weights);

        for ($i = $startIndex; $i < $count; $i++) {
            $remaining += (float) ($weights[$i] ?? 0);
        }

        return $remaining;
    }

    private function lineItemPdfWeight(array $item): float
    {
        $notes = trim((string) ($item['notes'] ?? ''));
        if ($notes === '') {
            return 1.0;
        }

        $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        $lineCount = max(count($noteLines), 1);

        return 1.0 + min($lineCount * 0.35, 4.0);
    }

    private function buildInvoicePdf(Invoice $invoice, bool $includeAdjustments = false): PDF
    {
        $invoice->loadMissing('user', 'lines');
        if ($includeAdjustments) {
            $invoice->loadMissing('taxAdjustments.lines');
        }
        $this->appendReissueNotesToInvoiceLines($invoice);

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Invoice PDF generation requires barryvdh/laravel-dompdf.');
        }

        $itemPages = $this->paginateLineItemsForPdf($invoice);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'itemPages' => $itemPages,
            'adjustments' => $includeAdjustments
                ? $invoice->taxAdjustments()->orderBy('issue_date')->orderBy('id')->get()
                : collect(),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function appendReissueNotesToInvoiceLines(Invoice $invoice): void
    {
        if (! $invoice->relationLoaded('lines')) {
            $invoice->load('lines');
        }

        $lineIds = $invoice->lines
            ->where('kind', 'ticket')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($lineIds === []) {
            return;
        }

        $tickets = Ticket::query()
            ->where('invoice_id', $invoice->id)
            ->where(function ($query) use ($lineIds): void {
                $query->whereIn('invoice_line_id', $lineIds)
                    ->orWhereIn('id', $lineIds);
            })
            ->get(['id', 'invoice_line_id', 'reference_code', 'status', 'created_at']);

        if ($tickets->isEmpty()) {
            return;
        }

        foreach ($invoice->lines as $line) {
            if ((string) $line->kind !== 'ticket') {
                continue;
            }

            $lineTicketId = (int) data_get($line->details_json, 'ticket_id');
            $lineReference = trim((string) data_get($line->details_json, 'ticket_reference'));
            $linkedTickets = $tickets->where('invoice_line_id', (int) $line->id);
            if ($linkedTickets->isEmpty() && $lineTicketId > 0) {
                $linkedTickets = $tickets->where('id', $lineTicketId);
            }
            if ($linkedTickets->isEmpty()) {
                continue;
            }

            $activeTicket = $linkedTickets
                ->filter(fn (Ticket $ticket) => in_array((int) $ticket->status, Ticket::activePurchasedStatuses(), true))
                ->sortByDesc('created_at')
                ->first();

            if (! $activeTicket instanceof Ticket) {
                continue;
            }

            $activeReference = trim((string) ($activeTicket->reference_code ?: $activeTicket->id));
            if ($activeReference === '') {
                continue;
            }

            $fromReference = $lineReference !== ''
                ? $lineReference
                : trim((string) (optional($linkedTickets->sortBy('created_at')->first())->reference_code ?? ''));

            if ($fromReference !== '' && $fromReference === $activeReference) {
                continue;
            }

            $existingNotes = trim((string) ($line->notes ?? ''));
            if (str_contains($existingNotes, 'Ticket reissued as:')) {
                continue;
            }

            $suffix = 'Ticket reissued as: '.$activeReference;

            $line->notes = $existingNotes !== '' ? $existingNotes."\n".$suffix : $suffix;
        }
    }

    private function replaceInvoiceLines(Invoice $invoice, array $lineItems): void
    {
        $invoice->lines()->delete();

        foreach (array_values($lineItems) as $index => $lineItem) {
            $invoice->lines()->create([
                'line_number' => $index + 1,
                'kind' => (string) ($lineItem['kind'] ?? 'generic'),
                'description' => (string) ($lineItem['description'] ?? ''),
                'notes' => (string) ($lineItem['notes'] ?? ''),
                'details_json' => $lineItem['details_json'] ?? [],
                'quantity' => (float) ($lineItem['quantity'] ?? 0),
                'unit_price_ex_tax' => (float) ($lineItem['unit_price_ex_tax'] ?? 0),
                'tax_rate' => (float) ($lineItem['tax_rate'] ?? 0),
                'line_total_ex_tax' => (float) ($lineItem['line_total_ex_tax'] ?? 0),
                'tax_amount' => (float) ($lineItem['tax_amount'] ?? 0),
                'line_total_inc_tax' => (float) ($lineItem['line_total_inc_tax'] ?? 0),
                'source_type' => $lineItem['source_type'] ?? null,
                'source_id' => $lineItem['source_id'] ?? null,
                'original_invoice_line_id' => $lineItem['original_invoice_line_id'] ?? null,
            ]);
        }
    }

    private function normalizeLineItem(array $item): ?array
    {
        $description = trim((string) ($item['description'] ?? ''));
        $notes = trim((string) ($item['notes'] ?? ''));
        $quantity = (float) ($item['quantity'] ?? $item['qty'] ?? 0);
        $unitPriceExTax = (float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0);
        $taxRate = array_key_exists('tax_rate', $item)
            ? (float) $item['tax_rate']
            : (filter_var($item['gst_applicable'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 0.10 : 0.00);

        if ($description === '' && $notes === '' && abs($quantity) < 0.0001 && abs($unitPriceExTax) < 0.0001) {
            return null;
        }

        $lineTotalExTax = round($quantity * $unitPriceExTax, 2);
        $taxAmount = round($lineTotalExTax * $taxRate, 2);

        return [
            'kind' => trim((string) ($item['kind'] ?? 'generic')) ?: 'generic',
            'description' => $description,
            'notes' => $notes,
            'details_json' => is_array($item['details_json'] ?? null) ? $item['details_json'] : [],
            'quantity' => round($quantity, 2),
            'unit_price_ex_tax' => round($unitPriceExTax, 2),
            'tax_rate' => round($taxRate, 4),
            'line_total_ex_tax' => $lineTotalExTax,
            'tax_amount' => $taxAmount,
            'line_total_inc_tax' => round($lineTotalExTax + $taxAmount, 2),
            'source_type' => isset($item['source_type']) ? (string) $item['source_type'] : null,
            'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : null,
            'original_invoice_line_id' => isset($item['original_invoice_line_id']) ? (int) $item['original_invoice_line_id'] : null,
        ];
    }

    private function invoiceLineItemsForPayload(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');

        return $invoice->lines->map(function (InvoiceLine $line): array {
            return [
                'id' => $line->id,
                'kind' => (string) $line->kind,
                'description' => (string) $line->description,
                'notes' => (string) ($line->notes ?? ''),
                'details_json' => is_array($line->details_json) ? $line->details_json : [],
                'quantity' => (float) $line->quantity,
                'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
                'tax_rate' => (float) $line->tax_rate,
                'line_total_ex_tax' => (float) $line->line_total_ex_tax,
                'tax_amount' => (float) $line->tax_amount,
                'line_total_inc_tax' => (float) $line->line_total_inc_tax,
                'source_type' => $line->source_type,
                'source_id' => $line->source_id,
                'original_invoice_line_id' => $line->original_invoice_line_id,
                'gst_applicable' => ((float) $line->tax_rate) > 0.0001,
                'unit_price' => (float) $line->unit_price_ex_tax,
                'line_total' => (float) $line->line_total_ex_tax,
            ];
        })->all();
    }

    private function getInvoicePdfFilename(Invoice $invoice): string
    {
        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $invoice->invoice_number);
        if (! is_string($safeInvoiceNumber) || $safeInvoiceNumber === '') {
            $safeInvoiceNumber = (string) $invoice->id;
        }

        return 'invoice-'.$safeInvoiceNumber.'.pdf';
    }

    private function abortIfInvoiceNotAccessible(Invoice $invoice): void
    {
        $user = auth()->user();
        abort_if(! $user, Response::HTTP_FORBIDDEN);

        if (! $user->isAdmin() && $invoice->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function getMailInitiatorIdentity(): array
    {
        $user = auth()->user();
        $email = trim((string) ($user->email ?? ''));
        $firstName = trim((string) ($user->firstname ?? ''));
        $surname = trim((string) ($user->surname ?? ''));
        $name = trim($firstName.' '.$surname);
        if ($name === '') {
            $name = trim((string) ($user?->getName() ?? ''));
        }

        return [
            $email !== '' ? $email : null,
            $name !== '' ? $name : null,
        ];
    }
}
