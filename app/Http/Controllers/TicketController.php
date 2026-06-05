<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\TicketManualRefundNotice;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketCancelledNotice;
use App\Mail\TicketMagicLink;
use App\Mail\TicketNoTickets;
use App\Mail\TicketOrderConfirmation;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\SquareRefundOperation;
use App\Models\TaxAdjustment;
use App\Models\Ticket;
use App\Models\Token;
use App\Models\User;
use App\Providers\QRCodeProvider;
use App\Services\DocumentNumberService;
use App\Services\SquareApiService;
use App\Services\TicketReissueService;
use App\Services\WorkshopTicketService;
use App\Support\AltchaTrust;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use GrantHolle\Altcha\Rules\ValidAltcha;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TicketController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly TicketReissueService $ticketReissueService,
        private readonly WorkshopTicketService $workshopTicketService
    ) {}

    public function accountIndex(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = auth()->user();

        $query = Ticket::query()
            ->with(['workshop.location', 'user', 'invoice'])
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('user_id', $user?->id);
                $email = strtolower(trim((string) ($user->email ?? '')));
                if ($email !== '') {
                    $builder->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->where(function ($builder) {
                $builder->where('status', '!=', Ticket::STATUS_HOLD)
                    ->orWhere('created_at', '>=', now()->subMinutes(10));
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('reference_code', 'like', '%'.$search.'%')
                    ->orWhereHas('workshop', fn ($workshopQuery) => $workshopQuery->where('title', 'like', '%'.$search.'%'));
            });
        }

        $ticketOutstandingSummary = $this->ticketOutstandingSummary(clone $query);

        $tickets = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        return view('account.tickets', [
            'tickets' => $tickets,
            'ticketOutstandingByInvoiceId' => $ticketOutstandingSummary['byInvoiceId'],
            'ticketOutstandingInvoiceCount' => $ticketOutstandingSummary['invoiceCount'],
            'ticketOutstandingTotal' => $ticketOutstandingSummary['total'],
            'pageTitle' => 'My Tickets',
        ]);
    }

    public function adminIndex(Request $request): View
    {
        $showInactive = $request->boolean('show_inactive');
        $groupByWorkshop = $request->boolean('group_by_workshop');

        $query = Ticket::query()
            ->with(['workshop.location', 'invoice', 'user'])
            ->where(function (Builder $builder): void {
                $builder->where('status', '!=', Ticket::STATUS_HOLD)
                    ->orWhere('created_at', '>=', now()->subMinutes(10));
            });

        if (! $showInactive) {
            $query->whereNotIn('status', [
                Ticket::STATUS_CANCELLED,
                Ticket::STATUS_REISSUED,
            ]);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            if ($search !== '') {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('reference_code', 'like', '%'.$search.'%')
                        ->orWhereHas('workshop', fn (Builder $workshopQuery) => $workshopQuery->where('title', 'like', '%'.$search.'%'))
                        ->orWhereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%'));
                });
            }
        }

        if ($groupByWorkshop) {
            $query
                ->orderByDesc(
                    \App\Models\Workshop::query()
                        ->select('starts_at')
                        ->whereColumn('workshops.id', 'tickets.workshop_id')
                        ->limit(1)
                )
                ->orderBy('workshop_id')
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $tickets = $query->paginate(30)->onEachSide(1);

        return view('admin.ticket.index', [
            'tickets' => $tickets,
            'showInactive' => $showInactive,
            'groupByWorkshop' => $groupByWorkshop,
        ]);
    }

    public function showRequest(): View
    {
        return view('tickets.request');
    }

    public function sendMagicLink(Request $request)
    {
        $rules = [
            'email' => ['required', 'email'],
        ];
        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha()];
        }

        $validated = $request->validate($rules, [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);
        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        $email = strtolower(trim((string) $validated['email']));
        $ticketQuery = $this->ticketLookupQueryForEmail($email);
        $ticketCount = (clone $ticketQuery)->count();

        if ($ticketCount > 0) {
            /** @var Ticket|null $firstTicket */
            $firstTicket = (clone $ticketQuery)->orderBy('id')->first();
            if ($firstTicket) {
                $tokenUserId = $this->resolveOrCreateTokenUserIdForTicketEmail($email, $firstTicket);
                $token = Token::create([
                    'user_id' => $tokenUserId,
                    'type' => 'tickets-access',
                    'data' => ['email' => $email],
                    'expires_at' => now()->addMinutes(30),
                ]);

                dispatch(new SendEmail($email, new TicketMagicLink($token->id, $email)))->onQueue('mail');
            }
        } else {
            dispatch(new SendEmail($email, new TicketNoTickets($email)))->onQueue('mail');
        }

        session()->flash('message', 'If tickets were found, a secure link has been sent to your email.');
        session()->flash('message-title', 'Check your inbox');
        session()->flash('message-type', 'success');

        return redirect()->route('index');
    }

    public function showByMagicToken(Request $request): View|RedirectResponse
    {
        $tokenString = trim((string) $request->query('token', ''));
        if ($tokenString === '') {
            abort(404);
        }

        $token = Token::query()
            ->where('id', $tokenString)
            ->where('type', 'tickets-access')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            session()->flash('message', 'That ticket link has expired or is invalid.');
            session()->flash('message-title', 'Link invalid');
            session()->flash('message-type', 'danger');

            return redirect()->route('tickets.request');
        }

        $email = strtolower(trim((string) ($token->data['email'] ?? '')));
        if ($email === '') {
            abort(404);
        }

        $tokenPurchaserUserId = $this->purchaserUserIdForToken($token, $email);

        $ticketQuery = Ticket::query()
            ->with(['workshop.location', 'user', 'invoice'])
            ->where(function (Builder $builder) use ($email, $tokenPurchaserUserId): void {
                $builder->whereRaw('LOWER(email) = ?', [$email]);
                if ($tokenPurchaserUserId !== null) {
                    $builder->orWhere('user_id', $tokenPurchaserUserId);
                }
            })
            ->where(function ($builder) {
                $builder->where('status', '!=', Ticket::STATUS_HOLD)
                    ->orWhere('created_at', '>=', now()->subMinutes(10));
            });

        $ticketOutstandingSummary = $this->ticketOutstandingSummary(clone $ticketQuery);
        $tickets = $ticketQuery
            ->orderByDesc('created_at')
            ->get();

        return view('tickets.list', [
            'tickets' => $tickets,
            'email' => $email,
            'accessToken' => $tokenString,
            'tokenPurchaserUserId' => $tokenPurchaserUserId,
            'ticketOutstandingByInvoiceId' => $ticketOutstandingSummary['byInvoiceId'],
            'ticketOutstandingInvoiceCount' => $ticketOutstandingSummary['invoiceCount'],
            'ticketOutstandingTotal' => $ticketOutstandingSummary['total'],
            'pageTitle' => 'My Tickets',
        ]);
    }

    public function accountPdf(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $this->abortIfTicketNotAccessible($request, $ticket);

        return $this->buildTicketPdfResponse($ticket);
    }

    public function pdf(Request $request, Ticket $ticket)
    {
        $this->abortIfTicketNotAccessible($request, $ticket);

        return $this->buildTicketPdfResponse($ticket);
    }

    public function accountInvoicePdf(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return $this->invoicePdf($request, $ticket);
    }

    public function accountCancel(Request $request, Ticket $ticket, SquareApiService $squareApi): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user, 403);

        $isAdmin = (bool) $user->isAdmin();
        $isOwner = (string) ($ticket->user_id ?? '') !== '' && (string) $ticket->user_id === (string) $user->id;
        $introLine = $this->resolveTicketCancellationIntroLine($request);

        abort_if(! ($isAdmin || $isOwner), 403);

        try {
            $summary = $this->cancelTicketWithFinancials(
                ticket: $ticket,
                squareApi: $squareApi,
                initiatedByAdmin: $isAdmin,
                reason: $introLine,
                processSquareRefunds: true
            );
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            if ($message === '') {
                $message = 'Unable to cancel ticket.';
            }
            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        } catch (RuntimeException $e) {
            session()->flash('message', $e->getMessage());
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $refunded = (int) ($summary['refunded_cents'] ?? 0);
        $message = 'Your ticket has been cancelled successfully.';
        if ((bool) ($summary['already_adjusted'] ?? false)) {
            $message .= ' A refund/credit had already been recorded for this ticket.';
        }
        if ($refunded > 0) {
            $message .= ' A refund of $'.number_format($refunded / 100, 2).' has been processed to your card.';
        }
        if ((bool) ($summary['manual_refund_required'] ?? false)) {
            $message .= ' We have recorded your refund request and will finalise it shortly.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Ticket cancelled');
        session()->flash('message-type', 'success');

        try {
            $freshTicket = $ticket->fresh(['invoice.user', 'invoice.taxAdjustments.lines', 'user', 'workshop.location']);
            $this->sendCancellationNoticeEmail($request, $freshTicket, $summary, $introLine);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->back();
    }

    public function cancel(Request $request, Ticket $ticket, SquareApiService $squareApi): RedirectResponse
    {
        $this->abortIfTicketNotAccessible($request, $ticket);

        $authUser = $request->user();
        $isAdmin = (bool) ($authUser?->isAdmin() ?? false);
        $isOwner = $authUser
            && (string) ($ticket->user_id ?? '') !== ''
            && (string) $ticket->user_id === (string) $authUser->id;
        $hasPurchaserToken = $this->hasValidPurchaserMagicTokenForTicket($request, $ticket);
        $introLine = $this->resolveTicketCancellationIntroLine($request);

        abort_if(! ($isAdmin || $isOwner || $hasPurchaserToken), 403);

        try {
            $summary = $this->cancelTicketWithFinancials(
                ticket: $ticket,
                squareApi: $squareApi,
                initiatedByAdmin: $isAdmin,
                reason: $introLine,
                processSquareRefunds: true
            );
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            if ($message === '') {
                $message = 'Unable to cancel ticket.';
            }
            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        } catch (RuntimeException $e) {
            session()->flash('message', $e->getMessage());
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $refunded = (int) ($summary['refunded_cents'] ?? 0);
        $message = 'Your ticket has been cancelled successfully.';
        if ((bool) ($summary['already_adjusted'] ?? false)) {
            $message .= ' A refund/credit had already been recorded for this ticket.';
        }
        if ($refunded > 0) {
            $message .= ' A refund of $'.number_format($refunded / 100, 2).' has been processed to your card.';
        }
        if ((bool) ($summary['manual_refund_required'] ?? false)) {
            $message .= ' We have recorded your refund request and will finalise it shortly.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Ticket cancelled');
        session()->flash('message-type', 'success');

        try {
            $freshTicket = $ticket->fresh(['invoice.user', 'invoice.taxAdjustments.lines', 'user', 'workshop.location']);
            $this->sendCancellationNoticeEmail($request, $freshTicket, $summary, $introLine);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->back();
    }

    public function adminCancel(Request $request, Ticket $ticket, SquareApiService $squareApi): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        $processSquareRefunds = filter_var($request->input('process_square_refund', false), FILTER_VALIDATE_BOOLEAN);
        $emailCustomer = $request->boolean('email_customer', true);
        $introLine = $this->resolveTicketCancellationIntroLine($request);

        try {
            $summary = $this->cancelTicketWithFinancials(
                ticket: $ticket,
                squareApi: $squareApi,
                initiatedByAdmin: true,
                reason: $introLine,
                processSquareRefunds: $processSquareRefunds
            );
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            if ($message === '') {
                $message = 'Unable to cancel ticket.';
            }
            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        } catch (RuntimeException $e) {
            session()->flash('message', $e->getMessage());
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $adjustmentNoteNumber = $summary['adjustment_note_number'] ?? null;
        $refunded = (int) ($summary['refunded_cents'] ?? 0);
        $message = 'Ticket cancelled successfully.';
        if ((bool) ($summary['already_adjusted'] ?? false)) {
            $message .= ' Existing refund/credit already recorded for this ticket.';
        }
        if (is_string($adjustmentNoteNumber) && $adjustmentNoteNumber !== '') {
            $message .= ' Tax adjustment note '.$adjustmentNoteNumber.' created.';
        }
        if ($refunded > 0) {
            $message .= ' Square refund issued: $'.number_format($refunded / 100, 2).'.';
        }
        if ((bool) ($summary['manual_refund_required'] ?? false)) {
            $message .= ' Manual refund required.';
        }
        if (! $processSquareRefunds && (bool) ($summary['has_square_payment'] ?? false)) {
            $message .= ' Square refund not processed yet; complete it from the payment record if needed.';
        }
        if (! $emailCustomer) {
            $message .= ' Customer email not sent.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Ticket cancelled');
        session()->flash('message-type', 'success');

        if ($emailCustomer) {
            try {
                $freshTicket = $ticket->fresh(['invoice.user', 'invoice.taxAdjustments.lines', 'user', 'workshop.location']);
                $this->sendCancellationNoticeEmail($request, $freshTicket, $summary, $introLine);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->back();
    }

    public function adminBulkCancel(Request $request, SquareApiService $squareApi): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        $validated = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer', 'min:1'],
            'process_square_refund' => ['nullable', 'boolean'],
            'email_customer' => ['nullable', 'boolean'],
        ]);

        $ticketIds = collect($validated['ticket_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($ticketIds === []) {
            $message = 'No tickets were selected for cancellation.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $tickets = Ticket::query()
            ->with(['workshop', 'invoice'])
            ->whereIn('id', $ticketIds)
            ->get()
            ->keyBy(fn (Ticket $ticket): int => (int) $ticket->id);

        $processSquareRefunds = $request->boolean('process_square_refund', true);
        $emailCustomer = $request->boolean('email_customer', true);
        $introLine = $this->resolveTicketCancellationIntroLine($request);
        $cancelledCount = 0;
        $failureMessages = [];
        $refundedCentsTotal = 0;
        $manualRefundRequiredCount = 0;
        $alreadyAdjustedCount = 0;

        foreach ($ticketIds as $ticketId) {
            $ticket = $tickets->get($ticketId);
            if (! $ticket instanceof Ticket) {
                $failureMessages[] = 'Ticket #'.$ticketId.' was not found.';

                continue;
            }

            $ticketLabel = (string) ($ticket->reference_code ?: '#'.$ticket->id);

            try {
                $summary = $this->cancelTicketWithFinancials(
                    ticket: $ticket,
                    squareApi: $squareApi,
                    initiatedByAdmin: true,
                    reason: $introLine,
                    processSquareRefunds: $processSquareRefunds
                );
            } catch (ValidationException $e) {
                $message = (string) collect($e->errors())->flatten()->first();
                $failureMessages[] = ($message !== '' ? $message : 'Unable to cancel ticket '.$ticketLabel.'.');

                continue;
            } catch (RuntimeException $e) {
                $failureMessages[] = $e->getMessage() !== '' ? $e->getMessage() : ('Unable to cancel ticket '.$ticketLabel.'.');

                continue;
            }

            $cancelledCount++;
            $refundedCentsTotal += (int) ($summary['refunded_cents'] ?? 0);
            if ((bool) ($summary['manual_refund_required'] ?? false)) {
                $manualRefundRequiredCount++;
            }
            if ((bool) ($summary['already_adjusted'] ?? false)) {
                $alreadyAdjustedCount++;
            }

            try {
                $freshTicket = $ticket->fresh(['invoice.user', 'invoice.taxAdjustments.lines', 'user', 'workshop.location']);
                if ($emailCustomer && $freshTicket instanceof Ticket) {
                    $this->sendCancellationNoticeEmail($request, $freshTicket, $summary, $introLine);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($cancelledCount === 0) {
            $message = (string) ($failureMessages[0] ?? 'Unable to cancel selected tickets.');
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => $failureMessages,
                ], 422);
            }

            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $message = 'Cancelled '.$cancelledCount.' ticket'.($cancelledCount === 1 ? '' : 's').'.';
        if ($refundedCentsTotal > 0) {
            $message .= ' Square refunds issued: $'.number_format($refundedCentsTotal / 100, 2).'.';
        }
        if ($manualRefundRequiredCount > 0) {
            $message .= ' '.$manualRefundRequiredCount.' ticket'.($manualRefundRequiredCount === 1 ? ' requires' : 's require').' manual refund follow-up.';
        }
        if ($alreadyAdjustedCount > 0) {
            $message .= ' '.$alreadyAdjustedCount.' ticket'.($alreadyAdjustedCount === 1 ? ' already had' : 's already had').' refund/credit adjustments.';
        }
        if (count($failureMessages) > 0) {
            $message .= ' '.count($failureMessages).' ticket'.(count($failureMessages) === 1 ? '' : 's').' could not be cancelled.';
        }

        if ($request->expectsJson()) {
            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket cancellation complete');
            session()->flash('message-type', count($failureMessages) > 0 ? 'warning' : 'success');

            return response()->json([
                'message' => $message,
                'cancelled_count' => $cancelledCount,
                'failed_count' => count($failureMessages),
                'errors' => $failureMessages,
            ]);
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Ticket cancellation complete');
        session()->flash('message-type', count($failureMessages) > 0 ? 'warning' : 'success');

        return redirect()->back();
    }

    public function invoicePdf(Request $request, Ticket $ticket)
    {
        $this->abortIfTicketNotAccessible($request, $ticket);

        if (! $ticket->invoice_id) {
            abort(404, 'No invoice linked to this ticket');
        }

        $invoice = Invoice::query()->findOrFail($ticket->invoice_id);

        // For ticket-linked invoice downloads, include related tax adjustment notes
        // in the same PDF so customers see the updated financial position.
        return app(InvoiceController::class)->pdfWithAdjustments($invoice);
    }

    public function invoiceReceipts(Request $request, Ticket $ticket): View
    {
        $this->abortIfTicketNotAccessible($request, $ticket);

        if (! $ticket->invoice_id) {
            abort(404, 'No invoice linked to this ticket');
        }

        $invoice = Invoice::query()->with('user')->findOrFail($ticket->invoice_id);
        $paymentIds = $this->receiptPaymentIdsForInvoice($invoice);

        $query = Payment::query()->with('refundOf');
        if ($paymentIds === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($builder) use ($paymentIds): void {
                $builder->whereIn('id', $paymentIds)
                    ->orWhereIn('refund_of_payment_id', $paymentIds);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search', ''));
            $query->where(function ($builder) use ($search): void {
                $builder->where('id', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%')
                    ->orWhere('payment_method', 'like', '%'.$search.'%');
            });
        }

        $receipts = $query
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        return view('tickets.invoice-receipts', [
            'ticket' => $ticket,
            'invoice' => $invoice,
            'receipts' => $receipts,
            'accessToken' => trim((string) $request->query('token', '')),
        ]);
    }

    public function invoiceReceiptPdf(Request $request, Ticket $ticket, Payment $payment)
    {
        $this->abortIfTicketNotAccessible($request, $ticket);

        if (! $ticket->invoice_id) {
            abort(404, 'No invoice linked to this ticket');
        }

        $invoice = Invoice::query()->findOrFail($ticket->invoice_id);
        if (! in_array((int) $payment->id, $this->receiptPaymentIdsForInvoice($invoice), true)) {
            abort(404);
        }

        return app(InvoiceController::class)->receiptPdf($request, $invoice, $payment);
    }

    public function updateAttendee(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfTicketNotAccessible($request, $ticket);
        if (! $this->canModifyTicketAttendee($request, $ticket)) {
            session()->flash('message', 'Ticket holder updates must be made by the ticket purchaser.');
            session()->flash('message-title', 'Update denied');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:120'],
            'surname' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:60'],
        ]);

        try {
            $reissue = DB::transaction(function () use ($ticket, $validated): array {
                /** @var Ticket $lockedTicket */
                $lockedTicket = Ticket::query()
                    ->with(['workshop.location', 'invoice.allocations.customerPayment', 'user'])
                    ->whereKey($ticket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array((int) $lockedTicket->status, Ticket::activePurchasedStatuses(), true)) {
                    throw ValidationException::withMessages([
                        'ticket' => 'Only active tickets can be updated and reissued.',
                    ]);
                }

                return $this->ticketReissueService->reissue($lockedTicket, $validated);
            });
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();
            if ($message === '') {
                $message = 'Unable to update ticket attendee details.';
            }
            session()->flash('message', $message);
            session()->flash('message-title', 'Ticket update failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if (! (bool) ($reissue['changed'] ?? false)) {
            session()->flash('message', 'No attendee changes were detected.');
            session()->flash('message-title', 'Ticket unchanged');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        /** @var Ticket|null $oldTicket */
        $oldTicket = isset($reissue['old_ticket']) && $reissue['old_ticket'] instanceof Ticket
            ? $reissue['old_ticket']->fresh(['reissuedToTicket', 'workshop.location', 'invoice.allocations.customerPayment', 'user'])
            : null;
        /** @var Ticket|null $newTicket */
        $newTicket = isset($reissue['new_ticket']) && $reissue['new_ticket'] instanceof Ticket
            ? $reissue['new_ticket']->fresh(['reissuedFromTicket', 'workshop.location', 'invoice.allocations.customerPayment', 'user'])
            : null;

        if ($oldTicket instanceof Ticket && $newTicket instanceof Ticket) {
            try {
                $this->sendReissueEmailsForTicket(
                    oldTicket: $oldTicket,
                    newTicket: $newTicket,
                    oldEmail: (string) ($reissue['old_email'] ?? ''),
                    newEmail: (string) ($reissue['new_email'] ?? ''),
                    emailChanged: (bool) ($reissue['email_changed'] ?? false),
                    purchaserEmail: $this->purchaserEmailForTicket($newTicket),
                    purchaserName: $this->purchaserNameForTicket($newTicket)
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        $newReference = $newTicket instanceof Ticket
            ? (string) ($newTicket->reference_code ?: '#'.$newTicket->id)
            : 'a new reference';
        session()->flash('message', 'Ticket details updated and reissued as '.$newReference.'.');
        session()->flash('message-title', 'Ticket reissued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    private function buildTicketPdfResponse(Ticket $ticket): Response
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
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
        } catch (\Throwable) {
            $ticketQrSvg = null;
            $ticketQrDataUri = null;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'workshop' => $ticket->workshop,
            'ticketQrSvg' => $ticketQrSvg,
            'ticketQrDataUri' => $ticketQrDataUri,
            'ticketReferenceCode' => $referenceCode,
            'ticketHeroImagePath' => $this->resolveTicketHeroImagePath($ticket),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);

        return $pdf->stream($this->ticketPdfFilename($ticket));
    }

    private function abortIfTicketNotAccessible(Request $request, Ticket $ticket): void
    {
        $authUser = $request->user();
        $isAdmin = (bool) ($authUser?->isAdmin() ?? false);
        $isAssignedUser = $authUser
            && (string) ($ticket->user_id ?? '') !== ''
            && (string) $ticket->user_id === (string) $authUser->id;
        $isEmailHolder = $authUser
            && strtolower(trim((string) ($authUser->email ?? ''))) !== ''
            && strtolower(trim((string) ($ticket->email ?? ''))) === strtolower(trim((string) ($authUser->email ?? '')));
        $hasValidMagicToken = $this->hasValidMagicTokenForTicket($request, $ticket);

        if (! ($isAdmin || $isAssignedUser || $isEmailHolder || $hasValidMagicToken)) {
            abort(403);
        }
    }

    private function hasValidMagicTokenForTicket(Request $request, Ticket $ticket): bool
    {
        $tokenString = trim((string) $request->query('token', ''));
        if ($tokenString === '') {
            return false;
        }

        $token = Token::query()
            ->where('id', $tokenString)
            ->where('type', 'tickets-access')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return false;
        }

        $tokenEmail = strtolower(trim((string) ($token->data['email'] ?? '')));
        $ticketEmail = strtolower(trim((string) ($ticket->email ?? '')));
        $ticketUserId = trim((string) ($ticket->user_id ?? ''));
        $tokenPurchaserUserId = $this->purchaserUserIdForToken($token, $tokenEmail);

        $isHolderToken = $tokenEmail !== '' && $ticketEmail !== '' && $tokenEmail === $ticketEmail;
        $isPurchaserToken = $tokenPurchaserUserId !== null && $ticketUserId !== '' && $tokenPurchaserUserId === $ticketUserId;

        return $isHolderToken || $isPurchaserToken;
    }

    private function hasValidPurchaserMagicTokenForTicket(Request $request, Ticket $ticket): bool
    {
        $tokenString = trim((string) $request->query('token', ''));
        if ($tokenString === '') {
            return false;
        }

        $token = Token::query()
            ->where('id', $tokenString)
            ->where('type', 'tickets-access')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return false;
        }

        $tokenEmail = strtolower(trim((string) ($token->data['email'] ?? '')));
        $ticketUserId = trim((string) ($ticket->user_id ?? ''));
        $tokenPurchaserUserId = $this->purchaserUserIdForToken($token, $tokenEmail);

        return $tokenPurchaserUserId !== null
            && $ticketUserId !== ''
            && $tokenPurchaserUserId === $ticketUserId;
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

    private function cancelTicketWithFinancials(
        Ticket $ticket,
        SquareApiService $squareApi,
        bool $initiatedByAdmin,
        string $reason,
        bool $processSquareRefunds = true
    ): array {
        $summary = DB::transaction(function () use ($ticket, $initiatedByAdmin, $reason, $processSquareRefunds): array {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::query()
                ->with(['workshop', 'invoice.lines'])
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array((int) $lockedTicket->status, Ticket::inactiveStatuses(), true)) {
                throw ValidationException::withMessages([
                    'ticket' => 'This ticket is already inactive.',
                ]);
            }

            if (! $initiatedByAdmin) {
                $startsAt = $lockedTicket->workshop?->starts_at;
                if (! $startsAt || $startsAt->lte(now()->addHours(2))) {
                    throw ValidationException::withMessages([
                        'ticket' => 'You can only cancel up to 2 hours before the workshop starts.',
                    ]);
                }
            }

            $allowedStatuses = Ticket::activePurchasedStatuses();
            if (! in_array((int) $lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'ticket' => 'Only active purchased tickets can be cancelled.',
                ]);
            }

            $lockedTicket->status = Ticket::STATUS_CANCELLED;
            $lockedTicket->save();

            if (! $lockedTicket->invoice_id) {
                return [
                    'adjustment_note_number' => null,
                    'originally_paid' => false,
                    'expected_refund_cents' => 0,
                    'refund_operation_ids' => [],
                ];
            }

            $invoice = $lockedTicket->invoice;
            if (! $invoice) {
                return [
                    'adjustment_note_number' => null,
                    'originally_paid' => false,
                    'expected_refund_cents' => 0,
                    'refund_operation_ids' => [],
                ];
            }

            if ((string) $invoice->status === Invoice::STATUS_CANCELLED) {
                return [
                    'adjustment_note_number' => null,
                    'originally_paid' => false,
                    'expected_refund_cents' => 0,
                    'refund_operation_ids' => [],
                    'has_square_payment' => false,
                    'invoice_already_cancelled' => true,
                ];
            }

            $originallyPaid = $invoice->settledAmount() >= ($invoice->dueAmount() - 0.0001);

            $invoiceLine = $this->resolveInvoiceLineForTicket($lockedTicket, $invoice);
            if ($this->ticketAlreadyHasAdjustment($invoice, $lockedTicket, $invoiceLine)) {
                return [
                    'adjustment_note_number' => null,
                    'originally_paid' => $originallyPaid,
                    'expected_refund_cents' => 0,
                    'refund_operation_ids' => [],
                    'has_square_payment' => false,
                    'already_adjusted' => true,
                ];
            }
            $creditLine = $this->buildCreditLineForTicket($lockedTicket, $invoice, $invoiceLine);
            $adjustmentNote = $this->createTaxAdjustmentNoteForTicketCancellation($lockedTicket, $invoice, $creditLine, $reason);
            $reconciliation = $this->reconcileCreditAllocationsForAdjustment($invoice, $adjustmentNote);
            $this->syncInvoicePaidStateAfterTicketAdjustment($invoice);

            $expectedRefundCents = (bool) $originallyPaid
                ? max(0, (int) ($creditLine['refund_cents'] ?? 0))
                : 0;
            $hasSquarePayment = InvoicePaymentAllocation::query()
                ->where('invoice_id', $invoice->id)
                ->whereHas('customerPayment', function ($query): void {
                    $query->where('gateway_provider', 'square')
                        ->orWhereNotNull('square_integration_meta->square_payment_id');
                })
                ->exists();

            $refundOperationIds = $processSquareRefunds
                ? $this->createSquareRefundOperations(
                    $invoice,
                    $adjustmentNote,
                    $lockedTicket,
                    $expectedRefundCents
                )
                : [];

            return [
                'adjustment_note_number' => (string) $adjustmentNote->adjustment_number,
                'originally_paid' => $originallyPaid,
                'expected_refund_cents' => $expectedRefundCents,
                'refund_operation_ids' => $refundOperationIds,
                'has_square_payment' => $hasSquarePayment,
                'credit_remaining' => (float) ($reconciliation['remaining'] ?? 0.0),
            ];
        });

        $expectedRefundCents = (int) $summary['expected_refund_cents'];
        $refundOperationIds = array_values(array_unique(array_map('intval', (array) ($summary['refund_operation_ids'] ?? []))));
        $refundedCents = 0;
        $refundPaymentIds = [];
        $manualRefundRequired = false;

        if ($expectedRefundCents > 0 && $processSquareRefunds) {
            $refundOutcome = $this->processSquareRefundOperations(
                $summary['refund_operation_ids'],
                'Ticket cancellation '.($ticket->reference_code ?: $ticket->id),
                $squareApi
            );
            $refundedCents = (int) ($refundOutcome['refunded_cents'] ?? 0);
            $refundPaymentIds = array_map('intval', (array) ($refundOutcome['refund_payment_ids'] ?? []));

            $manualRefundRequired = (bool) $summary['originally_paid']
                && $refundedCents < $expectedRefundCents;
        } elseif ($expectedRefundCents > 0) {
            $manualRefundRequired = (bool) $summary['originally_paid'];
        }

        unset($summary['expected_refund_cents'], $summary['refund_operation_ids']);

        if ($manualRefundRequired && $expectedRefundCents > 0 && $refundPaymentIds === [] && $refundOperationIds === []) {
            $manualRefundOperation = $this->createManualRefundOperationForTicketCancellation(
                ticket: $ticket,
                invoice: $ticket->invoice,
                requestedCents: $expectedRefundCents,
                reason: $reason
            );
            if ($manualRefundOperation instanceof SquareRefundOperation) {
                $refundOperationIds[] = (int) $manualRefundOperation->id;
            }
        }

        $summary['refunded_cents'] = $refundedCents;
        $summary['refund_payment_ids'] = array_values(array_unique(array_filter($refundPaymentIds, fn (int $id) => $id > 0)));
        $summary['manual_refund_required'] = $manualRefundRequired;
        if ($ticket->relationLoaded('workshop') || $ticket->workshop) {
            $this->workshopTicketService->syncManagedTicketStatus($ticket->workshop);
        }

        return $summary;
    }

    private function resolveInvoiceLineForTicket(Ticket $ticket, Invoice $invoice): ?InvoiceLine
    {
        if ($ticket->invoice_line_id) {
            $line = $invoice->lines->firstWhere('id', (int) $ticket->invoice_line_id);
            if ($line instanceof InvoiceLine) {
                return $line;
            }
        }

        $ticketLine = $invoice->lines->first(fn (InvoiceLine $line) => $line->kind === 'ticket' && (float) $line->quantity > 0);
        if ($ticketLine instanceof InvoiceLine) {
            return $ticketLine;
        }

        return null;
    }

    private function buildCreditLineForTicket(Ticket $ticket, Invoice $invoice, ?InvoiceLine $invoiceLine): array
    {
        $ticketCount = max(1, Ticket::query()->where('invoice_id', $invoice->id)->count());
        $quantityBase = $invoiceLine ? max(1, (int) round(abs((float) $invoiceLine->quantity))) : $ticketCount;
        $lineTotalEx = $invoiceLine ? (float) $invoiceLine->line_total_ex_tax : ((float) $invoice->subtotal_amount);
        $lineTotalInc = $invoiceLine ? (float) $invoiceLine->line_total_inc_tax : ((float) $invoice->total_amount);
        $taxRate = $invoiceLine ? (float) $invoiceLine->tax_rate : 0.10;
        $description = ($invoiceLine?->description) ?: (($ticket->workshop->title ?? 'Workshop').' - Ticket refund');
        $sourceType = $invoiceLine?->source_type;
        $sourceId = $invoiceLine?->source_id;
        $originalInvoiceLineId = $invoiceLine?->id;
        $originalTicketReference = trim((string) ($ticket->reference_code ?: $ticket->id));
        $reissuedTicket = $ticket->reissuedToTicket;
        $reissuedTicketReference = '';
        if ($reissuedTicket instanceof Ticket) {
            $reissuedTicketReference = trim((string) ($reissuedTicket->reference_code ?: $reissuedTicket->id));
        } elseif (trim((string) ($ticket->reissued_to_ticket_id ?? '')) !== '') {
            $reissuedTicketReference = trim((string) $ticket->reissued_to_ticket_id);
        }

        $unitEx = round($lineTotalEx / max(1, $quantityBase), 2);
        $lineEx = round($unitEx, 2);
        $taxAmount = round($lineEx * $taxRate, 2);
        $lineInc = round($lineEx + $taxAmount, 2);

        return [
            'kind' => 'ticket',
            'description' => $description,
            'notes' => trim(implode('; ', array_filter([
                'Ticket cancellation refund for original ticket '.$originalTicketReference,
                $reissuedTicketReference !== '' ? 'reissued as '.$reissuedTicketReference : null,
            ]))),
            'details_json' => [
                'ticket_id' => $ticket->id,
                'ticket_reference' => $originalTicketReference,
                'reissued_ticket_reference' => $reissuedTicketReference !== '' ? $reissuedTicketReference : null,
                'workshop_id' => $ticket->workshop_id,
            ],
            'quantity' => 1,
            'unit_price_ex_tax' => $unitEx,
            'tax_rate' => $taxRate,
            'line_total_ex_tax' => $lineEx,
            'tax_amount' => $taxAmount,
            'line_total_inc_tax' => $lineInc,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'original_invoice_line_id' => $originalInvoiceLineId,
            'refund_cents' => max(0, (int) round(abs($lineInc) * 100)),
        ];
    }

    private function createTaxAdjustmentNoteForTicketCancellation(
        Ticket $ticket,
        Invoice $invoice,
        array $creditLine,
        string $reason
    ): TaxAdjustment {
        $credit = new TaxAdjustment();
        $credit->invoice_id = $invoice->id;
        $credit->adjustment_number = $this->documentNumbers->nextTaxAdjustmentNumber();
        $credit->issue_date = now()->startOfDay();
        $credit->subtotal_amount = -1 * abs((float) $creditLine['line_total_ex_tax']);
        $credit->gst_amount = -1 * abs((float) $creditLine['tax_amount']);
        $credit->total_amount = -1 * abs((float) $creditLine['line_total_inc_tax']);
        $credit->notes = trim(implode("\n", array_filter([
            'Tax adjustment note for invoice '.$invoice->invoice_number,
            $reason,
            'Ticket: '.($ticket->reference_code ?: $ticket->id),
        ])));
        $credit->save();

        $credit->lines()->create([
            'invoice_line_id' => $creditLine['original_invoice_line_id'] ?? null,
            'line_number' => 1,
            'description' => (string) $creditLine['description'],
            'notes' => (string) ($creditLine['notes'] ?? ''),
            'quantity' => abs((float) ($creditLine['quantity'] ?? 1)),
            'unit_price_ex_tax' => abs((float) $creditLine['unit_price_ex_tax']),
            'tax_rate' => (float) $creditLine['tax_rate'],
            'line_total_ex_tax' => abs((float) $creditLine['line_total_ex_tax']),
            'tax_amount' => abs((float) $creditLine['tax_amount']),
            'line_total_inc_tax' => abs((float) $creditLine['line_total_inc_tax']),
        ]);

        return $credit;
    }

    private function createSquareRefundOperations(
        Invoice $invoice,
        TaxAdjustment $adjustmentNote,
        Ticket $ticket,
        int $targetCents
    ): array {
        if ($targetCents <= 0) {
            return [];
        }

        $allocations = InvoicePaymentAllocation::query()
            ->with('customerPayment')
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get();

        $remaining = $targetCents;
        $operationIds = [];

        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $customerPayment = $allocation->customerPayment;
            if (! $customerPayment instanceof Payment) {
                continue;
            }
            if ($customerPayment->gateway_provider !== 'square') {
                continue;
            }
            if (! is_string($customerPayment->square_payment_id) || trim($customerPayment->square_payment_id) === '') {
                continue;
            }

            $refundable = (int) $customerPayment->square_remaining_refundable_money;
            if ($refundable <= 0) {
                continue;
            }

            $allocationCents = max(0, (int) round(((float) $allocation->allocated_amount) * 100));
            $refundCents = min($remaining, $refundable, max(1, $allocationCents));

            $idempotencyKey = 't'.$ticket->id.'-a'.$adjustmentNote->id.'-p'.$customerPayment->id.'-'.$refundCents;

            $operation = SquareRefundOperation::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'invoice_id' => $invoice->id,
                    'tax_adjustment_id' => $adjustmentNote->id,
                    'ticket_id' => $ticket->id,
                    'payment_id' => $customerPayment->id,
                    'requested_cents' => $refundCents,
                    'status' => SquareRefundOperation::STATUS_PENDING,
                ]
            );

            $operationIds[] = (int) $operation->id;
            $remaining -= $refundCents;
        }

        return array_values(array_unique($operationIds));
    }

    private function processSquareRefundOperations(
        array $operationIds,
        string $reason,
        SquareApiService $squareApi
    ): array {
        $operationIds = array_values(array_filter(array_map('intval', $operationIds), fn (int $id) => $id > 0));
        if ($operationIds === []) {
            return [
                'refunded_cents' => 0,
                'refund_payment_ids' => [],
            ];
        }

        $refundedCents = 0;
        $refundPaymentIds = [];

        foreach ($operationIds as $operationId) {
            $operation = SquareRefundOperation::query()
                ->with('customerPayment')
                ->find($operationId);
            if (! $operation instanceof SquareRefundOperation) {
                continue;
            }

            if ($operation->status === SquareRefundOperation::STATUS_COMPLETED) {
                $refundedCents += (int) $operation->refunded_cents;

                continue;
            }

            $customerPayment = $operation->customerPayment;
            if (! $customerPayment instanceof Payment) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Customer payment record is missing.';
                $operation->processed_at = now();
                $operation->save();

                continue;
            }

            if (! $squareApi->isEnabled()) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Square integration is not enabled.';
                $operation->processed_at = now();
                $operation->save();

                continue;
            }

            if (! is_string($customerPayment->square_payment_id) || trim($customerPayment->square_payment_id) === '') {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Square payment id is missing on the customer payment.';
                $operation->processed_at = now();
                $operation->save();

                continue;
            }

            $refundable = (int) $customerPayment->square_remaining_refundable_money;
            $refundCents = min((int) $operation->requested_cents, $refundable);
            if ($refundCents <= 0) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'No remaining refundable Square balance on the payment.';
                $operation->processed_at = now();
                $operation->save();

                continue;
            }

            try {
                $response = $squareApi->createRefund([
                    'idempotency_key' => $operation->idempotency_key,
                    'payment_id' => $customerPayment->square_payment_id,
                    'amount_money' => [
                        'amount' => $refundCents,
                        'currency' => 'AUD',
                    ],
                    'reason' => mb_substr($reason, 0, 255),
                ]);

                $refund = (array) ($response['refund'] ?? []);
                if ($refund === []) {
                    throw new RuntimeException('Square refund failed: no refund object was returned.');
                }

                $refundValue = (int) ($refund['amount_money']['amount'] ?? 0);
                $refundStatus = strtoupper(trim((string) ($refund['status'] ?? 'UNKNOWN')));
                if ($refundValue <= 0 || ! in_array($refundStatus, ['PENDING', 'COMPLETED'], true)) {
                    throw new RuntimeException('Square refund was not accepted.');
                }

                $customerPayment->gateway_provider = 'square';
                $currentRefunded = (int) ($customerPayment->square_refunded_money_amount ?? 0);
                $paidValue = (int) ($customerPayment->square_paid_money_amount ?? 0);
                $refundPayment = $this->createSquareRefundPaymentRecord(
                    $customerPayment,
                    $refundValue,
                    (string) ($refund['id'] ?? ''),
                    (string) ($refund['status'] ?? 'PENDING'),
                    $reason
                );
                if ($refundPayment instanceof Payment) {
                    $refundPaymentIds[] = (int) $refundPayment->id;
                }
                $recordedRefundedCents = (int) round(((float) $customerPayment->refunds()->sum('total_amount')) * 100);
                $customerPayment->square_refunded_money_amount = min($paidValue, max($currentRefunded, $recordedRefundedCents));
                $customerPayment->save();

                $operation->status = SquareRefundOperation::STATUS_COMPLETED;
                $operation->refunded_cents = $refundValue;
                $operation->square_refund_id = (string) ($refund['id'] ?? null);
                $operation->payload = $response;
                $operation->failure_message = null;
                $operation->processed_at = now();
                $operation->save();

                $refundedCents += $refundValue;
            } catch (Throwable $e) {
                report($e);

                $operation->status = SquareRefundOperation::STATUS_FAILED;
                $operation->failure_message = mb_substr($e->getMessage(), 0, 500);
                $operation->processed_at = now();
                $operation->save();
            }
        }

        return [
            'refunded_cents' => $refundedCents,
            'refund_payment_ids' => array_values(array_unique(array_map('intval', $refundPaymentIds))),
        ];
    }

    private function createSquareRefundPaymentRecord(
        Payment $originalPayment,
        int $refundCents,
        string $squareRefundId,
        string $squareStatus,
        string $reason
    ): ?Payment {
        $refundAmount = round(max(0, $refundCents) / 100, 2);
        if ($refundAmount <= 0.0001) {
            return null;
        }

        $existing = Payment::query()
            ->where('refund_of_payment_id', $originalPayment->id)
            ->where('gateway_provider', 'square')
            ->where('gateway_reference_id', $squareRefundId)
            ->first();

        if ($existing instanceof Payment) {
            return $existing;
        }

        $refundPayment = new Payment();
        $refundPayment->refund_of_payment_id = $originalPayment->id;
        $refundPayment->kind = Payment::KIND_REFUND;
        $refundPayment->user_id = $originalPayment->user_id;
        $refundPayment->created_by = auth()->id();
        $refundPayment->received_on = now();
        $refundPayment->payment_method = (string) ($originalPayment->payment_method ?: Payment::PAYMENT_METHOD_CREDIT_CARD);
        $refundPayment->reference = trim(implode(' | ', array_filter([
            'Refund for payment #'.$originalPayment->id,
            $originalPayment->reference ? 'Original: '.$originalPayment->reference : null,
        ])));
        $refundPayment->total_amount = $refundAmount;
        $refundPayment->gst_amount = 0;
        $refundPayment->notes = $reason !== '' ? $reason : 'Square refund';
        $refundPayment->gateway_provider = 'square';
        $refundPayment->gateway_status = $squareStatus !== '' ? $squareStatus : 'PENDING';
        $refundPayment->gateway_reference_id = $squareRefundId !== '' ? $squareRefundId : null;
        $refundPayment->save();

        return $refundPayment;
    }

    private function syncInvoicePaidStateAfterTicketAdjustment(Invoice $invoice): void
    {
        if ((string) $invoice->status === Invoice::STATUS_CANCELLED) {
            return;
        }

        $allocated = $invoice->settledAmount();
        $invoiceTotal = $invoice->dueAmount();
        $isPaid = $allocated >= ($invoiceTotal - 0.0001);

        if ($isPaid) {
            $invoice->status = Invoice::STATUS_PAID;
            $invoice->save();

            Ticket::query()
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT])
                ->update(['status' => Ticket::STATUS_DONE]);

            return;
        }

        if ((string) $invoice->status === Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_ISSUED;
            $invoice->save();
        }
    }

    /**
     * @param  array<int, int>  $refundPaymentIds
     * @return array<int, array{filename:string,content:string,mime?:string}>
     */
    private function buildCancellationDocumentAttachmentsForTicket(Ticket $ticket, array $refundPaymentIds = []): array
    {
        $invoice = $ticket->invoice;
        if (! $invoice instanceof Invoice) {
            return [];
        }

        $invoice->loadMissing('user', 'taxAdjustments.lines', 'allocations.customerPayment');
        $refundPaymentIds = array_values(array_filter(array_map('intval', $refundPaymentIds), fn (int $id) => $id > 0));

        $attachments = [];
        $invoicePdf = $this->buildInvoicePdfBinary($invoice);
        if ($invoicePdf !== null) {
            $attachments[] = [
                'filename' => $this->invoicePdfFilename($invoice),
                'content' => $invoicePdf,
                'mime' => 'application/pdf',
            ];
        }

        foreach ($invoice->taxAdjustments as $adjustment) {
            $adjustmentPdf = $this->buildTaxAdjustmentPdfBinary($invoice, $adjustment);
            if ($adjustmentPdf === null) {
                continue;
            }

            $attachments[] = [
                'filename' => 'tax-adjustment-'.((string) $adjustment->adjustment_number).'.pdf',
                'content' => $adjustmentPdf,
                'mime' => 'application/pdf',
            ];
        }

        if ($refundPaymentIds !== []) {
            $refundPayments = Payment::query()
                ->whereIn('id', $refundPaymentIds)
                ->where('kind', Payment::KIND_REFUND)
                ->orderByDesc('received_on')
                ->orderByDesc('created_at')
                ->get();

            foreach ($refundPayments as $refundPayment) {
                $refundPdf = $this->buildPaymentReceiptPdfBinary($invoice, $refundPayment);
                if ($refundPdf === null) {
                    continue;
                }

                $attachments[] = [
                    'filename' => 'refund-receipt-'.((int) $refundPayment->id).'.pdf',
                    'content' => $refundPdf,
                    'mime' => 'application/pdf',
                ];
            }
        }

        return $attachments;
    }

    private function sendCancellationNoticeEmail(Request $request, Ticket $ticket, array $summary, string $introLine = 'The following ticket has been cancelled.'): void
    {
        $recipient = trim($this->purchaserEmailForTicket($ticket));
        if ($recipient === '') {
            $recipient = $this->resolveTicketsAccessTokenEmail($request);
        }
        if ($recipient === '') {
            Log::warning('Ticket cancellation notice skipped: no recipient email.', [
                'ticket_id' => (int) $ticket->id,
                'invoice_id' => (int) ($ticket->invoice_id ?? 0),
            ]);

            return;
        }

        $workshopTitle = trim((string) ($ticket->workshop->title ?? 'Workshop'));
        $workshopTime = method_exists($ticket->workshop, 'formattedDateRange')
            ? (string) ($ticket->workshop->formattedDateRange() ?? '-')
            : (string) ($ticket->workshop?->starts_at?->format('M j, Y g:i a') ?? '-');
        $workshopLocation = (string) ($ticket->workshop?->getLocationName() ?? '-');
        $ticketReference = (string) ($ticket->reference_code ?: '#'.$ticket->id);

        $purchaseWasPaid = (bool) ($summary['originally_paid'] ?? false);
        $invoiceTotal = (float) ($ticket->invoice->total_amount ?? 0);
        $isFreeBooking = (int) ($ticket->invoice_id ?? 0) <= 0 || $invoiceTotal <= 0.0001;
        $invoiceAlreadyCancelled = (bool) ($summary['invoice_already_cancelled'] ?? false);
        $manualRefundRequired = (bool) ($summary['manual_refund_required'] ?? false);
        $refundPaymentIds = array_values(array_unique(array_filter(array_map('intval', (array) ($summary['refund_payment_ids'] ?? [])), fn (int $id) => $id > 0)));
        $attachments = $manualRefundRequired ? [] : $this->buildCancellationDocumentAttachmentsForTicket($ticket, $refundPaymentIds);

        $financialSummary = '';
        if ($invoiceAlreadyCancelled) {
            $financialSummary .= 'The linked invoice was already cancelled.';
        } elseif (! $isFreeBooking) {
            if ($purchaseWasPaid) {
                if ($manualRefundRequired) {
                    $financialSummary .= 'Credit will be applied to your account or a refund for the purchase will be processed manually.';
                } elseif ($refundPaymentIds !== []) {
                    $financialSummary .= 'A refund for the purchase has been processed automatically.';
                } else {
                    $financialSummary .= 'A refund for the purchase will be processed automatically.';
                }
            } else {
                $financialSummary .= 'Any unpaid invoices related to this ticket will be cancelled.';
            }
        }
        $financialSummary .= ' If you have any questions, please don\'t hesitate to contact us.';

        $documentSummary = '';
        if ($manualRefundRequired) {
            $documentSummary = 'The invoice documents will be sent once processed.';
        } elseif ($attachments !== []) {
            $documentSummary = $refundPaymentIds !== []
                ? 'The invoice, tax adjustment, and refund receipt documents are attached to this email.'
                : 'The invoice documents are attached to this email.';
        }

        dispatch(new SendEmail($recipient, new TicketCancelledNotice(
            recipientName: $this->purchaserNameForTicket($ticket) ?: $recipient,
            ticketReference: $ticketReference,
            workshopTitle: $workshopTitle !== '' ? $workshopTitle : 'Workshop',
            workshopTime: $workshopTime !== '' ? $workshopTime : '-',
            workshopLocation: $workshopLocation !== '' ? $workshopLocation : '-',
            financialSummary: $financialSummary,
            attachments: $attachments,
            documentSummary: $documentSummary,
            introLine: $introLine,
        )))->onQueue('mail');

        if ($manualRefundRequired) {
            $this->notifyAdminManualRefundRequired($ticket, $introLine);
        }

        $holderEmail = strtolower(trim((string) ($ticket->email ?? '')));
        if ($holderEmail !== '' && $holderEmail !== strtolower($recipient)) {
            dispatch(new SendEmail($holderEmail, new TicketAttendeeUpdate(
                mode: 'cancelled',
                recipientName: trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: $holderEmail,
                purchaserName: $this->purchaserNameForTicket($ticket) ?: $recipient,
                workshop: [
                    'title' => $workshopTitle !== '' ? $workshopTitle : 'Workshop',
                    'time' => $workshopTime !== '' ? $workshopTime : '-',
                    'location' => $workshopLocation !== '' ? $workshopLocation : '-',
                ],
                ticket: [
                    'reference' => $ticketReference,
                    'name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                    'email' => $holderEmail,
                    'phone' => (string) ($ticket->phone ?? '-'),
                    'earlyBird' => $ticket->isEarlyBirdTicket(),
                ],
            )))->onQueue('mail');
        }
    }

    private function notifyAdminManualRefundRequired(Ticket $ticket, string $introLine = 'The following ticket has been cancelled.'): void
    {
        $recipients = $this->manualRefundAdminRecipients();
        if ($recipients === []) {
            Log::warning('Manual refund notice skipped: no admin recipient email configured.', [
                'ticket_id' => (int) $ticket->id,
                'invoice_id' => (int) ($ticket->invoice_id ?? 0),
            ]);

            return;
        }

        $ticket->loadMissing(['workshop', 'invoice.user', 'invoice.allocations.customerPayment.user']);
        $invoice = $ticket->invoice;
        if (! $invoice) {
            return;
        }

        $refundOperations = SquareRefundOperation::query()
            ->with(['invoice.user', 'customerPayment.user', 'ticket.workshop'])
            ->where('ticket_id', $ticket->id)
            ->whereIn('status', [SquareRefundOperation::STATUS_FAILED, SquareRefundOperation::STATUS_MANUAL_REQUIRED])
            ->orderBy('created_at')
            ->get();

        $operationSummaries = $refundOperations->map(function (SquareRefundOperation $operation): array {
            $payment = $operation->customerPayment;
            $ticket = $operation->ticket;
            $invoice = $operation->invoice;

            return [
                'operation_id' => (int) $operation->id,
                'status' => (string) $operation->status,
                'requested_amount' => round(((int) $operation->requested_cents) / 100, 2),
                'refunded_amount' => round(((int) $operation->refunded_cents) / 100, 2),
                'failure_message' => trim((string) $operation->failure_message),
                'payment_id' => $payment instanceof Payment ? (int) $payment->id : null,
                'payment_edit_url' => $payment instanceof Payment ? route('admin.payment.edit', $payment) : null,
                'ticket_reference' => $ticket instanceof Ticket ? (string) ($ticket->reference_code ?: '#'.$ticket->id) : null,
                'invoice_number' => $invoice instanceof Invoice ? (string) $invoice->invoice_number : null,
                'workshop_title' => $ticket instanceof Ticket ? (string) ($ticket->workshop->title ?? 'Workshop') : 'Workshop',
            ];
        })->all();

        $refundAmount = $refundOperations->sum(fn (SquareRefundOperation $operation): int => (int) $operation->requested_cents);
        $customerName = $this->purchaserNameForTicket($ticket) ?: trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? '')));
        $customerEmail = $this->purchaserEmailForTicket($ticket);
        $creditsUrl = route('admin.payment.refunds');
        $invoiceUrl = route('admin.invoice.edit', $invoice);
        $ticketUrl = route('admin.workshop.tickets', $ticket->workshop);

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail($recipient, new TicketManualRefundNotice(
                recipientName: 'Admin',
                ticketReference: (string) ($ticket->reference_code ?: '#'.$ticket->id),
                workshopTitle: (string) ($ticket->workshop->title ?? 'Workshop'),
                invoiceNumber: (string) $invoice->invoice_number,
                customerName: $customerName !== '' ? $customerName : 'Unknown customer',
                customerEmail: $customerEmail !== '' ? $customerEmail : '-',
                refundAmount: $refundAmount > 0 ? round($refundAmount / 100, 2) : null,
                creditsUrl: $creditsUrl,
                invoiceUrl: $invoiceUrl,
                ticketUrl: $ticketUrl,
                operationSummaries: $operationSummaries,
                introLine: $introLine,
            )))->onQueue('mail');
        }
    }

    private function createManualRefundOperationForTicketCancellation(
        Ticket $ticket,
        ?Invoice $invoice,
        int $requestedCents,
        string $reason
    ): ?SquareRefundOperation {
        if (! $invoice instanceof Invoice || $requestedCents <= 0) {
            return null;
        }

        $invoice->loadMissing('allocations.customerPayment');
        $candidatePayment = $invoice->allocations
            ->map(fn ($allocation) => $allocation->customerPayment)
            ->filter(fn ($payment) => $payment instanceof Payment && (string) ($payment->kind ?? Payment::KIND_PAYMENT) === Payment::KIND_PAYMENT)
            ->first(function (Payment $payment): bool {
                return $this->remainingRefundableAmount($payment) > 0.0001;
            });

        if (! $candidatePayment instanceof Payment) {
            return null;
        }

        $idempotencyKey = 't'.$ticket->id.'-i'.$invoice->id.'-p'.$candidatePayment->id.'-'.$requestedCents;

        return SquareRefundOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'invoice_id' => $invoice->id,
                'tax_adjustment_id' => $invoice->taxAdjustments()->latest('id')->first()?->id,
                'ticket_id' => $ticket->id,
                'payment_id' => $candidatePayment->id,
                'requested_cents' => $requestedCents,
                'status' => SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                'failure_message' => 'Payment was not processed through Square. Manual refund required.',
                'processed_at' => now(),
                'payload' => [
                    'manual_refund' => [
                        'source' => 'ticket_cancellation',
                        'reason' => $reason,
                        'ticket_id' => (int) $ticket->id,
                        'invoice_id' => (int) $invoice->id,
                    ],
                ],
            ]
        );
    }

    private function remainingRefundableAmount(Payment $payment): float
    {
        $original = max(0, round((float) $payment->total_amount, 2));
        $refunded = (float) $payment->refunds()->sum('total_amount');

        return max(0, round($original - $refunded, 2));
    }

    /**
     * @return array<int, string>
     */
    private function manualRefundAdminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveTicketCancellationIntroLine(Request $request): string
    {
        $reason = trim((string) $request->input('reason', ''));

        return $reason !== ''
            ? $reason
            : "We're sorry, but this workshop has been cancelled. Please see below for your refund or credit details.";
    }

    private function resolveTicketsAccessTokenEmail(Request $request): string
    {
        $tokenString = trim((string) $request->query('token', $request->input('token', '')));
        if ($tokenString === '') {
            return '';
        }

        $token = Token::query()
            ->where('id', $tokenString)
            ->where('type', 'tickets-access')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return '';
        }

        return strtolower(trim((string) ($token->data['email'] ?? '')));
    }

    private function reconcileCreditAllocationsForAdjustment(Invoice $invoice, TaxAdjustment $taxAdjustment): array
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

    private function ticketAlreadyHasAdjustment(Invoice $invoice, Ticket $ticket, ?InvoiceLine $invoiceLine): bool
    {
        $ticketReference = trim((string) ($ticket->reference_code ?: $ticket->id));
        if ($ticketReference !== '') {
            $existingByNote = TaxAdjustment::query()
                ->where('invoice_id', $invoice->id)
                ->where('notes', 'like', '%Ticket: '.$ticketReference.'%')
                ->exists();

            if ($existingByNote) {
                return true;
            }
        }

        if ($invoiceLine instanceof InvoiceLine) {
            return DB::table('tax_adjustment_lines')
                ->join('tax_adjustments', 'tax_adjustments.id', '=', 'tax_adjustment_lines.tax_adjustment_id')
                ->where('tax_adjustments.invoice_id', $invoice->id)
                ->where('tax_adjustment_lines.invoice_line_id', $invoiceLine->id)
                ->exists();
        }

        return false;
    }

    private function sendReissueEmailsForTicket(
        Ticket $oldTicket,
        Ticket $newTicket,
        string $oldEmail,
        string $newEmail,
        bool $emailChanged,
        string $purchaserEmail,
        string $purchaserName
    ): void {
        $workshopInfo = [
            'title' => (string) ($newTicket->workshop->title ?? ''),
            'time' => (string) ($newTicket->workshop?->getTicketTimeRangeLabel() ?? '-'),
            'location' => (string) ($newTicket->workshop?->getLocationDisplay(true) ?? '-'),
        ];

        $ticketAttachment = $this->ticketAttachmentForTicket($newTicket);

        if ($purchaserEmail !== '') {
            $invoice = $newTicket->invoice;
            $payment = $invoice ? $this->latestPaymentForInvoice($invoice) : null;

            $this->sendPurchaserTicketEmail(
                recipientEmail: $purchaserEmail,
                recipientName: $purchaserName !== '' ? $purchaserName : ($newTicket->user?->getName() ?: $purchaserEmail),
                workshopInfo: $workshopInfo,
                ticket: $newTicket,
                invoice: $invoice,
                payment: $payment
            );
        }

        $normalizedPurchaser = strtolower(trim($purchaserEmail));
        $normalizedOld = strtolower(trim($oldEmail));
        $normalizedNew = strtolower(trim($newEmail));

        $attendeeName = trim((string) (($newTicket->firstname ?? '').' '.($newTicket->surname ?? '')));
        $ticketInfo = [
            'reference' => (string) ($newTicket->reference_code ?: $newTicket->id),
            'name' => $attendeeName !== '' ? $attendeeName : '-',
            'email' => (string) ($newTicket->email ?? ''),
            'phone' => (string) ($newTicket->phone ?? ''),
            'earlyBird' => $newTicket->isEarlyBirdTicket(),
        ];

        if ($emailChanged) {
            if ($normalizedOld !== '' && $normalizedOld !== $normalizedPurchaser) {
                dispatch(new SendEmail($normalizedOld, new TicketAttendeeUpdate(
                    mode: 'transferred_away',
                    recipientName: trim((string) (($oldTicket->firstname ?? '').' '.($oldTicket->surname ?? ''))),
                    purchaserName: $purchaserName,
                    workshop: $workshopInfo,
                    ticket: $ticketInfo,
                )))->onQueue('mail');
            }

            if ($normalizedNew !== '' && $normalizedNew !== $normalizedPurchaser) {
                dispatch(new SendEmail($normalizedNew, new TicketAttendeeUpdate(
                    mode: 'new_holder',
                    recipientName: $attendeeName,
                    purchaserName: $purchaserName,
                    workshop: $workshopInfo,
                    ticket: $ticketInfo,
                    attachment: $ticketAttachment
                )))->onQueue('mail');
            }

            return;
        }

        if ($normalizedNew !== '' && $normalizedNew !== $normalizedPurchaser) {
            dispatch(new SendEmail($normalizedNew, new TicketAttendeeUpdate(
                mode: 'details_updated',
                recipientName: $attendeeName,
                purchaserName: $purchaserName,
                workshop: $workshopInfo,
                ticket: $ticketInfo,
                attachment: $ticketAttachment
            )))->onQueue('mail');
        }
    }

    private function sendPurchaserTicketEmail(
        string $recipientEmail,
        string $recipientName,
        array $workshopInfo,
        Ticket $ticket,
        ?Invoice $invoice,
        ?Payment $payment
    ): void {
        $attachments = [];
        $ticketAttachment = $this->ticketAttachmentForTicket($ticket);
        if ($ticketAttachment) {
            $attachments[] = $ticketAttachment;
        }

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

        dispatch(new SendEmail($recipientEmail, new TicketOrderConfirmation(
            recipientName: $recipientName,
            workshop: $workshopInfo,
            tickets: [[
                'reference' => (string) ($ticket->reference_code ?: $ticket->id),
                'name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                'email' => (string) ($ticket->email ?? ''),
            ]],
            paymentMethodLabel: 'Reissued Ticket',
            amount: 0.0,
            invoice: $invoice ? [
                'number' => (string) $invoice->invoice_number,
                'status' => (string) $invoice->status,
            ] : null,
            attachments: $attachments,
        )))->onQueue('mail');
    }

    private function ticketAttachmentForTicket(Ticket $ticket): ?array
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

    private function purchaserEmailForTicket(Ticket $ticket): string
    {
        $invoiceEmail = strtolower(trim((string) ($ticket->invoice->billing_email ?? '')));
        if ($invoiceEmail !== '') {
            return $invoiceEmail;
        }

        $userEmail = strtolower(trim((string) ($ticket->user->email ?? '')));
        if ($userEmail !== '') {
            return $userEmail;
        }

        return strtolower(trim((string) ($ticket->email ?? '')));
    }

    private function purchaserNameForTicket(Ticket $ticket): string
    {
        $invoiceName = trim((string) ($ticket->invoice->billing_name ?? ''));
        if ($invoiceName !== '') {
            return $invoiceName;
        }

        return trim((string) ($ticket->user?->getName() ?? ''));
    }

    private function latestPaymentForInvoice(Invoice $invoice): ?Payment
    {
        $paymentIds = $this->receiptPaymentIdsForInvoice($invoice);
        if ($paymentIds === []) {
            return null;
        }

        return Payment::query()
            ->whereIn('id', $paymentIds)
            ->where('kind', Payment::KIND_PAYMENT)
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->first();
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

    private function resolveTicketHeroImagePath(Ticket $ticket): ?string
    {
        $hero = $ticket->workshop?->hero;
        if (! $hero) {
            return null;
        }

        $thumbnailVariant = $hero->getClosestVariant('thumbnail');
        $thumbnailVariantName = trim((string) ($thumbnailVariant['variant'] ?? ''));
        $thumbnailPath = (string) ($thumbnailVariant['file'] ?? '');
        if ($thumbnailVariantName !== '' && $thumbnailPath !== '' && is_file($thumbnailPath)) {
            return $thumbnailPath;
        }

        return null;
    }

    private function buildInvoicePdfBinary(Invoice $invoice): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        $invoice->loadMissing('user', 'lines');
        $itemPages = $this->invoiceItemPagesForPdf($invoice);

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

        return DomPdf::loadView('pdf.payment-receipt', [
            'isRefund' => $payment->isRefund(),
            'receiptTitle' => $payment->isRefund() ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $payment->isRefund() ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => (float) $payment->total_amount,
            'gstAmount' => abs((float) $payment->gst_amount),
            'paymentMethod' => Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_CREDIT_CARD)),
            'paidOn' => $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($payment->reference ?? ''),
            'footerMessage' => $payment->isRefund() ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);
    }

    private function buildTaxAdjustmentPdfBinary(Invoice $invoice, TaxAdjustment $adjustment): ?string
    {
        if (! class_exists(DomPdf::class)) {
            return null;
        }

        return DomPdf::loadView('pdf.tax-adjustment', [
            'invoice' => $invoice,
            'adjustment' => $adjustment,
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);
    }

    private function invoiceItemPagesForPdf(Invoice $invoice): array
    {
        $items = $invoice->lines->map(function (InvoiceLine $line): array {
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
        })->values()->all();

        if ($items === []) {
            return [[]];
        }

        return [$items];
    }

    private function resolveOrCreateTokenUserIdForTicketEmail(string $email, Ticket $firstTicket): string
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->save();
        }

        Ticket::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        return (string) $user->id;
    }

    private function canModifyTicketAttendee(Request $request, Ticket $ticket): bool
    {
        $authUser = $request->user();
        if ((bool) ($authUser?->isAdmin() ?? false)) {
            return true;
        }

        if ($authUser && (string) ($ticket->user_id ?? '') !== '' && (string) $ticket->user_id === (string) $authUser->id) {
            return true;
        }

        $tokenString = trim((string) $request->query('token', ''));
        if ($tokenString === '') {
            return false;
        }

        $token = Token::query()
            ->where('id', $tokenString)
            ->where('type', 'tickets-access')
            ->where('expires_at', '>', now())
            ->first();
        if (! $token) {
            return false;
        }

        $tokenEmail = strtolower(trim((string) ($token->data['email'] ?? '')));
        $tokenPurchaserUserId = $this->purchaserUserIdForToken($token, $tokenEmail);

        return $tokenPurchaserUserId !== null
            && trim((string) ($ticket->user_id ?? '')) !== ''
            && $tokenPurchaserUserId === (string) $ticket->user_id;
    }

    private function ticketLookupQueryForEmail(string $email): Builder
    {
        $query = Ticket::query();
        $query->where(function (Builder $builder) use ($email): void {
            $builder->whereRaw('LOWER(email) = ?', [$email]);
            $userId = (string) User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->value('id');
            if (trim($userId) !== '') {
                $builder->orWhere('user_id', $userId);
            }
        });

        $query->where(function ($builder) {
            $builder->where('status', '!=', Ticket::STATUS_HOLD)
                ->orWhere('created_at', '>=', now()->subMinutes(10));
        });

        return $query;
    }

    /**
     * @return array{byInvoiceId: array<string, float>, invoiceCount: int, total: float}
     */
    private function ticketOutstandingSummary(Builder $query): array
    {
        $invoiceIds = (clone $query)
            ->reorder()
            ->whereNotNull('invoice_id')
            ->select('invoice_id')
            ->distinct()
            ->pluck('invoice_id')
            ->map(fn ($invoiceId): string => trim((string) $invoiceId))
            ->filter(fn (string $invoiceId): bool => $invoiceId !== '')
            ->values();

        if ($invoiceIds->isEmpty()) {
            return [
                'byInvoiceId' => [],
                'invoiceCount' => 0,
                'total' => 0.0,
            ];
        }

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy(fn (Invoice $invoice): string => (string) $invoice->id);

        $byInvoiceId = [];
        $total = 0.0;

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoices->get($invoiceId);
            if (! $invoice instanceof Invoice) {
                continue;
            }

            $outstanding = round((float) $invoice->outstandingAmount(), 2);
            if ($outstanding <= 0.0001) {
                continue;
            }

            $byInvoiceId[$invoiceId] = $outstanding;
            $total = round($total + $outstanding, 2);
        }

        return [
            'byInvoiceId' => $byInvoiceId,
            'invoiceCount' => count($byInvoiceId),
            'total' => $total,
        ];
    }

    private function purchaserUserIdForToken(Token $token, string $tokenEmail): ?string
    {
        $tokenUserId = trim((string) ($token->user_id ?? ''));
        if ($tokenUserId === '' || $tokenEmail === '') {
            return null;
        }

        $tokenUserEmail = strtolower(trim((string) User::query()
            ->whereKey($tokenUserId)
            ->value('email')));

        if ($tokenUserEmail === '' || $tokenUserEmail !== $tokenEmail) {
            return null;
        }

        return $tokenUserId;
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
}
