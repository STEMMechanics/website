<?php

namespace App\Http\Controllers;

use App\Jobs\SendWorkshopTicketOrderEmail;
use App\Jobs\SendEmail;
use App\Mail\UserRegister;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\Token;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use App\Providers\QRCodeProvider;
use App\Services\DocumentNumberService;
use App\Services\AccountCreditService;
use App\Services\StoreCouponService;
use App\Services\SquareApiService;
use App\Services\WorkshopRegistrationGroupService;
use App\Services\WorkshopTicketOrderEmailService;
use App\Services\WorkshopTicketService;
use App\Support\InvoiceDueDate;
use App\Support\AltchaTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use GrantHolle\Altcha\Rules\ValidAltcha;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use RuntimeException;
use Throwable;
use ZipArchive;

class WorkshopTicketFlowController extends Controller
{
    private const SESSION_KEY_PREFIX = 'ticket_checkout_flow.';

    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly WorkshopRegistrationGroupService $workshopRegistrationGroups,
        private readonly AccountCreditService $accountCredit,
        private readonly StoreCouponService $coupons
    )
    {
    }

    public function start(Workshop $workshop, WorkshopTicketService $ticketService): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $ticketService->cleanupExpiredHolds($workshop);
        if (! $ticketService->canStartTicketCheckout($workshop)) {
            session()->flash('message', $workshop->usesClassroomRegistration()
                ? 'Classroom access is not available for this workshop.'
                : 'Tickets are not available for this workshop.');
            session()->flash('message-title', 'Unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->route('workshop.show', $workshop);
        }

        return view('workshop.tickets.start', [
            'workshop' => $workshop,
            'availableTickets' => $ticketService->availableTickets($workshop),
            'ticketPriceAmount' => $ticketService->ticketPriceAmount($workshop),
            'prefill' => $this->defaultPurchaserData(),
            'requiresPrivateCode' => $workshop->requiresPrivateTicketCode(),
        ]);
    }

    public function begin(
        Request $request,
        Workshop $workshop,
        WorkshopTicketService $ticketService
    ): RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $ticketService->cleanupExpiredHolds($workshop);
        if (! $ticketService->canStartTicketCheckout($workshop)) {
            throw ValidationException::withMessages([
                'quantity' => $workshop->usesClassroomRegistration()
                    ? 'Classroom access is no longer available for this workshop.'
                    : 'Tickets are no longer available for this workshop.',
            ]);
        }

        $requiresPrivateCode = $workshop->requiresPrivateTicketCode();

        $rules = [
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'firstname' => ['required', 'string', 'max:120'],
            'surname' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:60'],
            'private_code' => [$requiresPrivateCode ? 'required' : 'nullable', 'string', 'max:120'],
        ];
        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha()];
        }

        $validated = $request->validate($rules);
        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        if (! $workshop->matchesPrivateTicketCode($validated['private_code'] ?? null)) {
            throw ValidationException::withMessages([
                'private_code' => 'The access code is incorrect.',
            ]);
        }

        $available = $ticketService->availableTickets($workshop);
        if ($available !== null && (int) $validated['quantity'] > $available) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.$available.' tickets are available right now.',
            ]);
        }

        $purchaser = [
            'firstname' => trim((string) ($validated['firstname'] ?? '')),
            'surname' => trim((string) ($validated['surname'] ?? '')),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone' => trim((string) ($validated['phone'] ?? '')),
        ];
        $purchaserUserId = $this->resolveCheckoutUserId($workshop, $purchaser);

        $earlyBirdFlags = $this->allocateEarlyBirdFlags($workshop, $ticketService, (int) $validated['quantity']);

        $holdIds = DB::transaction(function () use ($workshop, $ticketService, $validated, $purchaser, $purchaserUserId, $earlyBirdFlags) {
            $ticketService->cleanupExpiredHolds($workshop);
            $available = $ticketService->availableTickets($workshop);
            if ($available !== null && (int) $validated['quantity'] > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough tickets available.',
                ]);
            }

            $ids = [];
            for ($i = 0; $i < (int) $validated['quantity']; $i++) {
                $ticket = new Ticket();
                $ticket->workshop_id = $workshop->id;
                $ticket->user_id = $purchaserUserId;
                $ticket->status = Ticket::STATUS_HOLD;
                $ticket->firstname = $purchaser['firstname'];
                $ticket->surname = $purchaser['surname'];
                $ticket->email = $purchaser['email'];
                $ticket->phone = $purchaser['phone'];
                $ticket->is_early_bird = $earlyBirdFlags[$i] ?? false;
                $ticket->save();
                $ticket->ensureReferenceCode();
                $ids[] = $ticket->id;
            }

            return $ids;
        });
        $ticketService->syncManagedTicketStatus($workshop);

        $sessionPayload = [
            'hold_ids' => $holdIds,
            'expires_at' => now()->addMinutes($ticketService->holdWindowMinutes())->toIso8601String(),
            'purchaser' => $purchaser,
            'purchaser_user_id' => $purchaserUserId,
            'voucher_code' => null,
            'invoice_id' => null,
            'email_delivery_id' => null,
            'payment_method' => null,
            'payment_complete' => false,
        ];
        $this->putFlowSession($workshop, $sessionPayload);

        if ($ticketService->ticketPriceAmount($workshop) <= 0.0001) {
            return $this->completeFreeCheckout($workshop, $sessionPayload, $ticketService);
        }

        return redirect()->route('workshop.ticket.flow.payment', $workshop);
    }

    public function payment(Workshop $workshop, WorkshopTicketService $ticketService): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        if (($session['payment_complete'] ?? false) === true) {
            return redirect()->route('workshop.ticket.flow.details', $workshop);
        }

        if ($this->holdsExpired($workshop, $session['hold_ids'] ?? [], $ticketService)) {
            $this->clearFlowSession($workshop);
            session()->flash('message', $workshop->usesClassroomRegistration()
                ? 'Your classroom access hold expired. Please start again.'
                : 'Your ticket hold expired. Please start again.');
            session()->flash('message-title', 'Hold expired');
            session()->flash('message-type', 'warning');

            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $checkoutTotals = $this->resolveTicketCheckoutTotals($workshop, $ticketService, $session, true);
        $session = $checkoutTotals['session'];
        $accountCreditAvailable = $checkoutTotals['account_credit_available'];
        $accountTermsDays = $checkoutTotals['account_terms_days'];
        $accountCreditLoginHint = $checkoutTotals['account_credit_login_hint'];
        $applyAccountCreditDefault = $checkoutTotals['apply_account_credit_default'];
        $creditDebug = $checkoutTotals['credit_debug'];

        return view('workshop.tickets.payment', [
            'workshop' => $workshop,
            'session' => $session,
            'holdCount' => $checkoutTotals['hold_count'],
            'ticketPricing' => $checkoutTotals['ticket_pricing'],
            'ticketPriceAmount' => $checkoutTotals['ticket_price_amount'],
            'totalAmount' => $checkoutTotals['total_amount'],
            'voucherCode' => $checkoutTotals['voucher_code'],
            'voucherDiscountAmount' => $checkoutTotals['voucher_discount_amount'],
            'voucherButtonLabel' => $checkoutTotals['voucher_code'] !== '' ? 'Change voucher' : 'Add voucher',
            'accountCreditAvailable' => $accountCreditAvailable,
            'accountTermsDays' => $accountTermsDays,
            'canUseAccountTerms' => $accountTermsDays > 0,
            'accountTermsLabel' => $this->accountTermsLabel($accountTermsDays),
            'accountCreditLoginHint' => $accountCreditLoginHint,
            'applyAccountCreditDefault' => $applyAccountCreditDefault,
            'creditDebug' => $creditDebug,
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
            'bankTransferNotice' => $this->bankTransferMethodNotice(),
            'payAtDoorNotice' => $this->payAtDoorMethodNotice(),
        ]);
    }

    public function processPayment(
        Request $request,
        Workshop $workshop,
        WorkshopTicketService $ticketService,
        SquareApiService $squareApi
    ): RedirectResponse {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        if ($this->holdsExpired($workshop, $session['hold_ids'] ?? [], $ticketService)) {
            $this->clearFlowSession($workshop);
            session()->flash('message', $workshop->usesClassroomRegistration()
                ? 'Your classroom access hold expired. Please start again.'
                : 'Your ticket hold expired. Please start again.');
            session()->flash('message-title', 'Hold expired');
            session()->flash('message-type', 'warning');

            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $allowedPaymentMethods = ['pay_at_door', 'bank_transfer', 'credit_card', 'credit'];
        $accountTermsDays = $this->checkoutAccountTermsDays();
        if ($accountTermsDays > 0) {
            $allowedPaymentMethods[] = 'account_terms';
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in($allowedPaymentMethods)],
            'source_id' => ['nullable', 'string', 'max:255'],
            'apply_account_credit' => ['nullable', 'boolean'],
        ]);

        $checkoutTotals = $this->resolveTicketCheckoutTotals($workshop, $ticketService, $session, false);
        if (($checkoutTotals['voucher_error'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'voucher_code' => (string) $checkoutTotals['voucher_error'],
            ]);
        }

        $holdIds = $checkoutTotals['hold_ids'];
        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $holdIds)
            ->orderBy('id')
            ->get();
        $tickets->each(fn (Ticket $ticket) => $ticket->ensureReferenceCode());
        $amount = $checkoutTotals['total_amount'];
        $creditUser = $checkoutTotals['credit_user'];
        $accountCreditAvailable = $checkoutTotals['account_credit_available'];
        if ($amount <= 0.0001) {
            $session = $checkoutTotals['session'];

            return $this->completeFreeCheckout($workshop, $session, $ticketService);
        }

        $applyAccountCredit = $request->boolean('apply_account_credit');
        $amountDueAfterCredit = $applyAccountCredit
            ? max(0, round($amount - $accountCreditAvailable, 2))
            : $amount;
        $paymentMethod = $amount <= 0 ? 'free' : (string) $validated['payment_method'];
        if ($paymentMethod === 'account_terms' && $accountTermsDays <= 0) {
            throw ValidationException::withMessages([
                'payment_method' => 'Account terms are not available for this account.',
            ]);
        }

        if ($paymentMethod === 'credit_card' && $amountDueAfterCredit > 0.0001 && trim((string) ($validated['source_id'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Card details are required for credit card payment.',
            ]);
        }
        if ($paymentMethod === 'credit' && $amountDueAfterCredit > 0.0001) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your account credit does not cover this ticket order.',
            ]);
        }

        $result = DB::transaction(function () use ($workshop, $ticketService, $holdIds, $paymentMethod, $amount, $validated, $squareApi, $session, $accountCreditAvailable, $applyAccountCredit, $creditUser, $accountTermsDays, $checkoutTotals) {
            $holds = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $holdIds)
                ->where('status', Ticket::STATUS_HOLD)
                ->where('created_at', '>=', now()->subMinutes($ticketService->holdWindowMinutes()))
                ->lockForUpdate()
                ->get();

            if ($holds->count() !== count($holdIds)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'One or more held tickets expired. Please restart checkout.',
                ]);
            }

            $purchaserUserId = trim((string) ($session['purchaser_user_id'] ?? ''));
            if ($purchaserUserId === '') {
                $purchaserUserId = $this->resolveCheckoutUserId($workshop, $session['purchaser'] ?? []);
            }

            $invoice = null;
            $creditApplied = 0.0;
            if ($amount > 0) {
                $invoice = $this->createTicketInvoice(
                    $workshop,
                    $holds,
                    $session['purchaser'] ?? [],
                    $purchaserUserId !== '' ? $purchaserUserId : null,
                    $paymentMethod === 'account_terms' ? $accountTermsDays : null,
                    $checkoutTotals['voucher_code'] !== '' ? $checkoutTotals['voucher_code'] : null,
                    (float) ($checkoutTotals['voucher_discount_amount'] ?? 0)
                );
                $creditApplied = $creditUser instanceof User && $applyAccountCredit && $accountCreditAvailable > 0.0001
                    ? $this->accountCredit->applyCreditToInvoice($invoice, $creditUser, (float) $invoice->outstandingAmount())
                    : 0.0;
                if ($creditApplied > 0.0001) {
                    $invoice->refresh();
                }
            }

            $remainingAmount = $invoice instanceof Invoice ? round((float) $invoice->outstandingAmount(), 2) : 0.0;
            if ($paymentMethod === 'credit' && $remainingAmount > 0.0001) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Your account credit does not cover this ticket order.',
                ]);
            }
            $effectivePaymentMethod = $remainingAmount <= 0.0001 ? 'credit' : $paymentMethod;

            $ticketStatus = match ($effectivePaymentMethod) {
                'pay_at_door' => Ticket::STATUS_PENDING_DOOR,
                'bank_transfer' => Ticket::STATUS_PENDING_XFER,
                'account_terms' => Ticket::STATUS_ACCOUNT,
                default => Ticket::STATUS_PAID,
            };

            if ($effectivePaymentMethod === 'credit_card' && $remainingAmount > 0.0001) {
                if (! $squareApi->isEnabled()) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Credit card payments are not available right now.',
                    ]);
                }

                $locationId = (string) config('services.square.location_id');
                if ($locationId === '') {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Square location is not configured.',
                    ]);
                }

                $customerPayment = new Payment();
                $customerPayment->user_id = $purchaserUserId !== '' ? $purchaserUserId : $this->checkoutAccountUserId();
                $customerPayment->created_by = $this->checkoutAccountUserId();
                $customerPayment->kind = Payment::KIND_PAYMENT;
                $customerPayment->received_on = now();
                $customerPayment->payment_method = 'credit_card';
                $ticketReferences = $holds
                    ->map(fn (Ticket $ticket) => $ticket->ensureReferenceCode())
                    ->values()
                    ->all();
                $customerPayment->reference = 'Workshop '.$workshop->title.' ticket' . (count($ticketReferences) > 1 ? 's' : '') . ' ['.implode(',', $ticketReferences).']';
                $customerPayment->total_amount = $remainingAmount;
                $customerPayment->gst_amount = 0;
                $customerPayment->notes = 'Workshop "'.$workshop->title.'" '.($workshop->usesClassroomRegistration() ? 'classroom access' : 'ticket').' purchase';
                $customerPayment->save();
                $amountCents = (int) round($remainingAmount * 100);

                try {
                $paymentResponse = $squareApi->createPayment([
                        'idempotency_key' => 'tkt-'.$workshop->id.'-pay-'.$customerPayment->id.'-amt-'.$amountCents,
                        'source_id' => (string) $validated['source_id'],
                        'location_id' => $locationId,
                        'reference_id' => 'payment:'.$customerPayment->id,
                        'amount_money' => [
                            'amount' => $amountCents,
                            'currency' => 'AUD',
                        ],
                        'autocomplete' => true,
                        'note' => 'Workshop tickets for '.$workshop->title,
                    ]);
                } catch (RuntimeException $e) {
                    report($e);
                    throw ValidationException::withMessages([
                        'payment_method' => $squareApi->userFacingPaymentErrorMessage($e->getMessage()),
                    ]);
                }

                $payment = (array) ($paymentResponse['payment'] ?? []);
                if ($payment === []) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Credit card payment failed with an invalid Square response.',
                    ]);
                }
                $squareStatus = strtoupper(trim((string) ($payment['status'] ?? 'UNKNOWN')));
                if ($squareStatus !== 'COMPLETED') {
                    $statusDetail = trim((string) ($payment['card_details']['status'] ?? ''));
                    $statusMessage = $statusDetail !== ''
                        ? 'Square status: '.$squareStatus.' (card: '.$statusDetail.')'
                        : 'Square status: '.$squareStatus;

                    throw ValidationException::withMessages([
                        'payment_method' => 'Credit card payment was not completed. '.$statusMessage,
                    ]);
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

                if ($invoice) {
                    $invoice->status = \App\Models\Invoice::STATUS_PAID;
                    $invoice->save();
                    $customerPayment->allocations()->create([
                        'invoice_id' => $invoice->id,
                        'allocated_amount' => $remainingAmount,
                    ]);

                }
            } elseif ($invoice && $remainingAmount > 0.0001) {
                $invoice->status = \App\Models\Invoice::STATUS_ISSUED;
                $invoice->save();
            } elseif ($invoice) {
                $invoice->status = \App\Models\Invoice::STATUS_PAID;
                $invoice->save();
            }

            $lineIdByTicketId = [];
            if ($invoice) {
                $lineIdByTicketId = $invoice->lines
                    ->mapWithKeys(function (InvoiceLine $line): array {
                        $ticketId = (int) data_get($line->details_json, 'ticket_id');
                        if ($ticketId <= 0) {
                            return [];
                        }

                        return [$ticketId => (int) $line->id];
                    })
                    ->all();
            }

            foreach ($holds as $ticket) {
                $ticket->status = $ticketStatus;
                if ($purchaserUserId !== '') {
                    $ticket->user_id = $purchaserUserId;
                }
                $ticket->invoice_id = $invoice?->id;
                $ticket->invoice_line_id = $lineIdByTicketId[(int) $ticket->id] ?? null;
                if ($ticket->is_early_bird === null) {
                    $ticket->is_early_bird = $workshop->earlyBirdIsActive();
                }
                $ticket->save();
            }

            return [
                'invoice_id' => $invoice?->id,
                'payment_method' => $effectivePaymentMethod,
                'payment_id' => isset($customerPayment) ? (int) $customerPayment->id : null,
                'credit_applied' => $creditApplied,
            ];
        });
        $ticketService->syncManagedTicketStatus($workshop);

        $session['invoice_id'] = $result['invoice_id'];
        $session['payment_method'] = $result['payment_method'];
        $session['payment_id'] = $result['payment_id'] ?? null;
        $session['credit_applied_amount'] = round((float) ($result['credit_applied'] ?? 0), 2);
        $session['payment_complete'] = true;
        $delivery = $this->createWorkshopTicketEmailDelivery(
            workshop: $workshop,
            ticketIds: $holdIds,
            invoiceId: $result['invoice_id'],
            paymentId: $result['payment_id'] ?? null,
            recipientEmail: strtolower(trim((string) ($session['purchaser']['email'] ?? ''))),
            recipientName: trim((string) (($session['purchaser']['firstname'] ?? '').' '.($session['purchaser']['surname'] ?? ''))),
            paymentMethod: (string) $result['payment_method'],
            amount: $amount
        );
        $session['email_delivery_id'] = $delivery->id;
        $this->putFlowSession($workshop, $session);

        $this->workshopRegistrationGroups->assignForTickets(
            Ticket::query()
                ->with('workshop')
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $holdIds)
                ->get(),
            trim((string) ($session['purchaser_user_id'] ?? '')) ?: null
        );

        SendWorkshopTicketOrderEmail::dispatch($delivery->id)
            ->delay(now()->addMinutes(30));

        return redirect()->route('workshop.ticket.flow.details', $workshop);
    }

    public function updateVoucher(
        Request $request,
        Workshop $workshop,
        WorkshopTicketService $ticketService
    ): JsonResponse|RedirectResponse {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session) {
            return $this->voucherSessionExpiredResponse($request, $workshop);
        }

        if ($this->holdsExpired($workshop, $session['hold_ids'] ?? [], $ticketService)) {
            $this->clearFlowSession($workshop);

            return $this->voucherSessionExpiredResponse($request, $workshop, $workshop->usesClassroomRegistration()
                ? 'Your classroom access hold expired. Please start again.'
                : 'Your ticket hold expired. Please start again.');
        }

        $validated = $request->validate([
            'voucher_code' => ['nullable', 'string', 'max:60'],
        ]);

        $voucherCode = trim((string) ($validated['voucher_code'] ?? ''));
        if ($voucherCode === '') {
            $session['voucher_code'] = null;
            $this->putFlowSession($workshop, $session);

            return $this->voucherResponse($request, $workshop, 'Voucher removed.', [
                'voucher_code' => null,
                'voucher_discount_amount' => 0.0,
            ]);
        }

        $ticketPriceAmount = $ticketService->ticketPriceAmount($workshop);
        $holdIds = collect($session['hold_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
        $subtotal = round($ticketPriceAmount * count($holdIds), 2);
        $creditUser = $this->checkoutCreditUser($session);
        $billingEmail = trim((string) data_get($session, 'purchaser.email', ''));
        $evaluation = $this->evaluateTicketVoucher($workshop, $voucherCode, $subtotal, $creditUser, $billingEmail !== '' ? $billingEmail : null);

        if (($evaluation['error'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'voucher_code' => (string) $evaluation['error'],
            ]);
        }

        $resolvedVoucherCode = trim((string) ($evaluation['coupon_code'] ?? ''));
        $session['voucher_code'] = $resolvedVoucherCode !== '' ? $resolvedVoucherCode : strtoupper($voucherCode);
        $this->putFlowSession($workshop, $session);

        return $this->voucherResponse($request, $workshop, 'Voucher applied successfully.', [
            'voucher_code' => $session['voucher_code'],
            'voucher_discount_amount' => round(min($subtotal, (float) ($evaluation['discount_amount'] ?? 0)), 2),
        ]);
    }

    private function completeFreeCheckout(
        Workshop $workshop,
        array $session,
        WorkshopTicketService $ticketService
    ): RedirectResponse {
        $holdIds = collect($session['hold_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();

        $result = DB::transaction(function () use ($workshop, $holdIds, $ticketService, $session): array {
            $holds = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $holdIds)
                ->where('status', Ticket::STATUS_HOLD)
                ->where('created_at', '>=', now()->subMinutes($ticketService->holdWindowMinutes()))
                ->lockForUpdate()
                ->get();

            if ($holds->count() !== count($holdIds)) {
                throw ValidationException::withMessages([
                    'quantity' => 'One or more held tickets expired. Please restart checkout.',
                ]);
            }

            $purchaserUserId = trim((string) ($session['purchaser_user_id'] ?? ''));
            if ($purchaserUserId === '') {
                $purchaserUserId = $this->resolveCheckoutUserId($workshop, $session['purchaser'] ?? []);
            }

            foreach ($holds as $ticket) {
                $ticket->status = Ticket::STATUS_PAID;
                if ($purchaserUserId !== '') {
                    $ticket->user_id = $purchaserUserId;
                }
                $ticket->invoice_id = null;
                $ticket->invoice_line_id = null;
                if ($ticket->is_early_bird === null) {
                    $ticket->is_early_bird = $workshop->earlyBirdIsActive();
                }
                $ticket->save();
            }

            return [
                'payment_method' => 'free',
                'payment_id' => null,
            ];
        });
        $ticketService->syncManagedTicketStatus($workshop);

        $session['invoice_id'] = null;
        $session['payment_method'] = $result['payment_method'];
        $session['payment_id'] = $result['payment_id'];
        $session['payment_complete'] = true;
        $delivery = $this->createWorkshopTicketEmailDelivery(
            workshop: $workshop,
            ticketIds: $holdIds,
            invoiceId: null,
            paymentId: null,
            recipientEmail: strtolower(trim((string) ($session['purchaser']['email'] ?? ''))),
            recipientName: trim((string) (($session['purchaser']['firstname'] ?? '').' '.($session['purchaser']['surname'] ?? ''))),
            paymentMethod: (string) $result['payment_method'],
            amount: 0.0
        );
        $session['email_delivery_id'] = $delivery->id;
        $this->putFlowSession($workshop, $session);

        $this->workshopRegistrationGroups->assignForTickets(
            Ticket::query()
                ->with('workshop')
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $holdIds)
                ->get(),
            trim((string) ($session['purchaser_user_id'] ?? '')) ?: null
        );

        SendWorkshopTicketOrderEmail::dispatch($delivery->id)
            ->delay(now()->addMinutes(30));

        return redirect()->route('workshop.ticket.flow.details', $workshop);
    }

    /**
     * @return array{
     *     session: array,
     *     hold_ids: array<int, int>,
     *     hold_count: int,
     *     ticket_price_amount: float,
     *     subtotal_amount: float,
     *     voucher_code: string,
     *     voucher_discount_amount: float,
     *     voucher_error: ?string,
     *     total_amount: float,
     *     credit_user: ?User,
     *     account_credit_available: float,
     *     account_credit_login_hint: bool,
     *     account_terms_days: int,
     *     apply_account_credit_default: bool,
     *     credit_debug: array<string, mixed>,
     *     ticket_pricing: array<string, mixed>
     * }
     */
    private function resolveTicketCheckoutTotals(
        Workshop $workshop,
        WorkshopTicketService $ticketService,
        array $session,
        bool $clearInvalidVoucher = false
    ): array {
        $holdIds = collect($session['hold_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $holdIds)
            ->orderBy('id')
            ->get();
        $tickets->each(fn (Ticket $ticket) => $ticket->ensureReferenceCode());
        $ticketPricing = $this->calculateTicketCheckoutPricing($workshop, $tickets);
        $ticketPriceAmount = (float) ($ticketPricing['standard_unit_price'] ?? $ticketService->ticketPriceAmount($workshop));
        $subtotal = round((float) ($ticketPricing['subtotal_amount'] ?? 0), 2);
        $creditUser = $this->checkoutCreditUser($session);
        $accountCreditAvailable = round($this->accountCredit->availableCreditForUser($creditUser), 2);
        $accountTermsDays = $this->checkoutAccountTermsDays();
        $accountCreditLoginHint = $accountCreditAvailable <= 0.0001 && $this->purchaserEmailHasCredit($session);
        $applyAccountCreditDefault = $accountCreditAvailable > 0.0001;
        $accountCreditDebugRows = $this->accountCreditDebugRows($creditUser);
        $creditDebug = [
            'source' => $creditUser instanceof User
                ? ($this->checkoutAccountUser() instanceof User ? 'auth' : 'session')
                : ($accountCreditLoginHint ? 'email-login-hint' : 'none'),
            'auth_user_id' => $this->checkoutAccountUser()?->id,
            'auth_user_email' => $this->checkoutAccountUser()?->email,
            'session_purchaser_user_id' => $session['purchaser_user_id'] ?? null,
            'session_purchaser_email' => data_get($session, 'purchaser.email'),
            'resolved_user_id' => $creditUser?->id,
            'resolved_user_email' => $creditUser?->email,
            'available_credit' => $accountCreditAvailable,
            'candidate_rows' => $accountCreditDebugRows,
        ];

        $voucherCode = $this->ticketVoucherCode($session);
        $billingEmail = trim((string) data_get($session, 'purchaser.email', ''));
        $voucherEvaluation = $this->evaluateTicketVoucher(
            $workshop,
            $voucherCode !== '' ? $voucherCode : null,
            $subtotal,
            $creditUser,
            $billingEmail !== '' ? $billingEmail : null
        );
        $voucherError = $voucherEvaluation['error'] ?? null;
        if ($clearInvalidVoucher && $voucherError !== null && $voucherCode !== '') {
            $session['voucher_code'] = null;
            $this->putFlowSession($workshop, $session);
            session()->flash('message', (string) $voucherError);
            session()->flash('message-title', 'Voucher removed');
            session()->flash('message-type', 'warning');

            $voucherCode = '';
            $voucherEvaluation = $this->evaluateTicketVoucher($workshop, null, $subtotal, $creditUser, $billingEmail !== '' ? $billingEmail : null);
            $voucherError = null;
        }

        $voucherDiscountAmount = round(min($subtotal, (float) ($voucherEvaluation['discount_amount'] ?? 0)), 2);
        $totalAmount = round(max(0, $subtotal - $voucherDiscountAmount), 2);

        return [
            'session' => $session,
            'hold_ids' => $holdIds,
            'hold_count' => count($holdIds),
            'ticket_price_amount' => $ticketPriceAmount,
            'subtotal_amount' => $subtotal,
            'voucher_code' => $voucherError !== null ? trim((string) $voucherCode) : trim((string) ($voucherEvaluation['coupon_code'] ?? $voucherCode)),
            'voucher_discount_amount' => $voucherDiscountAmount,
            'voucher_error' => $voucherError,
            'total_amount' => $totalAmount,
            'credit_user' => $creditUser,
            'account_credit_available' => $accountCreditAvailable,
            'account_credit_login_hint' => $accountCreditLoginHint,
            'account_terms_days' => $accountTermsDays,
            'apply_account_credit_default' => $applyAccountCreditDefault,
            'credit_debug' => $creditDebug,
            'ticket_pricing' => $ticketPricing,
        ];
    }

    /**
     * @param  iterable<int, Ticket>  $tickets
     * @return array<string, mixed>
     */
    private function calculateTicketCheckoutPricing(Workshop $workshop, iterable $tickets): array
    {
        $baseUnitPrice = round($workshop->baseTicketPriceAmount(), 2);
        $earlyBirdUnitPrice = $workshop->earlyBirdPriceAmount();
        $earlyBirdUnitPrice = $earlyBirdUnitPrice !== null ? round($earlyBirdUnitPrice, 2) : null;

        $ticketItems = [];
        $subtotal = 0.0;
        $earlyBirdCount = 0;
        $standardCount = 0;

        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }

            $isEarlyBird = $ticket->isEarlyBirdTicket() && $earlyBirdUnitPrice !== null;
            $unitPrice = $isEarlyBird ? $earlyBirdUnitPrice : $baseUnitPrice;
            $subtotal = round($subtotal + (float) $unitPrice, 2);

            if ($isEarlyBird) {
                $earlyBirdCount++;
            } else {
                $standardCount++;
            }

            $ticketItems[] = [
                'ticket_id' => (int) $ticket->id,
                'ticket_reference' => $ticket->ensureReferenceCode(),
                'is_early_bird' => $isEarlyBird,
                'unit_price' => (float) $unitPrice,
            ];
        }

        $groupedItems = [];
        foreach ($ticketItems as $item) {
            $key = $item['is_early_bird'] ? 'early_bird' : 'standard';
            if (! isset($groupedItems[$key])) {
                $groupedItems[$key] = [
                    'label' => $item['is_early_bird'] ? 'Early Bird' : 'Standard',
                    'count' => 0,
                    'unit_price' => $item['unit_price'],
                    'amount' => 0.0,
                    'is_early_bird' => $item['is_early_bird'],
                ];
            }

            $groupedItems[$key]['count']++;
            $groupedItems[$key]['amount'] = round($groupedItems[$key]['amount'] + $item['unit_price'], 2);
        }

        return [
            'ticket_count' => count($ticketItems),
            'early_bird_count' => $earlyBirdCount,
            'standard_count' => $standardCount,
            'early_bird_unit_price' => $earlyBirdUnitPrice,
            'standard_unit_price' => $baseUnitPrice,
            'subtotal_amount' => $subtotal,
            'items' => array_values($groupedItems),
        ];
    }

    /**
     * @return array<int, bool>
     */
    private function allocateEarlyBirdFlags(
        Workshop $workshop,
        WorkshopTicketService $ticketService,
        int $quantity
    ): array {
        $remaining = $this->earlyBirdSlotsRemainingForCheckout($workshop, $ticketService);
        if ($remaining === 0 || $quantity <= 0) {
            return array_fill(0, max(0, $quantity), false);
        }

        if ($remaining === null) {
            return array_fill(0, $quantity, true);
        }

        $earlyBirdCount = min($quantity, $remaining);
        $flags = [];
        for ($i = 0; $i < $quantity; $i++) {
            $flags[] = $i < $earlyBirdCount;
        }

        return $flags;
    }

    private function earlyBirdSlotsRemainingForCheckout(
        Workshop $workshop,
        WorkshopTicketService $ticketService
    ): ?int {
        if (! $workshop->hasEarlyBirdOffer()) {
            return 0;
        }

        if ($workshop->early_bird_ends_at !== null && Carbon::parse($workshop->early_bird_ends_at)->isPast()) {
            return 0;
        }

        $limit = $workshop->earlyBirdTicketLimit();
        if ($limit === null) {
            return null;
        }

        $threshold = now()->subMinutes($ticketService->holdWindowMinutes());
        $reserved = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->where('is_early_bird', true)
            ->where(function ($builder) use ($threshold) {
                $builder->whereIn('status', Ticket::activePurchasedStatuses())
                    ->orWhere(function ($holdQuery) use ($threshold) {
                        $holdQuery->where('status', Ticket::STATUS_HOLD)
                            ->where('created_at', '>=', $threshold);
                    });
            })
            ->count();

        return max(0, $limit - $reserved);
    }

    private function ticketVoucherCode(array $session): string
    {
        $voucherCode = trim((string) ($session['voucher_code'] ?? ''));

        return $voucherCode !== '' ? strtoupper($voucherCode) : '';
    }

    private function evaluateTicketVoucher(
        Workshop $workshop,
        ?string $voucherCode,
        float $subtotal,
        ?User $user = null,
        ?string $billingEmail = null
    ): array {
        $evaluation = $this->coupons->evaluate(
            $voucherCode,
            $subtotal,
            0,
            $user,
            $billingEmail,
            Coupon::CHECKOUT_CONTEXT_WORKSHOPS,
            [
                'workshop_id' => (string) $workshop->id,
            ]
        );

        if (($evaluation['error'] ?? null) !== null) {
            return $evaluation;
        }

        if ((string) ($evaluation['discount_type'] ?? '') === Coupon::DISCOUNT_TYPE_FREE_SHIPPING) {
            return [
                'coupon' => $evaluation['coupon'] ?? null,
                'coupon_code' => null,
                'discount_type' => Coupon::DISCOUNT_TYPE_FREE_SHIPPING,
                'discount_amount' => 0.0,
                'error' => 'That voucher cannot be used for ticket checkout.',
            ];
        }

        return $evaluation;
    }

    private function voucherResponse(
        Request $request,
        Workshop $workshop,
        string $message,
        array $summary = []
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'summary' => $summary,
            ]);
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Voucher updated');
        session()->flash('message-type', 'success');

        return redirect()->route('workshop.ticket.flow.payment', $workshop);
    }

    private function voucherSessionExpiredResponse(
        Request $request,
        Workshop $workshop,
        ?string $message = null
    ): JsonResponse|RedirectResponse {
        $message ??= $workshop->usesClassroomRegistration()
            ? 'Your classroom access hold expired. Please start again.'
            : 'Your ticket hold expired. Please start again.';

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect_url' => route('workshop.ticket.flow.start', $workshop),
            ], 410);
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Hold expired');
        session()->flash('message-type', 'warning');

        return redirect()->route('workshop.ticket.flow.start', $workshop);
    }

    public function details(Workshop $workshop): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['payment_complete'] ?? false)) {
            return $this->redirectToWorkshopWithCheckoutExpiredToast($workshop);
        }

        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $session['hold_ids'] ?? [])
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->orderBy('id')
            ->get();

        if ($tickets->isEmpty()) {
            $this->clearFlowSession($workshop);
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $invoice = isset($session['invoice_id']) ? Invoice::query()->find($session['invoice_id']) : null;
        $ticketPricing = $this->calculateTicketCheckoutPricing($workshop, $tickets);

        return view('workshop.tickets.details', [
            'workshop' => $workshop,
            'session' => $session,
            'tickets' => $tickets,
            'ticketPricing' => $ticketPricing,
            'bankTransferDetails' => (string) ($session['payment_method'] ?? '') === 'bank_transfer'
                ? $this->bankTransferDetails($invoice)
                : null,
        ]);
    }

    public function detailsKeepAlive(Workshop $workshop): JsonResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['payment_complete'] ?? false) || (bool) ($session['details_complete'] ?? false)) {
            return response()->json([
                'ok' => false,
                'reason' => 'checkout_session_missing',
            ], 410);
        }

        $holdIds = collect($session['hold_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
        if ($holdIds === []) {
            return response()->json([
                'ok' => false,
                'reason' => 'checkout_tickets_missing',
            ], 410);
        }

        $activeCount = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $holdIds)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->count();

        if ($activeCount < 1) {
            return response()->json([
                'ok' => false,
                'reason' => 'checkout_tickets_inactive',
            ], 410);
        }

        $session['ping_at'] = now()->timestamp;
        $this->putFlowSession($workshop, $session);

        return response()->json(['ok' => true]);
    }

    public function saveDetails(
        Request $request,
        Workshop $workshop,
        WorkshopTicketOrderEmailService $emailService
    ): RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['payment_complete'] ?? false)) {
            return $this->redirectToWorkshopWithCheckoutExpiredToast($workshop);
        }

        $validated = $request->validate([
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.id' => ['required', 'integer'],
            'tickets.*.firstname' => ['required', 'string', 'max:120'],
            'tickets.*.surname' => ['required', 'string', 'max:120'],
            'tickets.*.email' => ['required', 'email', 'max:255'],
            'tickets.*.phone' => ['required', 'string', 'max:60'],
        ]);

        $ticketMap = collect($validated['tickets'])->keyBy(fn ($ticket) => (int) $ticket['id']);
        $updatedHoldIds = [];

        DB::transaction(function () use ($workshop, $session, $ticketMap, &$updatedHoldIds) {
            $tickets = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $session['hold_ids'] ?? [])
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->lockForUpdate()
                ->get();

            foreach ($tickets as $ticket) {
                $details = (array) $ticketMap->get((int) $ticket->id, []);
                if ($details === []) {
                    throw ValidationException::withMessages([
                        'tickets' => 'Ticket holder details were missing for one or more tickets.',
                    ]);
                }

                $ticket->firstname = trim((string) ($details['firstname'] ?? ''));
                $ticket->surname = trim((string) ($details['surname'] ?? ''));
                $ticket->email = strtolower(trim((string) ($details['email'] ?? '')));
                $ticket->phone = trim((string) ($details['phone'] ?? ''));
                $ticket->save();

                $updatedHoldIds[] = (int) $ticket->id;
            }
        });

        if ($updatedHoldIds !== []) {
            $session['hold_ids'] = $updatedHoldIds;
        }
        $session['details_complete'] = true;
        $this->putFlowSession($workshop, $session);

        try {
            $invoice = isset($session['invoice_id']) ? Invoice::query()->find((int) $session['invoice_id']) : null;
            $payment = isset($session['payment_id']) ? Payment::query()->find((int) $session['payment_id']) : null;
            $orderAmount = (float) (($payment->total_amount ?? 0) ?: ($invoice->total_amount ?? 0));
            $delivery = null;
            $deliveryId = (int) ($session['email_delivery_id'] ?? 0);
            if ($deliveryId > 0) {
                $delivery = WorkshopTicketEmail::query()->find($deliveryId);
            }

            if (! $delivery instanceof WorkshopTicketEmail) {
                $delivery = $this->createWorkshopTicketEmailDelivery(
                    workshop: $workshop,
                    ticketIds: $updatedHoldIds,
                    invoiceId: $invoice?->id ? (int) $invoice->id : null,
                    paymentId: $payment?->id ? (int) $payment->id : null,
                    recipientEmail: strtolower(trim((string) ($session['purchaser']['email'] ?? ''))),
                    recipientName: trim((string) (($session['purchaser']['firstname'] ?? '').' '.($session['purchaser']['surname'] ?? ''))),
                    paymentMethod: (string) ($session['payment_method'] ?? 'free'),
                    amount: $orderAmount
                );
                $session['email_delivery_id'] = $delivery->id;
                $this->putFlowSession($workshop, $session);
            }

            $emailService->queueCombinedEmail($delivery);
        } catch (Throwable $e) {
            report($e);
            session()->flash(
                'message',
                'We could not queue the combined ticket email right now. It will be retried automatically.'
            );
            session()->flash('message-title', 'Email not queued');
            session()->flash('message-type', 'warning');
        }

        return redirect()->route('workshop.ticket.flow.complete', $workshop);
    }

    public function complete(Workshop $workshop): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['details_complete'] ?? false)) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $session['hold_ids'] ?? [])
            ->orderBy('id')
            ->get();
        $tickets->each(fn (Ticket $ticket) => $ticket->ensureReferenceCode());
        $invoice = isset($session['invoice_id']) ? Invoice::query()->find($session['invoice_id']) : null;
        $payment = isset($session['payment_id']) ? Payment::query()->find((int) $session['payment_id']) : null;
        $ticketPricing = $this->calculateTicketCheckoutPricing($workshop, $tickets);
        $sentToEmail = trim((string) ($session['purchaser']['email'] ?? $tickets->first()->email ?? ''));
        $accessToken = null;

        if ($sentToEmail !== '') {
            $tokenUserId = trim((string) ($session['purchaser_user_id'] ?? $tickets->first()->user_id ?? $this->checkoutAccountUserId()));
            if ($tokenUserId !== '') {
                $token = Token::create([
                    'user_id' => $tokenUserId,
                    'type' => 'tickets-access',
                    'data' => ['email' => strtolower($sentToEmail)],
                    'expires_at' => now()->addDays(7),
                ]);
                $accessToken = (string) $token->id;
            }
        }

        return view('workshop.tickets.complete', [
            'workshop' => $workshop,
            'tickets' => $tickets,
            'invoice' => $invoice,
            'payment' => $payment,
            'session' => $session,
            'sentToEmail' => $sentToEmail,
            'accessToken' => $accessToken,
            'ticketPricing' => $ticketPricing,
        ]);
    }

    public function downloadAll(Workshop $workshop)
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['details_complete'] ?? false)) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $session['hold_ids'] ?? [])
            ->orderBy('id')
            ->get();
        $tickets->each(fn (Ticket $ticket) => $ticket->ensureReferenceCode());
        if ($tickets->isEmpty()) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $invoice = isset($session['invoice_id']) ? Invoice::query()->find($session['invoice_id']) : null;
        $payment = isset($session['payment_id']) ? Payment::query()->find((int) $session['payment_id']) : null;

        $zipPath = tempnam(sys_get_temp_dir(), 'ticket-docs-');
        if ($zipPath === false) {
            abort(500, 'Unable to prepare download archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Unable to create download archive.');
        }

        if (! $workshop->usesClassroomRegistration()) {
            foreach ($tickets as $ticket) {
                $ticketPdf = $this->buildTicketPdfBinary($ticket);
                if ($ticketPdf !== null) {
                    $zip->addFromString($this->ticketPdfFilename($ticket), $ticketPdf);
                }
            }
        }

        if ($invoice instanceof Invoice) {
            $invoicePdf = $this->buildInvoicePdfBinary($invoice);
            if ($invoicePdf !== null) {
                $zip->addFromString($this->invoicePdfFilename($invoice), $invoicePdf);
            }
        }

        if ($invoice instanceof Invoice && $payment instanceof Payment) {
            $receiptPdf = $this->buildPaymentReceiptPdfBinary($invoice, $payment);
            if ($receiptPdf !== null) {
                $zip->addFromString(($payment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').((int) $payment->id).'.pdf', $receiptPdf);
            }
        }

        $zip->close();

        $archiveName = 'workshop-'.$workshop->id.'-documents.zip';

        return response()->download($zipPath, $archiveName)->deleteFileAfterSend(true);
    }

    public function cancel(Workshop $workshop, WorkshopTicketService $ticketService): RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if ($session && !($session['payment_complete'] ?? false)) {
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $session['hold_ids'] ?? [])
                ->where('status', Ticket::STATUS_HOLD)
                ->delete();
        }
        $ticketService->syncManagedTicketStatus($workshop);
        $this->clearFlowSession($workshop);

        return redirect()->route('workshop.show', $workshop);
    }

    public function loginRedirect(Workshop $workshop): RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        session()->put('url.intended', route('workshop.ticket.flow.start', $workshop));

        return redirect()->route('login');
    }

    private function ensureWorkshopPubliclyVisible(Workshop $workshop): void
    {
        if ((bool) (auth()->user()?->isAdmin() ?? false)) {
            return;
        }

        if (! $workshop->isPubliclyVisible()) {
            abort(404);
        }
    }

    private function createTicketInvoice(
        Workshop $workshop,
        $holds,
        array $purchaser,
        ?string $purchaserUserId = null,
        ?int $termDays = null,
        ?string $voucherCode = null,
        float $voucherAmount = 0.0
    ): Invoice
    {
        $tickets = collect($holds)->values();
        $quantity = max(1, $tickets->count());
        $linePrices = $tickets->map(fn (Ticket $ticket): float => $this->ticketCheckoutUnitPrice($workshop, $ticket))->values();
        $subtotalAmount = round($linePrices->sum(), 2);
        $voucherAmount = round(min($subtotalAmount, max(0, $voucherAmount)), 2);
        $totalAmount = round(max(0, $subtotalAmount - $voucherAmount), 2);

        $invoice = new Invoice();
        $effectiveStartsAt = $workshop->effectiveStartsAt();
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->user_id = $purchaserUserId ?: $this->checkoutAccountUserId();
        $invoice->billing_name = trim((string) (($purchaser['firstname'] ?? '').' '.($purchaser['surname'] ?? '')));
        $invoice->billing_email = trim((string) ($purchaser['email'] ?? ''));
        $invoice->billing_phone = trim((string) ($purchaser['phone'] ?? ''));
        $invoice->status = 'draft';
        $invoice->issue_date = Carbon::today();
        $invoice->due_date = $termDays !== null && $termDays > 0
            ? InvoiceDueDate::fromIssueDate($invoice->issue_date, $termDays)
            : ($effectiveStartsAt
                ? Carbon::instance($effectiveStartsAt)->startOfDay()
                : Carbon::today());
        $invoice->subtotal_amount = 0;
        $invoice->gst_amount = 0;
        $invoice->total_amount = $totalAmount;
        $invoice->notes = trim(implode("\n", [
            'Generated from public ticket checkout for workshop: '.$workshop->title,
            'Purchaser: '.trim(($purchaser['firstname'] ?? '').' '.($purchaser['surname'] ?? '')),
            'Purchaser email: '.($purchaser['email'] ?? ''),
            'Purchaser phone: '.($purchaser['phone'] ?? ''),
        ]));
        $invoice->save();

        $lines = collect();
        $subtotal = 0.0;
        $gst = 0.0;

        foreach ($tickets as $index => $ticket) {
            $lineTotalInc = round((float) ($linePrices[$index] ?? $this->ticketCheckoutUnitPrice($workshop, $ticket)), 2);
            $lineTotalEx = round($lineTotalInc / 1.1, 2);
            $taxAmount = round($lineTotalInc - $lineTotalEx, 2);
            $ticketReference = $ticket->ensureReferenceCode();

            $line = new InvoiceLine();
            $line->invoice_id = $invoice->id;
            $line->line_number = $index + 1;
            $line->kind = 'ticket';
            $line->description = $workshop->title.' - Ticket '.$ticketReference;
            $line->notes = $workshop->ticketInvoiceLineNotes($ticket);
            $line->details_json = [
                'ticket_id' => (int) $ticket->id,
                'ticket_reference' => $ticketReference,
                'workshop_id' => $workshop->id,
                'workshop_title' => $workshop->title,
            ];
            $line->quantity = 1;
            $line->unit_price_ex_tax = $lineTotalEx;
            $line->tax_rate = 0.10;
            $line->line_total_ex_tax = $lineTotalEx;
            $line->tax_amount = $taxAmount;
            $line->line_total_inc_tax = $lineTotalInc;
            // Workshop IDs are string-based; keep linkage in details_json for ticket lines.
            $line->source_type = null;
            $line->source_id = null;
            $line->save();

            $subtotal += $lineTotalEx;
            $gst += $taxAmount;
            $lines->push($line);
        }

        if ($voucherAmount > 0.0001) {
            $voucherExTax = round($voucherAmount / 1.1, 2);
            $voucherTax = round($voucherAmount - $voucherExTax, 2);

            $discountLine = new InvoiceLine();
            $discountLine->invoice_id = $invoice->id;
            $discountLine->line_number = $lines->count() + 1;
            $discountLine->kind = 'discount';
            $discountLine->description = 'Voucher'.(trim((string) $voucherCode) !== '' ? ' '.trim((string) $voucherCode) : '');
            $discountLine->notes = trim(implode("\n", [
                'Applied to workshop ticket checkout for: '.$workshop->title,
            ]));
            $discountLine->details_json = [
                'voucher_code' => trim((string) $voucherCode) !== '' ? trim((string) $voucherCode) : null,
                'workshop_id' => $workshop->id,
                'workshop_title' => $workshop->title,
            ];
            $discountLine->quantity = 1;
            $discountLine->unit_price_ex_tax = -1 * $voucherExTax;
            $discountLine->tax_rate = 0.10;
            $discountLine->line_total_ex_tax = -1 * $voucherExTax;
            $discountLine->tax_amount = -1 * $voucherTax;
            $discountLine->line_total_inc_tax = -1 * $voucherAmount;
            $discountLine->source_type = null;
            $discountLine->source_id = null;
            $discountLine->save();

            $subtotal = round($subtotal - $voucherExTax, 2);
            $gst = round($gst - $voucherTax, 2);
            $lines->push($discountLine);
        }

        $invoice->subtotal_amount = round($subtotal, 2);
        $invoice->gst_amount = round($gst, 2);
        $invoice->total_amount = round($invoice->subtotal_amount + $invoice->gst_amount, 2);
        $invoice->save();

        $invoice->setRelation('lines', $lines);

        return $invoice;
    }

    private function ticketCheckoutUnitPrice(Workshop $workshop, Ticket $ticket): float
    {
        $baseUnitPrice = round($workshop->baseTicketPriceAmount(), 2);
        if (! $ticket->isEarlyBirdTicket()) {
            return $baseUnitPrice;
        }

        $earlyBirdUnitPrice = $workshop->earlyBirdPriceAmount();
        if ($earlyBirdUnitPrice === null) {
            return $baseUnitPrice;
        }

        return round($earlyBirdUnitPrice, 2);
    }

    private function bankTransferMethodNotice(): ?string
    {
        $value = trim((string) SiteOption::value('checkout.bank-transfer-notice'));

        return $value !== '' ? $value : null;
    }

    private function payAtDoorMethodNotice(): ?string
    {
        $value = trim((string) SiteOption::value('checkout.pay-at-door-notice'));

        return $value !== '' ? $value : null;
    }

    private function bankTransferDetails(?Invoice $invoice = null): ?array
    {
        $accountName = trim((string) SiteOption::value('payments.bank-account-name'));
        $bsb = trim((string) SiteOption::value('payments.bank-bsb'));
        $accountNumber = trim((string) SiteOption::value('payments.bank-account-number'));
        $reference = trim((string) ($invoice !== null ? $invoice->invoice_number : ''));

        if ($accountName === '' || $bsb === '' || $accountNumber === '' || $reference === '') {
            return null;
        }

        return [
            'account_name' => $accountName,
            'bsb' => $bsb,
            'account_number' => $accountNumber,
            'reference' => $reference,
        ];
    }

    private function resolveCheckoutUserId(Workshop $workshop, array $purchaser): ?string
    {
        $checkoutUser = $this->checkoutAccountUser();
        if ($checkoutUser instanceof User) {
            return (string) $checkoutUser->id;
        }

        $email = strtolower(trim((string) ($purchaser['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $firstname = trim((string) ($purchaser['firstname'] ?? ''));
        $surname = trim((string) ($purchaser['surname'] ?? ''));
        $phone = trim((string) ($purchaser['phone'] ?? ''));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->firstname = $firstname !== '' ? $firstname : null;
            $user->surname = $surname !== '' ? $surname : null;
            $user->phone = $phone !== '' ? $phone : null;
            $user->save();

            if ($workshop->usesClassroomRegistration()) {
                $this->sendClassroomAccountInvite($user);
            }

            return (string) $user->id;
        }

        if ($user->email_verified_at === null) {
            if ($firstname !== '') {
                $user->firstname = $firstname;
            }
            if ($surname !== '') {
                $user->surname = $surname;
            }
            if ($phone !== '') {
                $user->phone = $phone;
            }
            $user->save();

            if ($workshop->usesClassroomRegistration()) {
                $this->sendClassroomAccountInvite($user);
            }
        }

        return (string) $user->id;
    }

    private function sendClassroomAccountInvite(User $user): void
    {
        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === '') {
            return;
        }

        $user->tokens()->where('type', 'register')->delete();
        $token = $user->tokens()->create([
            'type' => 'register',
            'data' => ['url' => session()->pull('url.intended', null)],
        ]);

        dispatch(new SendEmail($email, new UserRegister($token->id, $email)))->onQueue('mail');
    }

    private function getFlowSession(Workshop $workshop): ?array
    {
        $value = session()->get(self::SESSION_KEY_PREFIX.$workshop->id);
        if (! is_array($value)) {
            return null;
        }

        return $value;
    }

    private function putFlowSession(Workshop $workshop, array $payload): void
    {
        session()->put(self::SESSION_KEY_PREFIX.$workshop->id, $payload);
    }

    private function clearFlowSession(Workshop $workshop): void
    {
        session()->forget(self::SESSION_KEY_PREFIX.$workshop->id);
    }

    private function holdsExpired(Workshop $workshop, array $holdIds, WorkshopTicketService $ticketService): bool
    {
        if (empty($holdIds)) {
            return true;
        }

        $ticketService->cleanupExpiredHolds($workshop);
        $count = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $holdIds)
            ->where('status', Ticket::STATUS_HOLD)
            ->where('created_at', '>=', now()->subMinutes($ticketService->holdWindowMinutes()))
            ->count();

        return $count !== count($holdIds);
    }

    private function defaultPurchaserData(): array
    {
        $user = $this->checkoutAccountUser();

        return [
            'firstname' => (string) ($user->firstname ?? ''),
            'surname' => (string) ($user->surname ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
        ];
    }

    private function checkoutAccountUser(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->canPurchaseOrBook()) {
            return null;
        }

        return $user;
    }

    private function checkoutAccountUserId(): ?string
    {
        $user = $this->checkoutAccountUser();

        return $user instanceof User ? (string) $user->id : null;
    }

    private function checkoutCreditUser(array $session): ?User
    {
        $checkoutUser = $this->checkoutAccountUser();
        if ($checkoutUser instanceof User && $checkoutUser->canPurchaseOrBook()) {
            return $checkoutUser;
        }

        $sessionUserId = trim((string) ($session['purchaser_user_id'] ?? ''));
        if ($sessionUserId === '') {
            return null;
        }

        $sessionUser = User::query()->find($sessionUserId);

        return $sessionUser instanceof User && $sessionUser->canPurchaseOrBook()
            ? $sessionUser
            : null;
    }

    private function checkoutAccountTermsDays(): int
    {
        $user = $this->checkoutAccountUser();
        if (! $user instanceof User || ! $user->hasAccountTerms()) {
            return 0;
        }

        return $user->accountTermsDays();
    }

    private function accountTermsLabel(int $termDays): string
    {
        return $termDays <= 0 ? 'Current' : $termDays.' days';
    }

    private function purchaserEmailHasCredit(array $session): bool
    {
        $email = strtolower(trim((string) data_get($session, 'purchaser.email', '')));
        if ($email === '') {
            return false;
        }

        $purchaser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $purchaser instanceof User) {
            return false;
        }

        return $this->accountCredit->availableCreditForUser($purchaser) > 0.0001;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountCreditDebugRows(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return Payment::query()
            ->where('user_id', $user->id)
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT)
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount')
            ->withSum('refunds as refunded_amount_sum', 'total_amount')
            ->orderBy('received_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (Payment $payment): array {
                $total = (float) $payment->total_amount;
                $allocated = (float) ($payment->allocated_amount_sum ?? 0);
                $refunded = (float) ($payment->refunded_amount_sum ?? 0);
                $available = max(0, round($total - $allocated - $refunded, 2));

                return [
                    'id' => $payment->id,
                    'reference' => (string) ($payment->reference ?? ''),
                    'received_on' => optional($payment->received_on)->format('Y-m-d H:i:s'),
                    'total_amount' => $total,
                    'allocated_amount' => $allocated,
                    'refunded_amount' => $refunded,
                    'available_amount' => $available,
                ];
            })
            ->all();
    }

    private function redirectToWorkshopWithCheckoutExpiredToast(Workshop $workshop): RedirectResponse
    {
        session()->flash('message', $workshop->usesClassroomRegistration()
            ? 'Your classroom checkout session expired while this page was open. Reload this page or restart checkout before saving access details.'
            : 'Your checkout session expired while this page was open. Reload this page or restart checkout before saving ticket details.');
        session()->flash('message-title', 'Session expired');
        session()->flash('message-type', 'warning');

        return redirect()->route('workshop.show', $workshop);
    }

    private function squareDateTime($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, int>  $ticketIds
     */
    private function createWorkshopTicketEmailDelivery(
        Workshop $workshop,
        array $ticketIds,
        ?int $invoiceId,
        ?int $paymentId,
        string $recipientEmail,
        string $recipientName,
        string $paymentMethod,
        float $amount
    ): WorkshopTicketEmail {
            $normalizedTicketIds = collect($ticketIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedTicketIds === []) {
            throw new RuntimeException('Cannot create a workshop ticket email without tickets.');
        }

        $delivery = new WorkshopTicketEmail();
        $delivery->workshop_id = (string) $workshop->id;
        $delivery->ticket_ids = $normalizedTicketIds;
        $delivery->invoice_id = $invoiceId;
        $delivery->payment_id = $paymentId;
        $delivery->recipient_email = strtolower(trim($recipientEmail));
        $delivery->recipient_name = trim($recipientName);
        $delivery->payment_method = trim($paymentMethod);
        $delivery->amount = round($amount, 2);
        $delivery->status = WorkshopTicketEmail::STATUS_PENDING;
        $delivery->save();

        return $delivery;
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

        $gatewayProcessedAtRaw = trim((string) ($payment->square_gateway_updated_at ?? $payment->square_gateway_created_at ?? ''));
        $gatewayProcessedAtLabel = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAtLabel = Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (\Throwable) {
                $gatewayProcessedAtLabel = '';
            }
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
            'gatewayProvider' => (string) ($payment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($payment->gateway_status ?? ''),
            'transactionId' => trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id)),
            'squareOrderId' => (string) ($payment->square_order_id ?? ''),
            'cardBrand' => (string) ($payment->square_card_brand ?? ''),
            'cardLast4' => (string) ($payment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($payment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $gatewayProcessedAtLabel,
            'footerMessage' => $payment->isRefund() ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->output([
            'compress' => 1,
        ]);
    }

    private function invoicePdfFilename(Invoice $invoice): string
    {
        $number = trim((string) ($invoice->invoice_number ?? $invoice->id));
        $number = preg_replace('/[^a-z0-9._-]+/i', '-', $number) ?: (string) $invoice->id;

        return 'invoice-'.$number.'.pdf';
    }

    private function invoiceItemPagesForPdf(Invoice $invoice): array
    {
        $lineIds = $invoice->lines
            ->where('kind', 'ticket')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $tickets = collect();
        if ($lineIds !== []) {
            $tickets = Ticket::query()
                ->where('invoice_id', $invoice->id)
                ->where(function ($query) use ($lineIds): void {
                    $query->whereIn('invoice_line_id', $lineIds)
                        ->orWhereIn('id', $lineIds);
                })
                ->get(['id', 'invoice_line_id', 'reference_code', 'status', 'created_at']);
        }

        $items = $invoice->lines->map(function (InvoiceLine $line) use ($tickets): array {
            $lineNotes = trim((string) ($line->notes ?? ''));
            if ((string) $line->kind === 'ticket') {
                $lineTicketId = (int) data_get($line->details_json, 'ticket_id');
                $lineReference = trim((string) data_get($line->details_json, 'ticket_reference'));
                $linkedTickets = $tickets->where('invoice_line_id', (int) $line->id);
                if ($linkedTickets->isEmpty() && $lineTicketId > 0) {
                    $linkedTickets = $tickets->where('id', $lineTicketId);
                }

                $activeTicket = $linkedTickets
                    ->filter(fn (Ticket $ticket) => in_array((int) $ticket->status, Ticket::activePurchasedStatuses(), true))
                    ->sortByDesc('created_at')
                    ->first();
                if ($activeTicket instanceof Ticket) {
                    $activeReference = $activeTicket->ensureReferenceCode();
                    $fromReference = $lineReference !== ''
                        ? $lineReference
                        : trim((string) (optional($linkedTickets->sortBy('created_at')->first())->reference_code ?? ''));

                    if ($activeReference !== '' && $fromReference !== $activeReference && ! str_contains($lineNotes, 'Ticket reissued as:')) {
                        $suffix = 'Ticket reissued as: '.$activeReference;
                        $lineNotes = $lineNotes !== '' ? $lineNotes."\n".$suffix : $suffix;
                    }
                }
            }

            return [
                'description' => (string) $line->description,
                'notes' => $lineNotes,
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
}
