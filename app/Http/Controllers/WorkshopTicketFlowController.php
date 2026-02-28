<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketOrderConfirmation;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\Token;
use App\Models\User;
use App\Models\Workshop;
use App\Providers\QRCodeProvider;
use App\Services\DocumentNumberService;
use App\Services\SquareApiService;
use App\Services\WorkshopTicketService;
use App\Support\AltchaTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        private readonly DocumentNumberService $documentNumbers
    )
    {
    }

    public function start(Workshop $workshop, WorkshopTicketService $ticketService): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $ticketService->cleanupExpiredHolds($workshop);
        if (! $ticketService->canStartTicketCheckout($workshop)) {
            session()->flash('message', 'Tickets are not available for this workshop.');
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
                'quantity' => 'Tickets are no longer available for this workshop.',
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
        $purchaserUserId = $this->resolveCheckoutUserId($purchaser);

        $holdIds = DB::transaction(function () use ($workshop, $ticketService, $validated, $purchaser, $purchaserUserId) {
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
            'invoice_id' => null,
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
            session()->flash('message', 'Your ticket hold expired. Please start again.');
            session()->flash('message-title', 'Hold expired');
            session()->flash('message-type', 'warning');

            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('id', $session['hold_ids'] ?? [])
            ->orderBy('id')
            ->get();
        $tickets->each(fn (Ticket $ticket) => $ticket->ensureReferenceCode());

        return view('workshop.tickets.payment', [
            'workshop' => $workshop,
            'session' => $session,
            'holdCount' => count($session['hold_ids'] ?? []),
            'ticketPriceAmount' => $ticketService->ticketPriceAmount($workshop),
            'totalAmount' => round($ticketService->ticketPriceAmount($workshop) * count($session['hold_ids'] ?? []), 2),
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
            session()->flash('message', 'Your ticket hold expired. Please start again.');
            session()->flash('message-title', 'Hold expired');
            session()->flash('message-type', 'warning');

            return redirect()->route('workshop.ticket.flow.start', $workshop);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:pay_at_door,bank_transfer,credit_card'],
            'source_id' => ['nullable', 'string', 'max:255'],
        ]);

        $holdIds = collect($session['hold_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
        $ticketPriceAmount = $ticketService->ticketPriceAmount($workshop);
        $amount = round($ticketPriceAmount * count($holdIds), 2);
        $paymentMethod = $amount <= 0 ? 'free' : $validated['payment_method'];

        if ($paymentMethod === 'credit_card' && trim((string) ($validated['source_id'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Card details are required for credit card payment.',
            ]);
        }

        $result = DB::transaction(function () use ($workshop, $ticketService, $holdIds, $paymentMethod, $amount, $ticketPriceAmount, $validated, $squareApi, $session) {
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
                $purchaserUserId = $this->resolveCheckoutUserId($session['purchaser'] ?? []);
            }

            $invoice = null;
            if ($amount > 0) {
                $invoice = $this->createTicketInvoice(
                    $workshop,
                    $holds,
                    $ticketPriceAmount,
                    $session['purchaser'] ?? [],
                    $purchaserUserId !== '' ? $purchaserUserId : null
                );
            }

            $ticketStatus = match ($paymentMethod) {
                'pay_at_door' => Ticket::STATUS_PENDING_DOOR,
                'bank_transfer' => Ticket::STATUS_PENDING_XFER,
                default => Ticket::STATUS_PAID,
            };

            if ($paymentMethod === 'credit_card' && $amount > 0) {
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
                $customerPayment->user_id = $purchaserUserId !== '' ? $purchaserUserId : auth()->id();
                $customerPayment->created_by = auth()->id();
                $customerPayment->kind = Payment::KIND_PAYMENT;
                $customerPayment->received_on = now();
                $customerPayment->payment_method = 'credit_card';
                $ticketReferences = $holds
                    ->map(fn (Ticket $ticket) => $ticket->ensureReferenceCode())
                    ->values()
                    ->all();
                $customerPayment->reference = 'Workshop '.$workshop->title.' ticket' . (count($ticketReferences) > 1 ? 's' : '') . ' ['.implode(',', $ticketReferences).']';
                $customerPayment->total_amount = $amount;
                $customerPayment->gst_amount = 0;
                $customerPayment->notes = 'Workshop "'.$workshop->title.'" ticket purchase';
                $customerPayment->save();

                try {
                    $paymentResponse = $squareApi->createPayment([
                        'idempotency_key' => 'ticket-flow-'.$workshop->id.'-custpay-'.$customerPayment->id,
                        'source_id' => (string) $validated['source_id'],
                        'location_id' => $locationId,
                        'reference_id' => 'payment:'.$customerPayment->id,
                        'amount_money' => [
                            'amount' => (int) round($amount * 100),
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
                    $wasPaid = (string) $invoice->status === \App\Models\Invoice::STATUS_PAID;
                    $invoice->status = \App\Models\Invoice::STATUS_PAID;
                    $invoice->save();
                    $customerPayment->allocations()->create([
                        'invoice_id' => $invoice->id,
                        'allocated_amount' => $amount,
                    ]);

                }
            } elseif ($invoice) {
                $invoice->status = \App\Models\Invoice::STATUS_ISSUED;
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
                $ticket->save();
            }

            return [
                'invoice_id' => $invoice?->id,
                'payment_method' => $paymentMethod,
                'payment_id' => isset($customerPayment) ? (int) $customerPayment->id : null,
            ];
        });
        $ticketService->syncManagedTicketStatus($workshop);

        $session['invoice_id'] = $result['invoice_id'];
        $session['payment_method'] = $result['payment_method'];
        $session['payment_id'] = $result['payment_id'] ?? null;
        $session['payment_complete'] = true;
        $this->putFlowSession($workshop, $session);

        try {
            $invoice = isset($result['invoice_id']) ? Invoice::query()->find($result['invoice_id']) : null;
            $tickets = Ticket::query()
                ->with('workshop.location')
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $holdIds)
                ->orderBy('id')
                ->get();
            $customerPayment = isset($result['payment_id']) && (int) $result['payment_id'] > 0
                ? Payment::query()->find((int) $result['payment_id'])
                : null;
            $this->sendCustomerTicketOrderConfirmation(
                workshop: $workshop->loadMissing('location'),
                tickets: [],
                invoice: $invoice,
                payment: $customerPayment,
                paymentMethod: (string) $result['payment_method'],
                amount: $amount,
                purchaserEmail: strtolower(trim((string) ($session['purchaser']['email'] ?? ''))),
                purchaserName: trim((string) (($session['purchaser']['firstname'] ?? '').' '.($session['purchaser']['surname'] ?? ''))),
                ticketCount: $tickets->count()
            );
        } catch (Throwable $e) {
            report($e);
            session()->flash(
                'message',
                'Checkout completed, but we could not send your confirmation email right now. You can still access your tickets from your account or via ticket lookup.'
            );
            session()->flash('message-title', 'Email not sent');
            session()->flash('message-type', 'warning');
        }

        return redirect()->route('workshop.ticket.flow.details', $workshop);
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
                $purchaserUserId = $this->resolveCheckoutUserId($session['purchaser'] ?? []);
            }

            foreach ($holds as $ticket) {
                $ticket->status = Ticket::STATUS_PAID;
                if ($purchaserUserId !== '') {
                    $ticket->user_id = $purchaserUserId;
                }
                $ticket->invoice_id = null;
                $ticket->invoice_line_id = null;
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
        $this->putFlowSession($workshop, $session);

        return redirect()->route('workshop.ticket.flow.details', $workshop);
    }

    public function details(Workshop $workshop): View|RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['payment_complete'] ?? false)) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
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

        return view('workshop.tickets.details', [
            'workshop' => $workshop,
            'session' => $session,
            'tickets' => $tickets,
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

    public function saveDetails(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->ensureWorkshopPubliclyVisible($workshop);

        $session = $this->getFlowSession($workshop);
        if (! $session || !($session['payment_complete'] ?? false)) {
            return redirect()->route('workshop.ticket.flow.start', $workshop);
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
            $updatedTickets = Ticket::query()
                ->with('workshop.location')
                ->whereIn('id', $updatedHoldIds)
                ->orderBy('id')
                ->get();
            $invoice = isset($session['invoice_id']) ? Invoice::query()->find($session['invoice_id']) : null;
            $payment = isset($session['payment_id']) ? Payment::query()->find((int) $session['payment_id']) : null;
            $purchaserEmail = strtolower(trim((string) ($session['purchaser']['email'] ?? '')));
            $purchaserName = trim((string) (($session['purchaser']['firstname'] ?? '').' '.($session['purchaser']['surname'] ?? '')));
            $orderAmount = (float) ($payment->total_amount ?? $invoice->total_amount ?? 0.0);

            $this->sendCustomerTicketOrderConfirmation(
                workshop: $workshop->loadMissing('location'),
                tickets: $updatedTickets->all(),
                invoice: null,
                payment: null,
                paymentMethod: (string) ($session['payment_method'] ?? 'free'),
                amount: $orderAmount,
                purchaserEmail: $purchaserEmail,
                purchaserName: $purchaserName
            );

            $this->sendCheckoutHolderTicketEmails(
                tickets: $updatedTickets->all(),
                purchaserEmail: $purchaserEmail,
                purchaserName: $purchaserName
            );
        } catch (Throwable $e) {
            report($e);
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
        $sentToEmail = trim((string) ($session['purchaser']['email'] ?? $tickets->first()->email ?? ''));
        $normalizedSentToEmail = strtolower($sentToEmail);
        $holderRecipientCount = $tickets
            ->map(fn (Ticket $ticket): string => strtolower(trim((string) ($ticket->email ?? ''))))
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->reject(fn (string $email): bool => $email === $normalizedSentToEmail)
            ->count();
        $accessToken = null;

        if ($sentToEmail !== '') {
            $tokenUserId = trim((string) ($session['purchaser_user_id'] ?? $tickets->first()->user_id ?? auth()->id()));
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
            'holderRecipientCount' => $holderRecipientCount,
            'accessToken' => $accessToken,
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

        foreach ($tickets as $ticket) {
            $ticketPdf = $this->buildTicketPdfBinary($ticket);
            if ($ticketPdf !== null) {
                $zip->addFromString($this->ticketPdfFilename($ticket), $ticketPdf);
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
        float $ticketPriceAmount,
        array $purchaser,
        ?string $purchaserUserId = null
    ): Invoice
    {
        $tickets = collect($holds)->values();
        $quantity = max(1, $tickets->count());
        $ticketPriceAmount = round($ticketPriceAmount, 2);
        $totalAmount = round($ticketPriceAmount * $quantity, 2);

        $invoice = new Invoice();
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->user_id = $purchaserUserId ?: auth()->id();
        $invoice->billing_name = trim((string) (($purchaser['firstname'] ?? '').' '.($purchaser['surname'] ?? '')));
        $invoice->billing_email = trim((string) ($purchaser['email'] ?? ''));
        $invoice->billing_phone = trim((string) ($purchaser['phone'] ?? ''));
        $invoice->status = 'draft';
        $invoice->issue_date = Carbon::today();
        $invoice->due_date = $workshop->starts_at
            ? Carbon::parse($workshop->starts_at)->startOfDay()
            : Carbon::today();
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
            $lineTotalInc = $ticketPriceAmount;
            $lineTotalEx = round($lineTotalInc / 1.1, 2);
            $taxAmount = round($lineTotalInc - $lineTotalEx, 2);
            $ticketReference = $ticket->ensureReferenceCode();

            $line = new InvoiceLine();
            $line->invoice_id = $invoice->id;
            $line->line_number = $index + 1;
            $line->kind = 'ticket';
            $line->description = $workshop->title.' - Ticket '.$ticketReference;
            $line->notes = trim(implode("\n", [
                'Workshop date/time: '.($workshop->starts_at?->format('M j, Y g:i a') ?? '-'),
                'Workshop location: '.((string) ($workshop->getLocationName())),
            ]));
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

        $invoice->subtotal_amount = round($subtotal, 2);
        $invoice->gst_amount = round($gst, 2);
        $invoice->total_amount = round($invoice->subtotal_amount + $invoice->gst_amount, 2);
        $invoice->save();

        $invoice->setRelation('lines', $lines);

        return $invoice;
    }

    private function bankTransferMethodNotice(): ?string
    {
        $value = trim((string) SiteOption::value('checkout.bank_transfer_notice'));

        return $value !== '' ? $value : null;
    }

    private function payAtDoorMethodNotice(): ?string
    {
        $value = trim((string) SiteOption::value('checkout.pay_at_door_notice'));

        return $value !== '' ? $value : null;
    }

    private function bankTransferDetails(?Invoice $invoice = null): ?array
    {
        $accountName = trim((string) SiteOption::value('payments.bank_account_name'));
        $bsb = trim((string) SiteOption::value('payments.bank_bsb'));
        $accountNumber = trim((string) SiteOption::value('payments.bank_account_number'));
        $reference = trim((string) ($invoice?->invoice_number ?? ''));

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

    private function resolveCheckoutUserId(array $purchaser): ?string
    {
        if (auth()->check()) {
            return (string) auth()->id();
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
        }

        return (string) $user->id;
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
        $user = auth()->user();

        return [
            'firstname' => (string) ($user->firstname ?? ''),
            'surname' => (string) ($user->surname ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
        ];
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

    private function sendCustomerTicketOrderConfirmation(
        Workshop $workshop,
        array $tickets,
        ?Invoice $invoice,
        ?Payment $payment,
        string $paymentMethod,
        float $amount,
        ?string $purchaserEmail = null,
        ?string $purchaserName = null,
        ?int $ticketCount = null
    ): void {
        $recipient = strtolower(trim((string) ($purchaserEmail ?? $tickets[0]->email ?? $invoice->billing_email ?? auth()->user()->email ?? '')));
        if ($recipient === '') {
            return;
        }

        $attachments = [];

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

        $ticketRows = array_map(function (Ticket $ticket): array {
            return [
                'reference' => $ticket->ensureReferenceCode(),
                'name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                'email' => (string) ($ticket->email ?? ''),
            ];
        }, $tickets);

        $recipientName = trim((string) ($purchaserName ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) (($tickets[0]->firstname ?? '').' '.($tickets[0]->surname ?? '')));
        }
        if ($recipientName === '') {
            $recipientName = auth()->user()?->getName() ?: $recipient;
        }

        dispatch(new SendEmail($recipient, new TicketOrderConfirmation(
            recipientName: $recipientName,
            workshop: [
                'title' => (string) ($workshop->title ?? ''),
                'time' => (string) ($workshop->getTicketTimeRangeLabel()),
                'location' => (string) ($workshop->getLocationDisplay(true)),
            ],
            tickets: $ticketRows,
            paymentMethodLabel: match ($paymentMethod) {
                'credit_card' => 'Credit Card',
                'pay_at_door' => 'Pay at Door',
                'bank_transfer' => 'Bank Transfer',
                'free' => 'Free',
                default => ucwords(str_replace('_', ' ', $paymentMethod)),
            },
            amount: (float) $amount,
            invoice: $invoice ? [
                'number' => (string) $invoice->invoice_number,
                'status' => (string) $invoice->status,
            ] : null,
            attachments: $attachments,
            ticketCount: $ticketCount,
        )))->onQueue('mail');
    }

    private function sendCheckoutHolderTicketEmails(
        array $tickets,
        string $purchaserEmail,
        string $purchaserName
    ): void {
        $normalizedPurchaser = strtolower(trim($purchaserEmail));

        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }

            $recipient = strtolower(trim((string) ($ticket->email ?? '')));
            if ($recipient === '' || $recipient === $normalizedPurchaser) {
                continue;
            }

            $ticketPdf = $this->buildTicketPdfBinary($ticket);
            $ticketAttachment = $ticketPdf !== null ? [
                'content' => $ticketPdf,
                'filename' => $this->ticketPdfFilename($ticket),
                'mime' => 'application/pdf',
            ] : null;

            $workshopInfo = [
                'title' => (string) ($ticket->workshop->title ?? ''),
                'time' => (string) ($ticket->workshop?->getTicketTimeRangeLabel() ?? '-'),
                'location' => (string) ($ticket->workshop?->getLocationDisplay(true) ?? '-'),
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
                purchaserName: $purchaserName,
                workshop: $workshopInfo,
                ticket: $ticketInfo,
                attachment: $ticketAttachment
            )))->onQueue('mail');
        }
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

        $thumbnailVariant = $hero->getClosestVariant('thumbnail');
        $thumbnailVariantName = trim((string) ($thumbnailVariant['variant'] ?? ''));
        $thumbnailPath = (string) ($thumbnailVariant['file'] ?? '');
        if ($thumbnailVariantName !== '' && $thumbnailPath !== '' && is_file($thumbnailPath)) {
            return $thumbnailPath;
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
