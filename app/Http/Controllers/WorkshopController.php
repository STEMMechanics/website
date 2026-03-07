<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\WorkshopTicketBroadcast;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PickListTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use App\Services\WorkshopTicketService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Workshop::query();
        $query = $query->publiclyVisible()
            ->where('starts_at', '>=', Carbon::now()->subDays(8))
            ->orderBy('starts_at', 'asc');

        $workshops = $query->paginate(12);

        return view('workshop.index', [
            'workshops' => $workshops,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function past_index()
    {
        $query = Workshop::query();
        $query = $query->publiclyVisible()
            ->where('starts_at', '<', Carbon::now())
            ->orderBy('starts_at', 'desc');

        $workshops = $query->paginate(12);

        return view('workshop.index', [
            'workshops' => $workshops,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function admin_index(Request $request)
    {
        $query = Workshop::query();

        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
            $query->orWhere('content', 'like', '%'.$request->search.'%');
        }

        $workshops = $query->orderBy('starts_at', 'desc')->paginate(12)->onEachSide(1);

        return view('admin.workshop.index', [
            'workshops' => $workshops,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function admin_create()
    {
        return view('admin.workshop.edit', [
            'pickListTemplates' => PickListTemplate::query()->orderBy('name')->get(),
            'groupSuggestions' => $this->groupSuggestions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function admin_store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'type' => 'required|in:physical,online',
            'location_id' => 'nullable|exists:locations,id',
            'starts_at' => 'required',
            'ends_at' => 'required|after:starts_at',
            'publish_at' => 'required',
            'closes_at' => 'required',
            'status' => 'required',
            'is_private' => 'nullable|boolean',
            'is_hidden' => 'nullable|boolean',
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_if:registration,link,email,message',
            'private_code' => 'nullable|string|max:120',
            'hosted_for' => 'nullable|string|max:255',
            'max_tickets' => 'nullable|integer|min:1|required_if:registration,tickets',
            'ticket_group_slug' => 'nullable|string|max:80',
            'pick_list_template_id' => 'nullable|exists:pick_list_templates,id',
            'pick_list_notes' => 'nullable|string',
            'tickets_json' => 'nullable|string',
            'private_files' => 'nullable|string',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'starts_at.required' => __('validation.custom_messages.starts_at_required'),
            'ends_at.required' => __('validation.custom_messages.ends_at_required'),
            'ends_at.after' => __('validation.custom_messages.ends_at_after'),
            'publish_at.required' => __('validation.custom_messages.publish_at_required'),
            'closes_at.required' => __('validation.custom_messages.closes_at_required'),
            'status.required' => __('validation.custom_messages.status_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
            'hero_media_name.exists' => __('validation.custom_messages.hero_media_name_exists'),
            'registration_data.required_if' => __('validation.custom_messages.registration_data_required_unless'),
        ]);

        $workshopData = $request->all();
        $workshopData['user_id'] = auth()->user()->id;
        $this->normalizeWorkshopTypeData($workshopData);
        $workshopData['is_private'] = $request->boolean('is_private');
        $workshopData['is_hidden'] = $request->boolean('is_hidden');
        if (($workshopData['status'] ?? null) === 'hidden') {
            $workshopData['status'] = 'open';
            $workshopData['is_hidden'] = true;
        }
        if (($workshopData['status'] ?? null) === 'private') {
            $workshopData['status'] = 'open';
            $workshopData['is_private'] = true;
        }
        if (($workshopData['registration'] ?? 'none') !== 'tickets') {
            $workshopData['max_tickets'] = null;
            $workshopData['ticket_group_slug'] = null;
        } else {
            $ticketGroupSlug = UserGroup::normalizeSlug((string) ($workshopData['ticket_group_slug'] ?? ''));
            $workshopData['ticket_group_slug'] = $ticketGroupSlug !== '' ? $ticketGroupSlug : null;
        }
        if (! isset($workshopData['pick_list_template_id']) || trim((string) $workshopData['pick_list_template_id']) === '') {
            $workshopData['pick_list_template_id'] = null;
        }
        if (! array_key_exists('pick_list_notes', $workshopData)) {
            $workshopData['pick_list_notes'] = null;
        }
        if (($workshopData['pick_list_template_id'] ?? null) !== null && trim((string) ($workshopData['pick_list_notes'] ?? '')) === '') {
            $templateNotes = (string) (PickListTemplate::query()
                ->where('id', (int) $workshopData['pick_list_template_id'])
                ->value('description') ?? '');
            $workshopData['pick_list_notes'] = trim($templateNotes) !== '' ? $templateNotes : null;
        }
        if (! in_array(($workshopData['registration'] ?? 'none'), ['link', 'email', 'message'], true)) {
            $workshopData['registration_data'] = null;
        }
        $this->normalizeWorkshopRegistrationData($workshopData);
        if (! $workshopData['is_private']) {
            $workshopData['private_code'] = null;
            $workshopData['hosted_for'] = null;
        } else {
            $privateCode = trim((string) ($workshopData['private_code'] ?? ''));
            $workshopData['private_code'] = $privateCode !== '' ? $privateCode : null;
            $hostedFor = trim((string) ($workshopData['hosted_for'] ?? ''));
            $workshopData['hosted_for'] = $hostedFor !== '' ? $hostedFor : null;
        }

        if ($workshopData['status'] === 'open' && Carbon::parse($workshopData['starts_at'])->lt(Carbon::now())) {
            $workshopData['status'] = 'closed';
        }

        $workshop = Workshop::create($workshopData);
        $workshop->updateFiles($request->input('files'));
        $workshop->updateFiles($request->input('private_files'), 'private');

        session()->flash('message', 'Workshop has been created');
        session()->flash('message-title', 'Workshop created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workshop $workshop, WorkshopTicketService $ticketService)
    {
        if (! (bool) (auth()->user()?->isAdmin() ?? false) && ! $workshop->isPubliclyVisible()) {
            abort(404);
        }

        $ticketService->cleanupExpiredHolds($workshop);
        $availableTickets = $ticketService->availableTickets($workshop);
        $ticketPriceAmount = $ticketService->ticketPriceAmount($workshop);
        $requiresPrivateAccessCode = $workshop->requiresPrivateAccessCode();
        $privateAccessKey = $this->privateAccessSessionKey($workshop);
        $hasPrivateAccess = ! $workshop->isPrivate()
            || (bool) (auth()->user()?->isAdmin() ?? false)
            || ($requiresPrivateAccessCode && (bool) session($privateAccessKey, false));
        $privateLockedNoCode = $workshop->isPrivate() && ! $requiresPrivateAccessCode;

        return view('workshop.show', [
            'workshop' => $workshop,
            'availableTickets' => $availableTickets,
            'canGetTickets' => $ticketService->canStartTicketCheckout($workshop),
            'ticketPriceAmount' => $ticketPriceAmount,
            'ticketHoldMinutes' => $ticketService->holdWindowMinutes(),
            'adminCanViewTickets' => (bool) (auth()->user()?->isAdmin() ?? false) && $workshop->registration === 'tickets',
            'requiresPrivateAccessCode' => $requiresPrivateAccessCode,
            'hasPrivateAccess' => $hasPrivateAccess,
            'privateLockedNoCode' => $privateLockedNoCode,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function admin_edit(Workshop $workshop)
    {
        return view('admin.workshop.edit', [
            'workshop' => $workshop,
            'pickListTemplates' => PickListTemplate::query()->orderBy('name')->get(),
            'groupSuggestions' => $this->groupSuggestions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function admin_update(Request $request, Workshop $workshop)
    {
        $request->validate([
            'title' => 'required',
            'content' => 'required',
            'type' => 'required|in:physical,online',
            'location_id' => 'nullable|exists:locations,id',
            'starts_at' => 'required',
            'ends_at' => 'required|after:starts_at',
            'publish_at' => 'required',
            'closes_at' => 'required',
            'status' => 'required',
            'is_private' => 'nullable|boolean',
            'is_hidden' => 'nullable|boolean',
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_if:registration,link,email,message',
            'private_code' => 'nullable|string|max:120',
            'hosted_for' => 'nullable|string|max:255',
            'max_tickets' => 'nullable|integer|min:1|required_if:registration,tickets',
            'ticket_group_slug' => 'nullable|string|max:80',
            'pick_list_template_id' => 'nullable|exists:pick_list_templates,id',
            'pick_list_notes' => 'nullable|string',
            'tickets_json' => 'nullable|string',
            'private_files' => 'nullable|string',
        ], [
            'title.required' => __('validation.custom_messages.title_required'),
            'content.required' => __('validation.custom_messages.content_required'),
            'starts_at.required' => __('validation.custom_messages.starts_at_required'),
            'ends_at.required' => __('validation.custom_messages.ends_at_required'),
            'ends_at.after' => __('validation.custom_messages.ends_at_after'),
            'publish_at.required' => __('validation.custom_messages.publish_at_required'),
            'closes_at.required' => __('validation.custom_messages.closes_at_required'),
            'status.required' => __('validation.custom_messages.status_required'),
            'hero_media_name.required' => __('validation.custom_messages.hero_media_name_required'),
            'hero_media_name.exists' => __('validation.custom_messages.hero_media_name_exists'),
            'registration_data.required_if' => __('validation.custom_messages.registration_data_required_unless'),
        ]);

        $workshopData = $request->all();
        $this->normalizeWorkshopTypeData($workshopData);
        $workshopData['is_private'] = $request->boolean('is_private');
        $workshopData['is_hidden'] = $request->boolean('is_hidden');
        if (($workshopData['status'] ?? null) === 'hidden') {
            $workshopData['status'] = 'open';
            $workshopData['is_hidden'] = true;
        }
        if (($workshopData['status'] ?? null) === 'private') {
            $workshopData['status'] = 'open';
            $workshopData['is_private'] = true;
        }
        if (($workshopData['registration'] ?? 'none') !== 'tickets') {
            $workshopData['max_tickets'] = null;
            $workshopData['ticket_group_slug'] = null;
        } else {
            $ticketGroupSlug = UserGroup::normalizeSlug((string) ($workshopData['ticket_group_slug'] ?? ''));
            $workshopData['ticket_group_slug'] = $ticketGroupSlug !== '' ? $ticketGroupSlug : null;
        }
        if (! isset($workshopData['pick_list_template_id']) || trim((string) $workshopData['pick_list_template_id']) === '') {
            $workshopData['pick_list_template_id'] = null;
        }
        $existingTemplateId = $workshop->pick_list_template_id !== null ? (int) $workshop->pick_list_template_id : null;
        $newTemplateId = $workshopData['pick_list_template_id'] !== null ? (int) $workshopData['pick_list_template_id'] : null;
        $templateChanged = $existingTemplateId !== $newTemplateId;

        if ($templateChanged) {
            if ($newTemplateId === null) {
                $workshopData['pick_list_notes'] = null;
            } else {
                $templateNotes = (string) (PickListTemplate::query()
                    ->where('id', $newTemplateId)
                    ->value('description') ?? '');
                $workshopData['pick_list_notes'] = trim($templateNotes) !== '' ? $templateNotes : null;
            }
        } else {
            if (! array_key_exists('pick_list_notes', $workshopData)) {
                $workshopData['pick_list_notes'] = $workshop->pick_list_notes;
            }
            if (($workshopData['pick_list_template_id'] ?? null) !== null && trim((string) ($workshopData['pick_list_notes'] ?? '')) === '') {
                $templateNotes = (string) (PickListTemplate::query()
                    ->where('id', (int) $workshopData['pick_list_template_id'])
                    ->value('description') ?? '');
                $workshopData['pick_list_notes'] = trim($templateNotes) !== '' ? $templateNotes : null;
            }
        }
        if (! in_array(($workshopData['registration'] ?? 'none'), ['link', 'email', 'message'], true)) {
            $workshopData['registration_data'] = null;
        }
        $this->normalizeWorkshopRegistrationData($workshopData);
        if (! $workshopData['is_private']) {
            $workshopData['private_code'] = null;
            $workshopData['hosted_for'] = null;
        } else {
            $privateCode = trim((string) ($workshopData['private_code'] ?? ''));
            $workshopData['private_code'] = $privateCode !== '' ? $privateCode : null;
            $hostedFor = trim((string) ($workshopData['hosted_for'] ?? ''));
            $workshopData['hosted_for'] = $hostedFor !== '' ? $hostedFor : null;
        }
        if ($workshopData['status'] === 'open' && Carbon::parse($workshopData['starts_at'])->lt(Carbon::now())) {
            $workshopData['status'] = 'closed';
        }

        $changingAwayFromManagedTickets = $workshop->registration === 'tickets'
            && (($workshopData['registration'] ?? 'none') !== 'tickets');
        if ($changingAwayFromManagedTickets) {
            $hasActiveTickets = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->exists();

            if ($hasActiveTickets) {
                session()->flash('message', 'This workshop cannot be changed from managed tickets while active tickets exist. Cancel/refund active tickets first.');
                session()->flash('message-title', 'Update blocked');
                session()->flash('message-type', 'danger');

                return redirect()->route('admin.workshop.edit', $workshop);
            }
        }

        $workshop->update($workshopData);
        $workshop->updateFiles($request->input('files'));
        $workshop->updateFiles($request->input('private_files'), 'private');

        session()->flash('message', 'Workshop has been updated');
        session()->flash('message-title', 'Workshop updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.index');
    }

    public function privateAccess(Request $request, Workshop $workshop): RedirectResponse
    {
        if (! (bool) (auth()->user()?->isAdmin() ?? false) && ! $workshop->isPubliclyVisible()) {
            abort(404);
        }

        if (! $workshop->isPrivate() || ! $workshop->requiresPrivateAccessCode()) {
            return redirect()->route('workshop.show', $workshop);
        }

        $validated = $request->validate([
            'private_code' => ['required', 'string', 'max:120'],
        ]);

        if (! $workshop->matchesPrivateAccessCode($validated['private_code'] ?? null)) {
            throw ValidationException::withMessages([
                'private_code' => 'The access code is incorrect.',
            ]);
        }

        session()->put($this->privateAccessSessionKey($workshop), true);

        if ($workshop->registration === 'link' && trim((string) ($workshop->registration_data ?? '')) !== '') {
            $registrationUrl = trim((string) ($workshop->registration_data ?? ''));
            if ($this->isSafeHttpUrl($registrationUrl)) {
                return redirect()->away($registrationUrl);
            }
        }

        return redirect()->route('workshop.show', $workshop);
    }

    public function admin_tickets(Workshop $workshop, Request $request)
    {
        $bulkEmailRecipientCount = count($this->resolveWorkshopTicketEmailRecipients($workshop));

        $activeTicketCount = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->count();

        $showInvoiceColumn = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->where('status', '!=', Ticket::STATUS_HOLD)
            ->whereNotNull('invoice_id')
            ->exists();

        $query = Ticket::query()
            ->with(['user', 'invoice.allocations.customerPayment'])
            ->where('workshop_id', $workshop->id)
            ->where('status', '!=', Ticket::STATUS_HOLD);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search', ''));
            $query->where(function ($builder) use ($search) {
                $builder->where('reference_code', 'like', '%'.$search.'%')
                    ->orWhere('firstname', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $tickets = $query
            ->orderByRaw(
                'CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END ASC',
                [Ticket::STATUS_CANCELLED, Ticket::STATUS_REISSUED]
            )
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        return view('admin.workshop.tickets', [
            'workshop' => $workshop,
            'tickets' => $tickets,
            'activeTicketCount' => $activeTicketCount,
            'showInvoiceColumn' => $showInvoiceColumn,
            'bulkEmailRecipientCount' => $bulkEmailRecipientCount,
        ]);
    }

    public function admin_tickets_email(Request $request, Workshop $workshop): RedirectResponse
    {
        $validated = $request->validate([
            'email_subject' => ['required', 'string', 'max:255'],
            'email_message' => ['required', 'string'],
        ]);

        $subject = trim((string) ($validated['email_subject'] ?? ''));
        $message = trim((string) ($validated['email_message'] ?? ''));
        $recipients = $this->resolveWorkshopTicketEmailRecipients($workshop);

        if ($recipients === []) {
            session()->flash('message', 'No ticket-holder or linked-user email addresses were found for this workshop.');
            session()->flash('message-title', 'No recipients');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();
        $toEmail = $initiatedByEmail;
        if ($toEmail === null) {
            $fallback = trim((string) config('mail.from.address', ''));
            $toEmail = $fallback !== '' ? $fallback : null;
        }
        if ($toEmail === null) {
            $fallback = trim((string) config('mail.admin_bcc', ''));
            $toEmail = $fallback !== '' ? $fallback : null;
        }

        if ($toEmail === null || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            session()->flash('message', 'Unable to send email: no valid sender/admin email address is configured.');
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        try {
            dispatch(new SendEmail($toEmail, new WorkshopTicketBroadcast(
                subjectLine: $subject,
                workshopTitle: (string) ($workshop->title ?? 'Workshop'),
                messageBody: $message,
                bccRecipients: $recipients,
                initiatedByEmail: $initiatedByEmail,
                initiatedByName: $initiatedByName,
            )))->onQueue('mail');
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Workshop email failed: '.$e->getMessage());
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Email sent to '.count($recipients).' recipient'.(count($recipients) == 1 ? '' : 's').'.');
        session()->flash('message-title', 'Email queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function admin_tickets_pdf(Workshop $workshop): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        $tickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->where('status', '!=', Ticket::STATUS_HOLD)
            ->orderBy('reference_code')
            ->orderBy('id')
            ->get();

        $currentTickets = $tickets
            ->filter(fn (Ticket $ticket) => in_array((int) $ticket->status, Ticket::activePurchasedStatuses(), true))
            ->values();
        $reissuedTickets = $tickets
            ->where('status', Ticket::STATUS_REISSUED)
            ->values();
        $cancelledTickets = $tickets
            ->where('status', Ticket::STATUS_CANCELLED)
            ->values();

        return DomPdf::loadView('pdf.workshop-ticket-roll', [
            'workshop' => $workshop->loadMissing('location'),
            'currentTickets' => $currentTickets,
            'reissuedTickets' => $reissuedTickets,
            'cancelledTickets' => $cancelledTickets,
            'generatedAt' => now(),
        ])->setOption([
            'enable_font_subsetting' => true,
        ])->stream('workshop-'.$workshop->id.'-ticket-roll.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function admin_destroy(Workshop $workshop)
    {
        $hasActiveTickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->exists();
        if ($hasActiveTickets) {
            session()->flash('message', 'This workshop cannot be deleted while active tickets exist. Cancel/refund active tickets first.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.workshop.edit', $workshop);
        }

        $hasHistoricalTickets = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->where('status', '!=', Ticket::STATUS_HOLD)
            ->exists();
        if ($hasHistoricalTickets) {
            session()->flash('message', 'This workshop cannot be deleted because ticket records must be retained.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.workshop.edit', $workshop);
        }

        $workshop->delete();
        session()->flash('message', 'Workshop has been deleted');
        session()->flash('message-title', 'Workshop deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.workshop.index');
    }

    public function admin_attendance(Workshop $workshop): Response|\Illuminate\Contracts\View\View
    {
        $isKiosk = request()->boolean('kiosk') && $workshop->registration !== 'tickets';
        $search = trim((string) request()->query('search', ''));
        $showCancelledTickets = request()->boolean('show_cancelled');

        $activeTickets = collect();
        $cancelledTickets = collect();
        $attendanceInvoiceMeta = [];
        $paymentMethodLabels = collect(Payment::PAYMENT_METHODS)
            ->mapWithKeys(fn (string $method): array => [$method => Payment::paymentMethodLabel($method)])
            ->all();
        $ticketPaymentRows = [];
        if ($workshop->registration === 'tickets') {
            $applyTicketSearch = function ($builder) use ($search): void {
                if ($search === '') {
                    return;
                }

                $builder->where(function ($query) use ($search): void {
                    $query->where('reference_code', 'like', '%'.$search.'%')
                        ->orWhere('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            };

            $activeTicketsQuery = Ticket::query()
                ->with(['invoice', 'invoiceLine'])
                ->where('workshop_id', $workshop->id)
                ->whereIn('status', Ticket::activePurchasedStatuses());
            $applyTicketSearch($activeTicketsQuery);

            $activeTickets = $activeTicketsQuery
                ->orderBy('firstname')
                ->orderBy('surname')
                ->orderBy('id')
                ->get();

            if ($showCancelledTickets) {
                $cancelledTicketsQuery = Ticket::query()
                    ->with('invoice')
                    ->where('workshop_id', $workshop->id)
                    ->where('status', Ticket::STATUS_CANCELLED);
                $applyTicketSearch($cancelledTicketsQuery);

                $cancelledTickets = $cancelledTicketsQuery
                    ->orderBy('firstname')
                    ->orderBy('surname')
                    ->orderBy('id')
                    ->get();
            }

            $invoiceIds = $activeTickets->pluck('invoice_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($invoiceIds !== []) {
                $invoices = Invoice::query()
                    ->with(['allocations.customerPayment', 'taxAdjustments'])
                    ->whereIn('id', $invoiceIds)
                    ->get();

                foreach ($invoices as $invoice) {
                    $expectedKind = $invoice->expectedSettlementKind();
                    $settled = round((float) $invoice->allocations
                        ->filter(function ($allocation) use ($expectedKind): bool {
                            $payment = $allocation->customerPayment;
                            if (! $payment || (string) $payment->kind !== $expectedKind) {
                                return false;
                            }

                            return ((float) $allocation->allocated_amount) > 0;
                        })
                        ->sum('allocated_amount'), 2);
                    $outstanding = max(0, round($invoice->dueAmount() - $settled, 2));

                    $attendanceInvoiceMeta[(int) $invoice->id] = [
                        'id' => (int) $invoice->id,
                        'number' => (string) $invoice->invoice_number,
                        'status' => (string) $invoice->status,
                        'outstanding' => $outstanding,
                    ];
                }
            }

            $fallbackTicketPrice = 0.0;
            $rawWorkshopPrice = trim((string) ($workshop->price ?? ''));
            if ($rawWorkshopPrice !== '') {
                $numericWorkshopPrice = preg_replace('/[^0-9.]/', '', $rawWorkshopPrice);
                if (is_string($numericWorkshopPrice) && $numericWorkshopPrice !== '' && is_numeric($numericWorkshopPrice)) {
                    $fallbackTicketPrice = round((float) $numericWorkshopPrice, 2);
                }
            }

            $ticketPaymentRows = $activeTickets->map(function (Ticket $ticket) use ($attendanceInvoiceMeta, $fallbackTicketPrice): array {
                $invoiceId = (int) ($ticket->invoice_id ?? 0);
                $invoiceMeta = $invoiceId > 0 ? ($attendanceInvoiceMeta[$invoiceId] ?? null) : null;
                $ticketPrice = round((float) ($ticket->invoice_line_id !== null ? $ticket->invoiceLine->line_total_inc_tax : 0), 2);
                if ($ticketPrice <= 0.0001) {
                    $ticketPrice = $fallbackTicketPrice;
                }

                return [
                    'id' => (int) $ticket->id,
                    'reference' => (string) ($ticket->reference_code ?: $ticket->id),
                    'attendee' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))) ?: '-',
                    'invoice_id' => $invoiceId > 0 ? $invoiceId : null,
                    'invoice_number' => $invoiceMeta['number'] ?? null,
                    'invoice_outstanding' => (float) ($invoiceMeta['outstanding'] ?? 0),
                    'ticket_price' => max(0, $ticketPrice),
                ];
            })->values()->all();
        }

        $dropIns = WorkshopAttendance::query()
            ->with('user')
            ->where('workshop_id', $workshop->id)
            ->whereNull('ticket_id')
            ->orderByDesc('attended_at')
            ->orderByDesc('id')
            ->get();

        $view = $isKiosk ? 'admin.workshop.attendance-kiosk' : 'admin.workshop.attendance';

        return response()->view($view, [
            'workshop' => $workshop->loadMissing('location'),
            'activeTickets' => $activeTickets,
            'cancelledTickets' => $cancelledTickets,
            'showCancelledTickets' => $showCancelledTickets,
            'attendanceInvoiceMeta' => $attendanceInvoiceMeta,
            'ticketPaymentRows' => $ticketPaymentRows,
            'paymentMethodLabels' => $paymentMethodLabels,
            'dropIns' => $dropIns,
            'isKiosk' => $isKiosk,
        ]);
    }

    public function admin_attendance_csv(Workshop $workshop)
    {
        $rows = $this->buildAttendanceExportRows($workshop);

        $filename = 'workshop-'.$workshop->id.'-attendance.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if (! is_resource($out)) {
                return;
            }

            fputcsv($out, [
                'Source',
                'Child Name',
                'Parent/Guardian Name',
                'Email',
                'Phone',
                'Media Consent',
                'Ticket Reference',
                'Status',
                'Recorded At',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['source'],
                    $row['child_name'],
                    $row['guardian_name'],
                    $row['email'],
                    $row['phone'],
                    $row['media_consent'],
                    $row['ticket_reference'],
                    $row['status'],
                    $row['recorded_at'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function admin_attendance_pdf(Workshop $workshop): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        $rows = $this->buildAttendanceExportRows($workshop);

        return DomPdf::loadView('pdf.workshop-attendance-sheet', [
            'workshop' => $workshop->loadMissing('location'),
            'rows' => collect($rows),
            'generatedAt' => now(),
        ])->stream('workshop-'.$workshop->id.'-attendance.pdf');
    }

    public function admin_attendance_tickets(Request $request, Workshop $workshop): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'attended_ticket_ids' => ['nullable', 'array'],
            'attended_ticket_ids.*' => ['integer'],
        ]);

        if ($workshop->registration !== 'tickets') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This workshop does not use managed tickets.',
                ], 422);
            }

            session()->flash('message', 'This workshop does not use managed tickets.');
            session()->flash('message-title', 'Attendance update skipped');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.workshop.attendance', $workshop);
        }

        $selectedIds = collect($validated['attended_ticket_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $query = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses());
        $activeTickets = (clone $query)
            ->select(['id', 'attended_at'])
            ->get();
        $allActiveIds = $activeTickets
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $currentlyAttendedIds = $activeTickets
            ->filter(fn (Ticket $ticket): bool => $ticket->attended_at !== null)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $validSelected = array_values(array_intersect($selectedIds, $allActiveIds));
        $toMark = array_values(array_diff($validSelected, $currentlyAttendedIds));
        $toClear = array_values(array_diff($currentlyAttendedIds, $validSelected));

        $savedAt = now();

        if ($toMark !== []) {
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $toMark)
                ->update(['attended_at' => $savedAt]);
        }

        if ($toClear !== []) {
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $toClear)
                ->update(['attended_at' => null]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket attendance has been updated.',
                'attended_ticket_ids' => $validSelected,
                'saved_at_iso' => $savedAt->toIso8601String(),
                'saved_at_display' => $savedAt->format('M j, Y g:i a'),
            ]);
        }

        session()->flash('message', 'Ticket attendance has been updated.');
        session()->flash('message-title', 'Attendance saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    public function admin_attendance_payments(Request $request, Workshop $workshop): RedirectResponse
    {
        if ($workshop->registration !== 'tickets') {
            session()->flash('message', 'This workshop does not use managed tickets.');
            session()->flash('message-title', 'Payment not recorded');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.workshop.attendance', $workshop);
        }

        $validated = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer', 'min:1'],
            'attended_ticket_ids' => ['nullable', 'array'],
            'attended_ticket_ids.*' => ['integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.received_on' => ['nullable', 'date'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string'],
            'sync_attendance' => ['nullable', 'boolean'],
            'mark_attended' => ['nullable', 'boolean'],
        ]);

        $selectedTicketIds = collect($validated['ticket_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($selectedTicketIds === []) {
            throw ValidationException::withMessages([
                'ticket_ids' => 'Select at least one ticket.',
            ]);
        }

        $tickets = Ticket::query()
            ->with('invoice')
            ->where('workshop_id', $workshop->id)
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->whereIn('id', $selectedTicketIds)
            ->get();
        if ($tickets->isEmpty()) {
            throw ValidationException::withMessages([
                'ticket_ids' => 'No eligible tickets were selected.',
            ]);
        }
        $selectedTicketIds = $tickets->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $attendedTicketIds = collect($validated['attended_ticket_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->intersect($selectedTicketIds)
            ->unique()
            ->values()
            ->all();

        $invoiceIds = $tickets->pluck('invoice_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($invoiceIds === []) {
            throw ValidationException::withMessages([
                'ticket_ids' => 'Selected tickets are not linked to an invoice.',
            ]);
        }

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy(fn (Invoice $invoice): int => (int) $invoice->id);
        $outstandingByInvoiceId = [];
        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoices->get($invoiceId);
            if (! $invoice) {
                continue;
            }

            $outstanding = max(0, round((float) $invoice->outstandingAmount(), 2));
            if ($outstanding > 0.0001) {
                $outstandingByInvoiceId[$invoiceId] = $outstanding;
            }
        }

        if ($outstandingByInvoiceId === []) {
            session()->flash('message', 'The selected tickets are already fully paid.');
            session()->flash('message-title', 'No payment needed');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.workshop.attendance', $workshop);
        }

        $paymentLines = collect($validated['payments'] ?? [])
            ->filter(fn ($line): bool => is_array($line))
            ->map(function (array $line): array {
                return [
                    'method' => trim((string) ($line['method'] ?? '')),
                    'amount' => round((float) ($line['amount'] ?? 0), 2),
                    'received_on' => trim((string) ($line['received_on'] ?? '')),
                    'reference' => trim((string) ($line['reference'] ?? '')),
                    'notes' => trim((string) ($line['notes'] ?? '')),
                ];
            })
            ->filter(fn (array $line): bool => $line['amount'] > 0.0001)
            ->values();
        if ($paymentLines->isEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'Add at least one payment amount.',
            ]);
        }

        $totalOutstanding = round((float) collect($outstandingByInvoiceId)->sum(), 2);
        $totalPaymentAmount = round((float) $paymentLines->sum('amount'), 2);
        if ($totalPaymentAmount > ($totalOutstanding + 0.0001)) {
            throw ValidationException::withMessages([
                'payments' => 'Payment total cannot exceed outstanding total of $'.number_format($totalOutstanding, 2).'.',
            ]);
        }

        $invoiceUserIds = collect($invoiceIds)
            ->map(function (int $invoiceId) use ($invoices): string {
                $invoice = $invoices->get($invoiceId);

                return $invoice !== null ? (string) $invoice->user_id : '';
            })
            ->filter(fn (string $userId): bool => trim($userId) !== '')
            ->unique()
            ->values()
            ->all();
        $resolvedUserId = count($invoiceUserIds) === 1 ? $invoiceUserIds[0] : null;
        $syncAttendance = $request->boolean('sync_attendance', false);
        $markAttended = $request->boolean('mark_attended', false);
        $now = now();
        $syncInvoiceIds = array_keys($outstandingByInvoiceId);

        DB::transaction(function () use (
            $paymentLines,
            $outstandingByInvoiceId,
            $resolvedUserId,
            $syncInvoiceIds,
            $selectedTicketIds,
            $attendedTicketIds,
            $syncAttendance,
            $markAttended,
            $now
        ): void {
            $remainingByInvoice = $outstandingByInvoiceId;

            foreach ($paymentLines as $line) {
                $lineAmount = round((float) $line['amount'], 2);
                if ($lineAmount <= 0.0001) {
                    continue;
                }

                $payment = new Payment();
                $payment->kind = Payment::KIND_PAYMENT;
                $payment->user_id = $resolvedUserId;
                $payment->created_by = auth()->id();
                $payment->received_on = $line['received_on'] !== '' ? Carbon::parse($line['received_on']) : $now;
                $payment->payment_method = $line['method'];
                $payment->reference = $line['reference'] !== '' ? $line['reference'] : null;
                $payment->total_amount = $lineAmount;
                $payment->gst_amount = 0;
                $payment->notes = $line['notes'] !== '' ? $line['notes'] : null;
                $payment->save();

                $unallocatedAmount = $lineAmount;
                foreach ($remainingByInvoice as $invoiceId => $remainingBalance) {
                    if ($unallocatedAmount <= 0.0001) {
                        break;
                    }
                    if ($remainingBalance <= 0.0001) {
                        continue;
                    }

                    $allocatedAmount = round(min($remainingBalance, $unallocatedAmount), 2);
                    if ($allocatedAmount <= 0.0001) {
                        continue;
                    }

                    $payment->allocations()->create([
                        'invoice_id' => (int) $invoiceId,
                        'allocated_amount' => $allocatedAmount,
                    ]);

                    $remainingByInvoice[$invoiceId] = round($remainingBalance - $allocatedAmount, 2);
                    $unallocatedAmount = round($unallocatedAmount - $allocatedAmount, 2);
                }
            }

            $this->syncTicketInvoicesFromAllocations($syncInvoiceIds);

            if ($syncAttendance) {
                if ($attendedTicketIds !== []) {
                    Ticket::query()
                        ->whereIn('id', $attendedTicketIds)
                        ->update(['attended_at' => $now]);
                }

                $toClearAttendance = array_values(array_diff($selectedTicketIds, $attendedTicketIds));
                if ($toClearAttendance !== []) {
                    Ticket::query()
                        ->whereIn('id', $toClearAttendance)
                        ->update(['attended_at' => null]);
                }

                return;
            }

            if ($markAttended && $selectedTicketIds !== []) {
                Ticket::query()
                    ->whereIn('id', $selectedTicketIds)
                    ->update(['attended_at' => $now]);
            }
        });

        session()->flash(
            'message',
            'Recorded $'.number_format($totalPaymentAmount, 2)
            .' across '.$paymentLines->count().' payment'.($paymentLines->count() === 1 ? '' : 's').'.'
        );
        session()->flash('message-title', 'Ticket payment saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    public function admin_attendance_dropin_store(Request $request, Workshop $workshop)
    {
        $validated = $request->validate([
            'child_name' => ['required', 'string', 'max:180'],
            'guardian_name' => ['required_if:kiosk,1', 'nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'media_consent' => ['nullable', 'boolean'],
            'kiosk' => ['nullable', 'boolean'],
            'submit_action' => ['nullable', 'string', Rule::in(['save', 'save_and_add_another'])],
        ]);

        $email = strtolower(trim((string) ($validated['email'] ?? '')));
        $childName = trim((string) ($validated['child_name'] ?? ''));
        $guardianName = trim((string) ($validated['guardian_name'] ?? ''));
        $phone = trim((string) ($validated['phone'] ?? ''));
        $mediaConsent = array_key_exists('media_consent', $validated)
            ? (bool) $validated['media_consent']
            : false;

        $userId = $this->resolveAttendanceUserId($email, $childName, '', $phone);

        WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'ticket_id' => null,
            'user_id' => $userId,
            'created_by' => auth()->id(),
            'source' => 'dropin',
            'child_name' => $childName !== '' ? $childName : null,
            'firstname' => null,
            'surname' => null,
            'guardian_name' => $guardianName !== '' ? $guardianName : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'media_consent' => $mediaConsent,
            'attended_at' => now(),
        ]);

        session()->flash('message', 'Your attendance has been recorded. Thank you.');
        session()->flash('message-title', 'Attendance saved');
        session()->flash('message-type', 'success');

        $routeParams = ['workshop' => $workshop];
        $isKiosk = $request->boolean('kiosk') && $workshop->registration !== 'tickets';
        if ($isKiosk) {
            $routeParams['kiosk'] = 1;
        }

        if ($isKiosk && ($validated['submit_action'] ?? 'save') === 'save_and_add_another') {
            return redirect()
                ->route('admin.workshop.attendance', $routeParams)
                ->withInput([
                    'child_name' => '',
                    'guardian_name' => $guardianName,
                    'email' => $email,
                    'phone' => $phone,
                    'media_consent' => $mediaConsent ? 1 : 0,
                ]);
        }

        return redirect()->route('admin.workshop.attendance', $routeParams);
    }

    public function admin_attendance_dropin_sync(Request $request, Workshop $workshop)
    {
        $rawEntries = collect($request->input('entries', []))
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'child_name' => trim((string) ($row['child_name'] ?? '')),
                    'guardian_name' => trim((string) ($row['guardian_name'] ?? '')),
                    'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                    'phone' => trim((string) ($row['phone'] ?? '')),
                    'media_consent' => (bool) ($row['media_consent'] ?? false),
                ];
            });

        $entries = $rawEntries
            ->filter(fn (array $row): bool => $row['child_name'] !== '')
            ->values()
            ->all();

        validator(['entries' => $entries], [
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer', 'min:0'],
            'entries.*.child_name' => ['required', 'string', 'max:180'],
            'entries.*.guardian_name' => ['nullable', 'string', 'max:160'],
            'entries.*.email' => ['nullable', 'email', 'max:255'],
            'entries.*.phone' => ['nullable', 'string', 'max:60'],
            'entries.*.media_consent' => ['nullable', 'boolean'],
        ])->validate();

        $existing = WorkshopAttendance::query()
            ->where('workshop_id', $workshop->id)
            ->whereNull('ticket_id')
            ->get()
            ->keyBy(fn (WorkshopAttendance $entry): int => (int) $entry->id);

        $retainedIds = [];

        foreach ($entries as $row) {
            $entryId = (int) $row['id'];
            $userId = $this->resolveAttendanceUserId(
                $row['email'],
                $row['child_name'],
                '',
                $row['phone']
            );

            if ($entryId > 0 && $existing->has($entryId)) {
                /** @var WorkshopAttendance $entry */
                $entry = $existing->get($entryId);
                $entry->child_name = $row['child_name'];
                $entry->guardian_name = $row['guardian_name'] !== '' ? $row['guardian_name'] : null;
                $entry->email = $row['email'] !== '' ? $row['email'] : null;
                $entry->phone = $row['phone'] !== '' ? $row['phone'] : null;
                $entry->media_consent = $row['media_consent'];
                $entry->user_id = $userId;
                $entry->save();

                $retainedIds[] = (int) $entry->id;

                continue;
            }

            $created = WorkshopAttendance::query()->create([
                'workshop_id' => $workshop->id,
                'ticket_id' => null,
                'user_id' => $userId,
                'created_by' => auth()->id(),
                'source' => 'dropin',
                'child_name' => $row['child_name'],
                'firstname' => null,
                'surname' => null,
                'guardian_name' => $row['guardian_name'] !== '' ? $row['guardian_name'] : null,
                'email' => $row['email'] !== '' ? $row['email'] : null,
                'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                'media_consent' => $row['media_consent'],
                'attended_at' => now(),
            ]);

            $retainedIds[] = (int) $created->id;
        }

        $deleteIds = $existing->keys()
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => in_array($id, $retainedIds, true))
            ->values()
            ->all();

        if ($deleteIds !== []) {
            WorkshopAttendance::query()
                ->where('workshop_id', $workshop->id)
                ->whereNull('ticket_id')
                ->whereIn('id', $deleteIds)
                ->delete();
        }

        session()->flash('message', 'Attendance records have been updated.');
        session()->flash('message-title', 'Attendance saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildAttendanceExportRows(Workshop $workshop): array
    {
        $rows = [];

        $dropIns = WorkshopAttendance::query()
            ->where('workshop_id', $workshop->id)
            ->whereNull('ticket_id')
            ->orderBy('attended_at')
            ->orderBy('id')
            ->get();

        foreach ($dropIns as $entry) {
            $childName = trim((string) ($entry->child_name ?? ''));
            if ($childName === '') {
                $childName = trim((string) (($entry->firstname ?? '').' '.($entry->surname ?? '')));
            }

            $rows[] = [
                'source' => 'dropin',
                'child_name' => $childName,
                'guardian_name' => trim((string) ($entry->guardian_name ?? '')),
                'email' => trim((string) ($entry->email ?? '')),
                'phone' => trim((string) ($entry->phone ?? '')),
                'media_consent' => $entry->media_consent ? 'Yes' : 'No',
                'ticket_reference' => '',
                'status' => 'Recorded',
                'recorded_at' => $entry->attended_at?->format('Y-m-d H:i:s') ?? '',
            ];
        }

        if ($workshop->registration === 'tickets') {
            $tickets = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->orderBy('firstname')
                ->orderBy('surname')
                ->orderBy('id')
                ->get();

            foreach ($tickets as $ticket) {
                $rows[] = [
                    'source' => 'ticket',
                    'child_name' => trim((string) (($ticket->firstname ?? '').' '.($ticket->surname ?? ''))),
                    'guardian_name' => '',
                    'email' => trim((string) ($ticket->email ?? '')),
                    'phone' => trim((string) ($ticket->phone ?? '')),
                    'media_consent' => '',
                    'ticket_reference' => (string) ($ticket->reference_code ?: $ticket->id),
                    'status' => $ticket->attended_at ? 'Attended' : 'Not marked',
                    'recorded_at' => $ticket->attended_at?->format('Y-m-d H:i:s') ?? '',
                ];
            }
        }

        return $rows;
    }

    private function syncTicketInvoicesFromAllocations(array $invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::query()->find((int) $invoiceId);
            if (! $invoice) {
                continue;
            }

            $allocated = $invoice->settledAmount();
            $invoiceTotal = $invoice->dueAmount();
            $isPaid = $allocated >= ($invoiceTotal - 0.0001);

            if ($isPaid) {
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->save();

                if ($this->invoiceHasTicketContent($invoice)) {
                    Ticket::query()
                        ->where('invoice_id', $invoice->id)
                        ->whereIn('status', [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER])
                        ->update(['status' => Ticket::STATUS_DONE]);
                }

                continue;
            }

            if ($invoice->status === Invoice::STATUS_PAID) {
                $invoice->status = Invoice::STATUS_ISSUED;
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

    public function admin_attendance_dropin_update(Request $request, Workshop $workshop, WorkshopAttendance $attendance)
    {
        abort_if($attendance->workshop_id !== $workshop->id, 404);
        abort_if($attendance->ticket_id !== null, 403);

        $validated = $request->validate([
            'child_name' => ['required', 'string', 'max:180'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'media_consent' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim((string) ($validated['email'] ?? '')));
        $childName = trim((string) ($validated['child_name'] ?? ''));
        $guardianName = trim((string) ($validated['guardian_name'] ?? ''));
        $phone = trim((string) ($validated['phone'] ?? ''));
        $mediaConsent = array_key_exists('media_consent', $validated)
            ? (bool) $validated['media_consent']
            : false;

        $attendance->child_name = $childName !== '' ? $childName : null;
        $attendance->guardian_name = $guardianName !== '' ? $guardianName : null;
        $attendance->email = $email !== '' ? $email : null;
        $attendance->phone = $phone !== '' ? $phone : null;
        $attendance->media_consent = $mediaConsent;
        $attendance->save();

        session()->flash('message', 'Attendance entry updated.');
        session()->flash('message-title', 'Attendance saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    public function admin_attendance_dropin_destroy(Workshop $workshop, WorkshopAttendance $attendance)
    {
        abort_if($attendance->workshop_id !== $workshop->id, 404);
        abort_if($attendance->ticket_id !== null, 403);

        $attendance->delete();

        session()->flash('message', 'Drop-in attendance entry removed.');
        session()->flash('message-title', 'Attendance updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    /**
     * Duplicate the specified resource.
     */
    public function admin_duplicate(Workshop $workshop)
    {
        $newWorkshop = $workshop->replicate();
        $newWorkshop->title = $newWorkshop->title.' (copy)';
        $newWorkshop->status = 'draft';
        $newWorkshop->save();

        foreach ($workshop->files()->get() as $file) {
            $newWorkshop->files()->attach($file->name);
        }
        foreach ($workshop->files('private')->get() as $file) {
            $newWorkshop->files('private')->attach($file->name, ['collection' => 'private']);
        }

        session()->flash('message', 'Workshop has been duplicated');
        session()->flash('message-title', 'Workshop duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.edit', $newWorkshop);
    }

    private function resolveAttendanceUserId(string $email, string $firstname, string $surname, string $phone): ?string
    {
        if ($email === '') {
            return null;
        }

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

    private function privateAccessSessionKey(Workshop $workshop): string
    {
        return 'workshop.private_access.'.$workshop->id;
    }

    private function normalizeWorkshopRegistrationData(array &$workshopData): void
    {
        $registration = (string) ($workshopData['registration'] ?? 'none');
        $registrationData = trim((string) ($workshopData['registration_data'] ?? ''));

        if ($registrationData === '') {
            $workshopData['registration_data'] = null;

            return;
        }

        if ($registration === 'link' && ! $this->isSafeHttpUrl($registrationData)) {
            throw ValidationException::withMessages([
                'registration_data' => 'Registration URL must be a valid http:// or https:// URL.',
            ]);
        }

        $workshopData['registration_data'] = $registrationData;
    }

    private function normalizeWorkshopTypeData(array &$workshopData): void
    {
        $type = (string) ($workshopData['type'] ?? 'online');
        if ($type !== 'physical') {
            $workshopData['location_id'] = null;

            return;
        }

        $locationId = trim((string) ($workshopData['location_id'] ?? ''));
        $workshopData['location_id'] = $locationId !== '' ? $locationId : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveWorkshopTicketEmailRecipients(Workshop $workshop): array
    {
        $tickets = Ticket::query()
            ->with('user')
            ->where('workshop_id', $workshop->id)
            ->where('status', '!=', Ticket::STATUS_HOLD)
            ->get();

        $normalized = [];
        foreach ($tickets as $ticket) {
            $addresses = [
                trim((string) ($ticket->email ?? '')),
                trim((string) ($ticket->user->email ?? '')),
            ];

            foreach ($addresses as $email) {
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $normalized[strtolower($email)] = $email;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
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

    private function groupSuggestions(): array
    {
        return UserGroup::query()
            ->select('slug')
            ->distinct()
            ->orderBy('slug')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter(fn ($slug) => $slug !== '')
            ->values()
            ->all();
    }

    private function isSafeHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST));

        return $host !== '';
    }
}
