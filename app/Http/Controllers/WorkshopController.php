<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\WorkshopTicketBroadcast;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use App\Services\WorkshopTicketService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $query = $query->where('status', '!=', 'draft')
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
        $query = $query->where('status', '!=', 'draft')
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
        return view('admin.workshop.edit');
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
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_if:registration,link,email,message',
            'private_code' => 'nullable|string|max:120',
            'hosted_for' => 'nullable|string|max:255',
            'max_tickets' => 'nullable|integer|min:1|required_if:registration,tickets',
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
        if (($workshopData['status'] ?? null) === 'private') {
            $workshopData['status'] = 'open';
            $workshopData['is_private'] = true;
        }
        if (($workshopData['registration'] ?? 'none') !== 'tickets') {
            $workshopData['max_tickets'] = null;
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
        if (! (bool) (auth()->user()?->isAdmin() ?? false) && $workshop->status == 'draft') {
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
            'hero_media_name' => 'required|exists:media,name',
            'registration_data' => 'required_if:registration,link,email,message',
            'private_code' => 'nullable|string|max:120',
            'hosted_for' => 'nullable|string|max:255',
            'max_tickets' => 'nullable|integer|min:1|required_if:registration,tickets',
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
        if (($workshopData['status'] ?? null) === 'private') {
            $workshopData['status'] = 'open';
            $workshopData['is_private'] = true;
        }
        if (($workshopData['registration'] ?? 'none') !== 'tickets') {
            $workshopData['max_tickets'] = null;
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

        $workshop->update($workshopData);
        $workshop->updateFiles($request->input('files'));
        $workshop->updateFiles($request->input('private_files'), 'private');
        if (($workshopData['registration'] ?? 'none') !== 'tickets') {
            $workshop->tickets()->delete();
        }

        session()->flash('message', 'Workshop has been updated');
        session()->flash('message-title', 'Workshop updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.index');
    }

    public function privateAccess(Request $request, Workshop $workshop): RedirectResponse
    {
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

        session()->flash('message', 'Email sent to '.count($recipients).' recipient' . (count($recipients) == 1 ? '' : 's') . '.');
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
        $hasIssuedTickets = $workshop->registration === 'tickets'
            && Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->where('status', '!=', Ticket::STATUS_HOLD)
                ->exists();
        if ($hasIssuedTickets) {
            session()->flash('message', 'This workshop cannot be deleted because issued tickets exist (including cancelled/reissued records).');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.workshop.edit', $workshop);
        }

        $workshop->tickets()->delete();
        $workshop->delete();
        session()->flash('message', 'Workshop has been deleted');
        session()->flash('message-title', 'Workshop deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.workshop.index');
    }

    public function admin_attendance(Workshop $workshop): Response|\Illuminate\Contracts\View\View
    {
        $activeTickets = collect();
        if ($workshop->registration === 'tickets') {
            $activeTickets = Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->orderBy('firstname')
                ->orderBy('surname')
                ->orderBy('id')
                ->get();
        }

        $dropIns = WorkshopAttendance::query()
            ->with('user')
            ->where('workshop_id', $workshop->id)
            ->whereNull('ticket_id')
            ->orderByDesc('attended_at')
            ->orderByDesc('id')
            ->get();

        return response()->view('admin.workshop.attendance', [
            'workshop' => $workshop->loadMissing('location'),
            'activeTickets' => $activeTickets,
            'dropIns' => $dropIns,
        ]);
    }

    public function admin_attendance_tickets(Request $request, Workshop $workshop)
    {
        $validated = $request->validate([
            'attended_ticket_ids' => ['nullable', 'array'],
            'attended_ticket_ids.*' => ['integer'],
        ]);

        if ($workshop->registration !== 'tickets') {
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
        $allActiveIds = (clone $query)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validSelected = array_values(array_intersect($selectedIds, $allActiveIds));
        $toClear = array_values(array_diff($allActiveIds, $validSelected));

        if ($validSelected !== []) {
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $validSelected)
                ->update(['attended_at' => now()]);
        }

        if ($toClear !== []) {
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->whereIn('id', $toClear)
                ->update(['attended_at' => null]);
        }

        session()->flash('message', 'Ticket attendance has been updated.');
        session()->flash('message-title', 'Attendance saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop.attendance', $workshop);
    }

    public function admin_attendance_dropin_store(Request $request, Workshop $workshop)
    {
        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:120'],
            'surname' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
        ]);

        $email = strtolower(trim((string) ($validated['email'] ?? '')));
        $firstname = trim((string) ($validated['firstname'] ?? ''));
        $surname = trim((string) ($validated['surname'] ?? ''));
        $phone = trim((string) ($validated['phone'] ?? ''));

        $userId = $this->resolveAttendanceUserId($email, $firstname, $surname, $phone);

        WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'ticket_id' => null,
            'user_id' => $userId,
            'created_by' => auth()->id(),
            'source' => 'dropin',
            'firstname' => $firstname !== '' ? $firstname : null,
            'surname' => $surname !== '' ? $surname : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'attended_at' => now(),
        ]);

        session()->flash('message', 'Drop-in attendance has been recorded.');
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
