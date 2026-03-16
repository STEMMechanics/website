<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Mail\WorkshopInterestAdminNotification;
use App\Mail\WorkshopTicketBroadcast;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PickListTemplate;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use App\Models\WorkshopInterest;
use App\Services\WorkshopTicketService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
    public function show(Request $request, Workshop $workshop, WorkshopTicketService $ticketService)
    {
        $requestedSlug = trim((string) $request->segment(2));
        if ($requestedSlug !== '' && $requestedSlug !== (string) $workshop->slug) {
            return redirect()->route('workshop.show', $workshop, 301);
        }

        if (! (bool) (auth()->user()?->isAdmin() ?? false) && ! $workshop->isPubliclyVisible()) {
            abort(404);
        }

        $ticketService->cleanupExpiredHolds($workshop);
        $availableTickets = $ticketService->availableTickets($workshop);
        $ticketPriceAmount = $ticketService->ticketPriceAmount($workshop);
        $interestCount = $workshop->interests()->count();
        $currentUserInterest = auth()->check()
            ? $workshop->interests()->where('user_id', auth()->id())->first()
            : null;
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
            'interestCount' => $interestCount,
            'currentUserInterest' => $currentUserInterest,
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

    public function interest(Request $request, Workshop $workshop): RedirectResponse
    {
        if (! (bool) (auth()->user()?->isAdmin() ?? false) && ! $workshop->isPubliclyVisible()) {
            abort(404);
        }

        if ($workshop->status !== 'open' || $workshop->registration !== 'interest') {
            abort(404);
        }

        $requiresPrivateAccessCode = $workshop->requiresPrivateAccessCode();
        $hasPrivateAccess = ! $workshop->isPrivate()
            || (bool) (auth()->user()?->isAdmin() ?? false)
            || ($requiresPrivateAccessCode && (bool) session($this->privateAccessSessionKey($workshop), false));
        if (! $hasPrivateAccess) {
            abort(404);
        }

        if (auth()->check()) {
            $action = trim((string) $request->input('action', 'add'));
            $user = $request->user();
            if (! $user instanceof User) {
                abort(403);
            }

            if ($action === 'remove') {
                WorkshopInterest::query()
                    ->where('workshop_id', $workshop->id)
                    ->where('user_id', $user->id)
                    ->delete();

                session()->flash('message', 'Your interest has been removed.');
                session()->flash('message-title', 'Interest updated');
                session()->flash('message-type', 'success');

                return redirect()->route('workshop.show', $workshop);
            }

            $interest = WorkshopInterest::query()->firstOrCreate(
                [
                    'workshop_id' => $workshop->id,
                    'user_id' => $user->id,
                ],
                [
                    'name' => trim((string) $user->getName()),
                    'email' => strtolower(trim((string) $user->email)),
                    'phone' => trim((string) ($user->phone ?? '')),
                ],
            );

            if ($interest->wasRecentlyCreated) {
                $this->notifyAdminOfWorkshopInterest($workshop, $interest);
            }

            session()->flash('message', 'Thanks, your interest has been recorded.');
            session()->flash('message-title', 'Interest recorded');
            session()->flash('message-type', 'success');

            return redirect()->route('workshop.show', $workshop);
        }

        $validated = Validator::make($request->all(), [
            'interest_name' => ['required', 'string', 'max:120'],
            'interest_email' => ['required', 'email', 'max:255'],
            'interest_phone' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $name = trim((string) ($validated['interest_name'] ?? ''));
        $email = strtolower(trim((string) ($validated['interest_email'] ?? '')));
        $phone = trim((string) ($validated['interest_phone'] ?? ''));

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser instanceof User) {
            $alreadyInterested = WorkshopInterest::query()
                ->where('workshop_id', $workshop->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($alreadyInterested) {
                session()->flash('message', 'Your interest has already been recorded.');
                session()->flash('message-title', 'Already interested');
                session()->flash('message-type', 'warning');

                return redirect()->route('workshop.show', $workshop);
            }
        }

        [$firstname, $surname] = $this->splitFullName($name);
        $userId = $this->resolveAttendanceUserId($email, $firstname, $surname, $phone);

        if ($userId === null || $userId === '') {
            throw ValidationException::withMessages([
                'interest_email' => 'We could not create a contact for this expression of interest.',
            ]);
        }

        $interest = WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        $this->notifyAdminOfWorkshopInterest($workshop, $interest);

        session()->flash('message', 'Thanks, your interest has been recorded.');
        session()->flash('message-title', 'Interest recorded');
        session()->flash('message-type', 'success');

        return redirect()->route('workshop.show', $workshop);
    }

    private function notifyAdminOfWorkshopInterest(Workshop $workshop, WorkshopInterest $interest): void
    {
        $recipients = $this->workshopInterestAdminRecipients();
        if ($recipients === []) {
            return;
        }

        $workshop->loadMissing('location');
        $adminUrl = route('admin.workshop.edit', $workshop);
        $publicUrl = route('workshop.show', $workshop);

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new WorkshopInterestAdminNotification($workshop, $interest, $adminUrl, $publicUrl)
            ))->onQueue('mail');
        }
    }

    /**
     * @return array<int, string>
     */
    private function workshopInterestAdminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
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
        $availableEftposPayments = [];
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
                $relevantInvoiceUserIds = [];

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

                    $invoiceUserId = trim((string) ($invoice->user_id ?? ''));
                    if ($invoiceUserId !== '') {
                        $relevantInvoiceUserIds[] = $invoiceUserId;
                    }
                }

                $availableEftposPayments = $this->buildAttendanceEftposPaymentOptions($relevantInvoiceUserIds);
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
                    'invoice_user_id' => $ticket->invoice ? (string) ($ticket->invoice->user_id ?? '') : '',
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
            'availableEftposPayments' => $availableEftposPayments,
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

        $rawPaymentRows = $request->input('payments', []);
        if (is_array($rawPaymentRows)) {
            $request->merge([
                'payments' => collect($rawPaymentRows)
                    ->map(function ($row): ?array {
                        if (! is_array($row)) {
                            return null;
                        }

                        return [
                            'method' => trim((string) ($row['method'] ?? '')),
                            'amount' => trim((string) ($row['amount'] ?? '')),
                            'received_on' => trim((string) ($row['received_on'] ?? '')),
                            'reference' => trim((string) ($row['reference'] ?? '')),
                            'notes' => trim((string) ($row['notes'] ?? '')),
                        ];
                    })
                    ->filter()
                    ->filter(function (array $row): bool {
                        return $row['amount'] !== ''
                            || $row['reference'] !== ''
                            || $row['notes'] !== '';
                    })
                    ->values()
                    ->all(),
            ]);
        }

        $validated = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer', 'min:1'],
            'attended_ticket_ids' => ['nullable', 'array'],
            'attended_ticket_ids.*' => ['integer', 'min:1'],
            'existing_payment_ids' => ['nullable', 'array'],
            'existing_payment_ids.*' => ['integer', 'min:1'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.received_on' => ['nullable', 'date'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string'],
            'sync_attendance' => ['nullable', 'boolean'],
            'mark_attended' => ['nullable', 'boolean'],
            'email_receipt' => ['nullable', 'boolean'],
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
        $selectedExistingPaymentIds = collect($validated['existing_payment_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($paymentLines->isEmpty() && $selectedExistingPaymentIds === []) {
            throw ValidationException::withMessages([
                'payments' => 'Select an existing EFTPOS transaction or add at least one payment amount.',
            ]);
        }

        $totalOutstanding = round((float) collect($outstandingByInvoiceId)->sum(), 2);
        $totalPaymentAmount = round((float) $paymentLines->sum('amount'), 2);
        if ($selectedExistingPaymentIds !== []) {
            $existingPayments = Payment::query()
                ->whereIn('id', $selectedExistingPaymentIds)
                ->get()
                ->keyBy(fn (Payment $payment): int => (int) $payment->id);
            $existingAvailableAmount = 0.0;

            foreach ($selectedExistingPaymentIds as $paymentId) {
                $existingPayment = $existingPayments->get($paymentId);
                if (! $existingPayment instanceof Payment) {
                    throw ValidationException::withMessages([
                        'existing_payment_ids' => 'One or more selected EFTPOS transactions could not be found.',
                    ]);
                }

                $eligibilityError = $this->attendanceExistingPaymentEligibilityError($existingPayment, $resolvedUserId);
                if ($eligibilityError !== null) {
                    throw ValidationException::withMessages([
                        'existing_payment_ids' => $eligibilityError,
                    ]);
                }

                $existingAvailableAmount += $this->attendancePaymentUnallocatedAmount($existingPayment);
            }

            $remainingAfterExisting = max(0, round($totalOutstanding - min($totalOutstanding, $existingAvailableAmount), 2));
        } else {
            $remainingAfterExisting = $totalOutstanding;
        }

        if ($totalPaymentAmount > ($remainingAfterExisting + 0.0001)) {
            throw ValidationException::withMessages([
                'payments' => 'Payment total cannot exceed remaining outstanding total of $'.number_format($remainingAfterExisting, 2).' after linked EFTPOS transactions.',
            ]);
        }

        $syncAttendance = $request->boolean('sync_attendance', false);
        $markAttended = $request->boolean('mark_attended', false);
        $emailReceipt = $request->boolean('email_receipt', false);
        $now = now();
        $syncInvoiceIds = array_keys($outstandingByInvoiceId);
        $receiptEmailPayloads = [];

        DB::transaction(function () use (
            $selectedExistingPaymentIds,
            $paymentLines,
            $outstandingByInvoiceId,
            $resolvedUserId,
            $syncInvoiceIds,
            $selectedTicketIds,
            $attendedTicketIds,
            $syncAttendance,
            $markAttended,
            $now,
            &$receiptEmailPayloads
        ): void {
            $remainingByInvoice = $outstandingByInvoiceId;

            foreach ($selectedExistingPaymentIds as $paymentId) {
                $existingPayment = Payment::query()->lockForUpdate()->find($paymentId);
                if (! $existingPayment instanceof Payment) {
                    throw ValidationException::withMessages([
                        'existing_payment_ids' => 'One or more selected EFTPOS transactions could not be found.',
                    ]);
                }

                $eligibilityError = $this->attendanceExistingPaymentEligibilityError($existingPayment, $resolvedUserId);
                if ($eligibilityError !== null) {
                    throw ValidationException::withMessages([
                        'existing_payment_ids' => $eligibilityError,
                    ]);
                }

                $availableAmount = $this->attendancePaymentUnallocatedAmount($existingPayment);
                if ($availableAmount <= 0.0001) {
                    continue;
                }

                if ($resolvedUserId !== null && trim((string) ($existingPayment->user_id ?? '')) === '') {
                    $existingPayment->user_id = $resolvedUserId;
                    $existingPayment->save();
                }

                $allocationSummary = $this->allocateAttendancePaymentAcrossInvoices($existingPayment, $remainingByInvoice, $availableAmount);
                if ($allocationSummary['allocated_total'] > 0.0001) {
                    $receiptEmailPayloads[] = [
                        'payment_id' => (int) $existingPayment->id,
                        'outstanding_before_summary' => $this->formatAttendanceReceiptMoneySummary(
                            (float) ($allocationSummary['outstanding_before_total'] ?? 0),
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                        'applied_amount_summary' => $this->formatAttendanceReceiptMoneySummary(
                            (float) $allocationSummary['allocated_total'],
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                        'status_summary' => $this->formatAttendanceReceiptOutstandingSummary(
                            (float) ($allocationSummary['outstanding_after_total'] ?? 0),
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                    ];
                }
            }

            foreach ($paymentLines as $line) {
                $lineAmount = round((float) $line['amount'], 2);
                if ($lineAmount <= 0.0001) {
                    continue;
                }

                $remainingOutstandingTotal = round((float) collect($remainingByInvoice)->sum(), 2);
                if ($lineAmount > ($remainingOutstandingTotal + 0.0001)) {
                    throw ValidationException::withMessages([
                        'payments' => 'Outstanding balance changed while saving. Refresh and try again.',
                    ]);
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

                $allocationSummary = $this->allocateAttendancePaymentAcrossInvoices($payment, $remainingByInvoice, $lineAmount);
                if ($allocationSummary['allocated_total'] > 0.0001) {
                    $receiptEmailPayloads[] = [
                        'payment_id' => (int) $payment->id,
                        'outstanding_before_summary' => $this->formatAttendanceReceiptMoneySummary(
                            (float) ($allocationSummary['outstanding_before_total'] ?? 0),
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                        'applied_amount_summary' => $this->formatAttendanceReceiptMoneySummary(
                            (float) $allocationSummary['allocated_total'],
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                        'status_summary' => $this->formatAttendanceReceiptOutstandingSummary(
                            (float) ($allocationSummary['outstanding_after_total'] ?? 0),
                            count($allocationSummary['invoice_ids'] ?? [])
                        ),
                    ];
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

        $receiptEmailPayloads = collect($receiptEmailPayloads)
            ->filter(fn (array $payload): bool => $payload['payment_id'] > 0)
            ->unique('payment_id')
            ->values()
            ->all();
        $queuedReceiptEmails = 0;
        $skippedReceiptEmails = 0;
        if ($emailReceipt) {
            foreach ($receiptEmailPayloads as $receiptPayload) {
                $payment = Payment::query()->find((int) $receiptPayload['payment_id']);
                if (! $payment instanceof Payment) {
                    continue;
                }

                if ($this->sendAttendancePaymentReceiptEmail($payment, $receiptPayload)) {
                    $queuedReceiptEmails++;
                } else {
                    $skippedReceiptEmails++;
                }
            }
        }

        $messageParts = [];
        if ($selectedExistingPaymentIds !== []) {
            $messageParts[] = 'linked '.count($selectedExistingPaymentIds).' existing EFTPOS transaction'.(count($selectedExistingPaymentIds) === 1 ? '' : 's');
        }
        if ($paymentLines->isNotEmpty()) {
            $messageParts[] = 'recorded '.$paymentLines->count().' new payment'.($paymentLines->count() === 1 ? '' : 's');
        }
        $appliedTotal = round(min($totalOutstanding, max(0, ($totalOutstanding - $remainingAfterExisting) + $totalPaymentAmount)), 2);

        session()->flash(
            'message',
            'Applied $'.number_format($appliedTotal, 2)
            .($messageParts !== [] ? ' by '.implode(' and ', $messageParts).'.' : '.')
            .($queuedReceiptEmails > 0 ? ' Queued '.number_format($queuedReceiptEmails).' receipt email'.($queuedReceiptEmails === 1 ? '' : 's').'.' : '')
            .($skippedReceiptEmails > 0 ? ' Could not email '.number_format($skippedReceiptEmails).' receipt'.($skippedReceiptEmails === 1 ? '' : 's').' because no single recipient email could be resolved.' : '')
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

    private function buildAttendanceEftposPaymentOptions(array $relevantUserIds = []): array
    {
        $relevantUserIds = collect($relevantUserIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        $query = Payment::query()
            ->with('user')
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount')
            ->withSum('refunds as refunded_amount_sum', 'total_amount')
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('payment_method', Payment::PAYMENT_METHOD_EFTPOS);

        if ($relevantUserIds !== []) {
            $query->where(function ($builder) use ($relevantUserIds): void {
                $builder->whereNull('user_id')
                    ->orWhereIn('user_id', $relevantUserIds);
            });
        } else {
            $query->whereNull('user_id');
        }

        return $query
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (Payment $payment): ?array {
                $availableAmount = max(0, round(
                    (float) $payment->total_amount
                    - (float) ($payment->allocated_amount_sum ?? 0)
                    - (float) ($payment->refunded_amount_sum ?? 0),
                    2
                ));
                if ($availableAmount <= 0.0001) {
                    return null;
                }

                return [
                    'id' => (int) $payment->id,
                    'user_id' => trim((string) ($payment->user_id ?? '')),
                    'customer_name' => $payment->user?->getName() ?? '',
                    'received_on' => $payment->received_on?->format('Y-m-d\TH:i') ?? '',
                    'received_on_display' => $payment->received_on?->format('M j, Y g:i a') ?? '-',
                    'reference' => (string) ($payment->reference ?? ''),
                    'notes' => (string) ($payment->notes ?? ''),
                    'available_amount' => $availableAmount,
                    'square_payment_id' => trim((string) ($payment->square_payment_id ?? '')),
                    'square_receipt_url' => (string) ($payment->square_receipt_url ?? ''),
                    'payment_edit_url' => route('admin.payment.edit', $payment),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function attendanceExistingPaymentEligibilityError(Payment $payment, ?string $resolvedUserId = null): ?string
    {
        if ($payment->refund_of_payment_id !== null || $payment->isRefund()) {
            return 'Selected EFTPOS transaction #'.$payment->id.' is a refund and cannot be linked here.';
        }

        if ((string) ($payment->kind ?? Payment::KIND_PAYMENT) !== Payment::KIND_PAYMENT) {
            return 'Selected EFTPOS transaction #'.$payment->id.' is not a customer payment.';
        }

        if ((string) ($payment->payment_method ?? '') !== Payment::PAYMENT_METHOD_EFTPOS) {
            return 'Selected transaction #'.$payment->id.' is not an EFTPOS payment.';
        }

        $paymentUserId = trim((string) ($payment->user_id ?? ''));
        if ($resolvedUserId !== null && $paymentUserId !== '' && $paymentUserId !== $resolvedUserId) {
            return 'Selected EFTPOS transaction #'.$payment->id.' belongs to a different customer.';
        }

        if ($this->attendancePaymentUnallocatedAmount($payment) <= 0.0001) {
            return 'Selected EFTPOS transaction #'.$payment->id.' no longer has any unallocated balance.';
        }

        return null;
    }

    private function attendancePaymentUnallocatedAmount(Payment $payment): float
    {
        $allocated = (float) $payment->allocations()->sum('allocated_amount');
        $refunded = (float) $payment->refunds()->sum('total_amount');
        $unallocatedBeforeRefund = max(0, round((float) $payment->total_amount - $allocated, 2));

        return max(0, round($unallocatedBeforeRefund - $refunded, 2));
    }

    private function allocateAttendancePaymentAcrossInvoices(Payment $payment, array &$remainingByInvoice, float $availableAmount): array
    {
        $unallocatedAmount = round(max(0, $availableAmount), 2);
        $allocatedTotal = 0.0;
        $allocatedInvoiceIds = [];
        $outstandingBeforeTotal = 0.0;
        $outstandingAfterTotal = 0.0;

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

            $allocatedInvoiceIds[] = (int) $invoiceId;
            $outstandingBeforeTotal = round($outstandingBeforeTotal + (float) $remainingBalance, 2);

            $payment->allocations()->create([
                'invoice_id' => (int) $invoiceId,
                'allocated_amount' => $allocatedAmount,
            ]);

            $remainingByInvoice[$invoiceId] = round($remainingBalance - $allocatedAmount, 2);
            $unallocatedAmount = round($unallocatedAmount - $allocatedAmount, 2);
            $allocatedTotal = round($allocatedTotal + $allocatedAmount, 2);
            $outstandingAfterTotal = round($outstandingAfterTotal + (float) $remainingByInvoice[$invoiceId], 2);
        }

        return [
            'allocated_total' => $allocatedTotal,
            'invoice_ids' => array_values(array_unique($allocatedInvoiceIds)),
            'outstanding_before_total' => $outstandingBeforeTotal,
            'outstanding_after_total' => $outstandingAfterTotal,
        ];
    }

    private function sendAttendancePaymentReceiptEmail(Payment $payment, array $emailSnapshot = []): bool
    {
        $payment->loadMissing('user', 'allocations.invoice.user', 'allocations.taxAdjustment');

        [$recipientEmail, $recipientName] = $this->resolveAttendancePaymentReceiptRecipient($payment);
        if ($recipientEmail === '') {
            return false;
        }

        [, $invoiceSummary] = $this->attendancePaymentReceiptInvoiceSummary($payment);
        $pdfBinary = $this->buildAttendancePaymentReceiptPdf($payment)->output();

        dispatch(new SendEmail($recipientEmail, new PaymentReceiptPdf(
            recipientName: $recipientName !== '' ? $recipientName : $recipientEmail,
            invoiceNumber: $invoiceSummary,
            receiptNumber: (string) $payment->id,
            amount: money(abs((float) $payment->total_amount)),
            paidOn: $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            paymentMethod: Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
            receiptUrl: (string) ($payment->square_receipt_url ?? ''),
            isRefund: $payment->isRefund(),
            pdfContent: $pdfBinary,
            pdfFilename: ($payment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').((int) $payment->id).'.pdf',
            invoiceSummary: $this->attendancePaymentReceiptItemSummary($payment),
            statusSummary: (string) ($emailSnapshot['status_summary'] ?? $this->attendancePaymentReceiptStatusSummary($payment)),
            outstandingBeforeSummary: (string) ($emailSnapshot['outstanding_before_summary'] ?? $this->attendancePaymentReceiptOutstandingBeforeSummary($payment)),
            appliedAmountSummary: (string) ($emailSnapshot['applied_amount_summary'] ?? $this->attendancePaymentReceiptAppliedAmountSummary($payment)),
            creditSummary: $this->attendancePaymentReceiptCreditSummary($payment),
        )))->onQueue('mail');

        return true;
    }

    private function resolveAttendancePaymentReceiptRecipient(Payment $payment): array
    {
        $payment->loadMissing('user', 'allocations.invoice.user');

        $invoiceRecipients = $payment->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice instanceof Invoice)
            ->map(function ($allocation): array {
                $invoice = $allocation->invoice;
                $invoiceUser = $invoice->user;
                $email = strtolower(trim((string) ($invoice->billing_email ?: ($invoiceUser instanceof User ? $invoiceUser->email : ''))));
                $name = trim((string) ($invoice->billing_name ?: ($invoiceUser instanceof User ? $invoiceUser->getName() : '')));

                return [
                    'email' => $email,
                    'name' => $name,
                ];
            })
            ->filter(fn (array $recipient): bool => $recipient['email'] !== '')
            ->unique('email')
            ->values();

        if ($invoiceRecipients->count() === 1) {
            $recipient = $invoiceRecipients->first();

            return [
                (string) ($recipient['email'] ?? ''),
                (string) ($recipient['name'] ?? ''),
            ];
        }

        if ($invoiceRecipients->count() > 1) {
            return ['', ''];
        }

        $paymentUser = $payment->user;

        return [
            strtolower(trim((string) ($paymentUser instanceof User ? $paymentUser->email : ''))),
            trim((string) ($paymentUser instanceof User ? $paymentUser->getName() : '')),
        ];
    }

    private function attendancePaymentReceiptInvoiceSummary(Payment $payment): array
    {
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

        if (! $hasTaxAdjustmentAllocations && $invoiceNumbers->isNotEmpty()) {
            $invoiceLabel = $invoiceNumbers->count() > 1 ? 'Invoice Numbers' : 'Invoice Number';
            $invoiceSummary = $invoiceNumbers->implode(', ');
        }

        return [$invoiceLabel, $invoiceSummary];
    }

    private function attendancePaymentReceiptItemSummary(Payment $payment): ?string
    {
        $invoices = $this->attendancePaymentReceiptAllocatedInvoices($payment);
        if ($invoices->isEmpty()) {
            return null;
        }

        $lineSummaries = $invoices
            ->flatMap(function (Invoice $invoice) {
                $invoice->loadMissing('lines');

                return $invoice->lines
                    ->map(function ($line): ?array {
                        $description = preg_replace('/\s+/', ' ', trim((string) ($line->description ?? ''))) ?? '';
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
                    ->values()
                    ->all();
            })
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
            $ticketCount = Ticket::query()
                ->whereIn('invoice_id', $invoices->pluck('id')->all())
                ->count();
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

    private function attendancePaymentReceiptStatusSummary(Payment $payment): ?string
    {
        $invoices = $this->attendancePaymentReceiptAllocatedInvoices($payment);
        if ($invoices->isEmpty()) {
            return null;
        }

        $outstanding = round((float) $invoices->sum(fn (Invoice $invoice): float => $invoice->outstandingAmount()), 2);
        if ($outstanding <= 0.0001) {
            return $invoices->count() === 1
                ? 'Paid in full'
                : 'Paid in full across '.$invoices->count().' linked invoices';
        }

        return $invoices->count() === 1
            ? money($outstanding).' still owing'
            : money($outstanding).' still owing across '.$invoices->count().' linked invoices';
    }

    private function attendancePaymentReceiptOutstandingBeforeSummary(Payment $payment): ?string
    {
        $invoices = $this->attendancePaymentReceiptAllocatedInvoices($payment);
        if ($invoices->isEmpty()) {
            return null;
        }

        $outstandingBefore = round((float) $invoices->sum(
            fn (Invoice $invoice): float => $invoice->outstandingAmount((int) $payment->id)
        ), 2);

        return $invoices->count() === 1
            ? money($outstandingBefore)
            : money($outstandingBefore).' across '.$invoices->count().' linked invoices';
    }

    private function attendancePaymentReceiptAppliedAmountSummary(Payment $payment): ?string
    {
        $invoices = $this->attendancePaymentReceiptAllocatedInvoices($payment);
        if ($invoices->isEmpty()) {
            return null;
        }

        $appliedAmount = round((float) $payment->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice instanceof Invoice)
            ->sum('allocated_amount'), 2);

        return $invoices->count() === 1
            ? money($appliedAmount)
            : money($appliedAmount).' across '.$invoices->count().' linked invoices';
    }

    private function attendancePaymentReceiptCreditSummary(Payment $payment): ?string
    {
        $creditAmount = $this->attendancePaymentUnallocatedAmount($payment);
        if ($creditAmount <= 0.0001) {
            return null;
        }

        return 'You now have '.money($creditAmount).' sitting in credit on your account. Please contact us to discuss your options.';
    }

    private function formatAttendanceReceiptMoneySummary(float $amount, int $invoiceCount): string
    {
        return $invoiceCount <= 1
            ? money($amount)
            : money($amount).' across '.$invoiceCount.' linked invoices';
    }

    private function formatAttendanceReceiptOutstandingSummary(float $outstandingAmount, int $invoiceCount): string
    {
        if ($outstandingAmount <= 0.0001) {
            return $invoiceCount <= 1
                ? 'This invoice is now paid in full.'
                : 'These linked invoices are now paid in full.';
        }

        return $invoiceCount <= 1
            ? 'There is '.money($outstandingAmount).' now remaining on this invoice.'
            : 'There is '.money($outstandingAmount).' now remaining across these linked invoices.';
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function attendancePaymentReceiptAllocatedInvoices(Payment $payment)
    {
        return $payment->allocations
            ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice instanceof Invoice)
            ->map(fn ($allocation) => $allocation->invoice)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function buildAttendancePaymentReceiptPdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Payment receipt PDF generation requires barryvdh/laravel-dompdf.');
        }

        [$invoiceLabel, $invoiceSummary] = $this->attendancePaymentReceiptInvoiceSummary($payment);

        $amountRaw = (float) $payment->total_amount;
        $isRefund = $payment->isRefund();
        $gatewayProcessedAtRaw = trim((string) ($payment->square_gateway_updated_at ?? $payment->square_gateway_created_at ?? ''));
        $gatewayProcessedAt = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAt = Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (\Throwable) {
                $gatewayProcessedAt = '';
            }
        }

        return DomPdf::loadView('pdf.payment-receipt', [
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

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, static fn ($part): bool => trim((string) $part) !== ''));
        if ($parts === []) {
            return ['', ''];
        }

        $firstname = (string) array_shift($parts);
        $surname = trim(implode(' ', $parts));

        return [$firstname, $surname];
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
