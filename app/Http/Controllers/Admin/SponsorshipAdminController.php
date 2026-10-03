<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ManualSponsorSupport;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\SiteOption;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipOption;
use App\Models\SponsorshipPayment;
use App\Models\SponsorshipProject;
use App\Models\SponsorshipRecognitionLevel;
use App\Models\User;
use App\Services\SponsorshipService;
use App\Support\WebsiteUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SponsorshipAdminController extends Controller
{
    public function index(Request $request): View
    {
        return $this->sponsorships($request);
    }

    public function updateBitcoinSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bitcoin_enabled' => ['nullable', 'boolean'],
            'bitcoin_receive_address' => ['nullable', 'required_if:bitcoin_enabled,1', 'string', 'max:255'],
        ]);

        $enabled = $request->boolean('bitcoin_enabled');
        $address = trim((string) ($validated['bitcoin_receive_address'] ?? ''));
        $previous = [
            'enabled' => SiteOption::value('sponsorship.bitcoin.enabled', '0'),
            'receive_address' => SiteOption::value('sponsorship.bitcoin.receive-address', ''),
        ];

        $enabledOption = SiteOption::query()->updateOrCreate(
            ['name' => 'sponsorship.bitcoin.enabled'],
            ['value' => $enabled ? '1' : '0'],
        );
        SiteOption::query()->updateOrCreate(
            ['name' => 'sponsorship.bitcoin.receive-address'],
            ['value' => $address],
        );
        $this->audit('sponsorship.btc.updated', $enabledOption, $previous, [
            'enabled' => $enabled ? '1' : '0',
            'receive_address' => $address,
        ]);

        return back()->with('message', 'STEMMechanics Bitcoin support settings saved.');
    }

    public function options(): View
    {
        $primary = $this->primaryProject();

        return view('admin.sponsorship.options', [
            'primary' => $primary,
            'options' => $this->orderedOptions($primary),
            'levels' => SponsorshipRecognitionLevel::query()->whereNull('project_id')->orderByDesc('minimum_total')->orderBy('sort_order')->get(),
            'bitcoinEnabled' => SiteOption::booleanValue('sponsorship.bitcoin.enabled'),
            'bitcoinAddress' => trim((string) SiteOption::value('sponsorship.bitcoin.receive-address', '')),
        ]);
    }

    public function editCheckoutSettings(): View
    {
        return view('admin.sponsorship.checkout-editor-page', ['project' => $this->primaryProject()]);
    }

    public function updateCheckoutSettings(Request $request): JsonResponse
    {
        $project = $this->primaryProject();
        $validated = $request->validate([
            'sponsorship_enabled' => ['nullable', 'boolean'],
            'allow_custom_amount' => ['nullable', 'boolean'],
            'custom_amount_min' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'custom_amount_max' => ['required', 'numeric', 'gte:custom_amount_min', 'max:1000000'],
        ]);
        $before = $project->only(['sponsorship_enabled', 'allow_custom_amount', 'custom_amount_min', 'custom_amount_max']);
        $project->fill([
            'sponsorship_enabled' => $request->boolean('sponsorship_enabled'),
            'allow_custom_amount' => $request->boolean('allow_custom_amount'),
            'custom_amount_min' => $validated['custom_amount_min'],
            'custom_amount_max' => $validated['custom_amount_max'],
        ])->save();
        $this->audit('sponsor.checkout.updated', $project, $before, $project->only(array_keys($before)));

        return $this->optionListResponse('Sponsorship checkout settings saved.');
    }

    public function createOption(): View
    {
        return view('admin.sponsorship.option-editor-page', [
            'option' => new SponsorshipOption(['frequency' => 'one_time', 'enabled' => true]),
        ]);
    }

    public function editOption(SponsorshipOption $option): View
    {
        $this->ensurePrimaryOption($option);

        return view('admin.sponsorship.option-editor-page', compact('option'));
    }

    public function storeOption(Request $request): JsonResponse
    {
        $validated = $this->validateOption($request);
        $this->ensureOptionAmountAvailable($validated);
        $option = SponsorshipOption::query()->create($this->optionAttributes($validated));
        $this->audit('sponsorship.option.created', $option, null, $option->only(['label', 'additional_benefits', 'checkout_group', 'frequency', 'amount', 'recognition_enabled', 'enabled', 'sort_order']));

        return $this->optionListResponse('Sponsorship amount added.');
    }

    public function updateOption(Request $request, SponsorshipOption $option): JsonResponse
    {
        $this->ensurePrimaryOption($option);
        $validated = $this->validateOption($request);
        $this->ensureOptionAmountAvailable($validated, $option->id);
        $before = $option->only(['label', 'additional_benefits', 'checkout_group', 'frequency', 'amount', 'recognition_enabled', 'enabled', 'sort_order']);
        $option->fill($this->optionAttributes($validated))->save();
        $this->audit('sponsorship.option.updated', $option, $before, $option->only(array_keys($before)));

        return $this->optionListResponse('Sponsorship amount updated.');
    }

    public function destroyOption(SponsorshipOption $option): RedirectResponse
    {
        $this->ensurePrimaryOption($option);

        if (! $option->enabled) {
            if ($option->sponsorships()->exists()) {
                return redirect()->route('admin.sponsorship.options')->withErrors([
                    'option' => 'This archived amount is linked to historical sponsorship records and must be kept.',
                ]);
            }

            $before = $option->only(['project_id', 'label', 'frequency', 'amount', 'enabled']);
            $option->delete();
            $this->audit('sponsorship.option.deleted', $option, $before, null);

            return redirect()->route('admin.sponsorship.options')->with('message', 'Unused archived sponsorship amount deleted.');
        }

        $before = $option->only(['enabled']);
        $option->enabled = false;
        $option->save();
        $this->audit('sponsorship.option.archived', $option, $before, ['enabled' => false]);

        return redirect()->route('admin.sponsorship.options')->with('message', 'Sponsorship amount archived and removed from checkout. Existing sponsorship records are preserved.');
    }

    public function createRecognitionGroup(): View
    {
        return view('admin.sponsorship.recognition-editor-page', [
            'level' => new SponsorshipRecognitionLevel(['enabled' => true, 'minimum_total' => 0]),
        ]);
    }

    public function editRecognitionGroup(SponsorshipRecognitionLevel $level): View
    {
        $this->ensureGlobalRecognitionGroup($level);

        return view('admin.sponsorship.recognition-editor-page', compact('level'));
    }

    public function storeRecognitionGroup(Request $request): JsonResponse
    {
        $validated = $this->validateRecognitionGroup($request);
        $level = SponsorshipRecognitionLevel::query()->create($this->recognitionGroupAttributes($validated));
        $this->audit('sponsor_group.created', $level, null, $level->only(['name', 'minimum_total', 'sort_order', 'enabled']));

        return $this->recognitionGroupListResponse('Business sponsor group added.');
    }

    public function updateRecognitionGroup(Request $request, SponsorshipRecognitionLevel $level): JsonResponse
    {
        $this->ensureGlobalRecognitionGroup($level);
        $validated = $this->validateRecognitionGroup($request);
        $before = $level->only(['name', 'minimum_total', 'sort_order', 'enabled']);
        $level->fill($this->recognitionGroupAttributes($validated))->save();
        $this->audit('sponsor_group.updated', $level, $before, $level->only(array_keys($before)));

        return $this->recognitionGroupListResponse('Business sponsor group updated.');
    }

    public function destroyRecognitionGroup(SponsorshipRecognitionLevel $level): RedirectResponse
    {
        $this->ensureGlobalRecognitionGroup($level);
        $before = $level->only(['enabled']);
        $level->enabled = false;
        $level->save();
        $this->audit('sponsor_group.disabled', $level, $before, ['enabled' => false]);

        return redirect()->route('admin.sponsorship.options')->with('message', 'Business sponsor group removed from public recognition.');
    }

    public function sponsors(): RedirectResponse
    {
        return redirect()->route('admin.sponsorship.index');
    }

    public function sponsorDetail(Sponsor $sponsor): View
    {
        $identityKey = $sponsor->entityIdentityKey();
        $relatedIds = Sponsor::query()
            ->get(['id', 'user_id', 'organisation_id', 'email', 'sponsor_type', 'company_name', 'recognition_company_name', 'display_name'])
            ->filter(fn (Sponsor $candidate) => $candidate->entityIdentityKey() === $identityKey)
            ->modelKeys();
        $relatedSponsors = Sponsor::query()->whereIn('id', $relatedIds)->with([
            'user',
            'organisation.contacts',
            'sponsorships' => fn ($query) => $query->with(['project', 'option', 'payments.invoice', 'payments.payment'])->orderByDesc('created_at'),
            'manualSupports.project',
            'manualSupports.recognitionLevel',
        ])->get();
        $sponsor->setRelation('sponsorships', $relatedSponsors->flatMap->sponsorships->sortByDesc('created_at')->values());
        $sponsor->setRelation('manualSupports', $relatedSponsors->flatMap->manualSupports->sortByDesc('starts_on')->values());
        $sponsor->loadMissing(['user', 'organisation.contacts']);

        return view('admin.sponsorship.sponsors', [
            'sponsor' => $sponsor,
        ]);
    }

    public function sponsorLogo(Sponsor $sponsor): Response
    {
        $path = trim((string) $sponsor->recognition_logo_path);
        abort_unless($path !== '' && str_starts_with($path, 'sponsorship-assets/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    public function createManualSupport(Request $request): View
    {
        $sponsor = $request->filled('sponsor_id')
            ? Sponsor::query()->findOrFail((int) $request->query('sponsor_id'))
            : new Sponsor(['country' => 'Australia']);

        return view('admin.sponsorship.manual-support-edit', [
            'support' => new ManualSponsorSupport(['starts_on' => now()->toDateString(), 'project_id' => $this->primaryProject()->id]),
            'sponsor' => $sponsor,
            'users' => User::query()->with('primaryOrganisation')->orderBy('firstname')->orderBy('surname')->get(),
            'selectedUserId' => old('user_id', $sponsor->user_id ?? ''),
            'primaryProjectId' => $this->primaryProject()->id,
            'levels' => SponsorshipRecognitionLevel::query()->whereNull('project_id')->where('enabled', true)->orderByDesc('minimum_total')->get(),
            'recipientThreshold' => (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'),
        ]);
    }

    public function storeManualSupport(Request $request, SponsorshipService $service): RedirectResponse
    {
        $support = new ManualSponsorSupport();
        $invoiceNumber = $this->saveManualSupport($request, $support, $service);
        $this->audit('sponsor_support.created', $support, null, $support->only(['sponsor_id', 'sponsorship_id', 'project_id', 'recognition_level_id', 'support_method', 'support_description', 'value_amount', 'starts_on', 'ends_on']));

        return redirect()->route('admin.sponsorship.sponsor.manual-support.edit', $support)->with('message', $this->manualSupportMessage('recorded', $invoiceNumber, $request->boolean('send_invoice_email')));
    }

    public function editManualSupport(ManualSponsorSupport $support): View
    {
        $support->load('sponsor');

        return view('admin.sponsorship.manual-support-edit', [
            'support' => $support,
            'sponsor' => $support->sponsor,
            'users' => User::query()->with('primaryOrganisation')->orderBy('firstname')->orderBy('surname')->get(),
            'selectedUserId' => old('user_id', $support->sponsor->user_id ?? ''),
            'primaryProjectId' => $this->primaryProject()->id,
            'levels' => SponsorshipRecognitionLevel::query()->whereNull('project_id')->where('enabled', true)->orderByDesc('minimum_total')->get(),
            'recipientThreshold' => (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'),
        ]);
    }

    public function updateManualSupport(Request $request, ManualSponsorSupport $support, SponsorshipService $service): RedirectResponse
    {
        $support->load('sponsor');
        $before = $support->only(['sponsor_id', 'sponsorship_id', 'project_id', 'recognition_level_id', 'support_method', 'support_description', 'value_amount', 'starts_on', 'ends_on']);
        $invoiceNumber = $this->saveManualSupport($request, $support, $service);
        $this->audit('sponsor_support.updated', $support, $before, $support->only(array_keys($before)));

        return redirect()->route('admin.sponsorship.sponsor.manual-support.edit', $support)->with('message', $this->manualSupportMessage('updated', $invoiceNumber, $request->boolean('send_invoice_email')));
    }

    private function saveManualSupport(Request $request, ManualSponsorSupport $support, SponsorshipService $service): ?string
    {
        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'sponsor_type' => ['required', Rule::in(['individual', 'organisation'])],
            'email' => ['nullable', Rule::requiredIf(fn (): bool => $request->boolean('send_invoice_email') && in_array((string) $request->input('support_method'), ['cheque', 'cash', 'bank_transfer'], true)), 'email', 'max:255'],
            'send_invoice_email' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'country' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'required_if:sponsor_type,organisation', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'max:20'],
            'foreign_tax_id' => ['nullable', 'string', 'max:100'],
            'non_resident_declaration' => ['nullable', 'boolean'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_address2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_postcode' => ['nullable', 'string', 'max:40'],
            'recognition_public' => ['nullable', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'website_url' => WebsiteUrl::validationRules(),
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'sponsor_id' => ['nullable', 'integer', Rule::exists('sponsors', 'id')],
            'recognition_level_id' => ['nullable', 'integer', Rule::exists('sponsorship_recognition_levels', 'id')->where(fn ($query) => $query->whereNull('project_id'))],
            'support_method' => ['required', Rule::in(['cheque', 'cash', 'bank_transfer', 'other_benefit'])],
            'support_description' => ['nullable', 'required_if:support_method,other_benefit', 'string', 'max:500'],
            'value_amount' => ['nullable', 'required_if:support_method,cheque', 'required_if:support_method,cash', 'required_if:support_method,bank_transfer', 'numeric', 'gt:0', 'max:10000000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'internal_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $validated['company_name'] = trim((string) ($validated['company_name'] ?? ''));

        $moneySupport = in_array($validated['support_method'], ['cheque', 'cash', 'bank_transfer'], true);
        $amount = $moneySupport ? round((float) $validated['value_amount'], 2) : (isset($validated['value_amount']) ? round((float) $validated['value_amount'], 2) : null);
        $startsOn = \Illuminate\Support\Carbon::parse($validated['starts_on'])->toDateString();
        $selectedProjectId = $support->project_id ?: $this->primaryProject()->id;
        $email = strtolower(trim((string) ($validated['email'] ?? '')));
        $sponsor = null;
        if ($support->exists) {
            $sponsor = Sponsor::query()->findOrFail($support->sponsor_id);
        } elseif (isset($validated['sponsor_id'])) {
            $sponsor = Sponsor::query()->findOrFail($validated['sponsor_id']);
        } elseif ($validated['sponsor_type'] === 'individual' && ! empty($validated['user_id'])) {
            $sponsor = Sponsor::query()->where('user_id', $validated['user_id'])->latest('id')->first();
        }
        if (! $sponsor && $validated['sponsor_type'] === 'organisation' && $validated['company_name'] !== '') {
            $organisationName = preg_replace('/\\s+/u', ' ', trim($validated['company_name'])) ?: $validated['company_name'];
            $organisation = Organisation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($organisationName)])->first();
            $sponsor = $organisation
                ? Sponsor::query()->where('organisation_id', $organisation->id)->latest('id')->first()
                : Sponsor::query()->where('sponsor_type', 'organisation')
                    ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($organisationName)])
                    ->latest('id')->first();
        }
        if (! $sponsor && $email !== '') {
            $sponsor = Sponsor::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }
        if (! $sponsor && ! empty($validated['user_id'])) {
            $sponsor = Sponsor::query()->where('user_id', $validated['user_id'])->latest('id')->first();
        }
        $isNewSponsor = ! $sponsor;
        $sponsor ??= new Sponsor();
        if ($support->sponsorship_id && (
            $validated['support_method'] !== $support->support_method
            || ($amount !== null ? number_format($amount, 2, '.', '') : null) !== ($support->value_amount !== null ? number_format((float) $support->value_amount, 2, '.', '') : null)
            || $startsOn !== $support->starts_on?->format('Y-m-d')
            || (string) ($selectedProjectId ?? '') !== (string) ($support->project_id ?? '')
        )) {
            throw ValidationException::withMessages(['support_method' => 'The method, value, start date and support area are locked after the invoice is issued. Add another support record for a new payment.']);
        }

        $threshold = max(0, (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'));
        if ($moneySupport && $amount >= $threshold) {
            $missing = [];
            foreach (['billing_address', 'billing_city', 'billing_state', 'billing_postcode'] as $field) {
                $recipientValue = trim((string) ($validated[$field] ?? '')) ?: trim((string) $sponsor->{$field});
                if ($recipientValue === '') $missing[$field] = 'Recipient address details are required for this amount.';
            }
            $companyNameForInvoice = trim((string) ($validated['company_name'] ?? '')) ?: trim((string) $sponsor->company_name);
            if ($validated['sponsor_type'] === 'organisation' && $companyNameForInvoice === '') {
                $missing['company_name'] = 'Organisation name is required for this amount.';
            }
            if ($missing !== []) throw ValidationException::withMessages($missing);
        }

        if ($moneySupport && ! in_array(strtolower(trim($validated['country'])), ['australia', 'au', 'aus'], true)
            && SiteOption::booleanValue('sponsorship.tax.gst-free-exports-enabled', true)
            && ! $request->boolean('non_resident_declaration')
            && ! $sponsor->non_resident_declaration) {
            throw ValidationException::withMessages(['non_resident_declaration' => 'Confirm the overseas sponsor is a non-resident before applying the GST-free export rules.']);
        }

        $preserveExistingProfile = $sponsor->exists && ! $support->exists;
        $sponsorBefore = $sponsor->exists ? $sponsor->only(['contact_name', 'sponsor_type', 'company_name', 'country', 'recognition_public', 'display_name', 'website_url', 'recognition_logo_path']) : null;
        $preserveExistingRecognition = $sponsor->exists && ! $support->exists && ! $request->boolean('recognition_public')
            && trim((string) ($validated['display_name'] ?? '')) === ''
            && trim((string) ($validated['website_url'] ?? '')) === ''
            && ! $request->hasFile('logo');
        $recognitionPublic = $request->boolean('recognition_public') || ($preserveExistingRecognition && $sponsor->recognition_public);
        $displayName = trim((string) ($validated['display_name'] ?? ''));
        if ($preserveExistingRecognition) {
            $displayName = $displayName !== '' ? $displayName : trim((string) ($sponsor->display_name ?: ($sponsor->sponsor_type === 'organisation' ? $sponsor->company_name : '')));
        }
        if ($recognitionPublic && $displayName === '' && $validated['sponsor_type'] === 'individual') {
            throw ValidationException::withMessages(['display_name' => 'Add the name to show publicly before enabling public recognition.']);
        }
        if ($recognitionPublic && $displayName === '') {
            $displayName = $validated['sponsor_type'] === 'organisation'
                ? $this->nullable($validated['company_name'] ?? null)
                : null;
            if ($displayName === '') {
                throw ValidationException::withMessages(['display_name' => 'Add the name to show publicly before enabling public recognition.']);
            }
        }

        $country = trim($validated['country']);
        $isAustralianCountry = in_array(strtolower($country), ['australia', 'au', 'aus'], true);
        $abn = preg_replace('/\s+/', '', (string) ($validated['abn'] ?? ''));
        if ($isAustralianCountry && $validated['sponsor_type'] === 'organisation' && $abn !== '' && ! preg_match('/^[0-9]{11}$/', $abn)) {
            throw ValidationException::withMessages(['abn' => 'Enter an 11-digit ABN.']);
        }

        $organisation = $service->syncSponsorOrganisationProfile($sponsor, array_merge($validated, [
            'recognition_public' => $recognitionPublic,
            'website_url' => $preserveExistingRecognition && trim((string) ($validated['website_url'] ?? '')) === '' ? $sponsor->website_url : ($validated['website_url'] ?? null),
        ]), $validated['user_id'] ?? $sponsor->user_id, true);

        $sponsor->fill([
            'user_id' => $validated['user_id'] ?? $sponsor->user_id,
            'organisation_id' => $organisation?->id,
            'email' => $email !== '' ? $email : $sponsor->email,
            'contact_name' => trim($validated['contact_name']),
            'sponsor_type' => $validated['sponsor_type'],
            'company_name' => null,
            'country' => $country,
            'abn' => null,
            'foreign_tax_id' => $organisation || $isAustralianCountry ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['foreign_tax_id'] ?? '')) === '') ? $sponsor->foreign_tax_id : ($validated['foreign_tax_id'] ?? null)),
            'non_resident_declaration' => ! $isAustralianCountry && ($request->boolean('non_resident_declaration') || ($preserveExistingProfile && $sponsor->non_resident_declaration)),
            'billing_address' => $organisation ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['billing_address'] ?? '')) === '') ? $sponsor->billing_address : ($validated['billing_address'] ?? null)),
            'billing_address2' => $organisation ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['billing_address2'] ?? '')) === '') ? $sponsor->billing_address2 : ($validated['billing_address2'] ?? null)),
            'billing_city' => $organisation ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['billing_city'] ?? '')) === '') ? $sponsor->billing_city : ($validated['billing_city'] ?? null)),
            'billing_state' => $organisation ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['billing_state'] ?? '')) === '') ? $sponsor->billing_state : ($validated['billing_state'] ?? null)),
            'billing_postcode' => $organisation ? null : $this->nullable(($preserveExistingProfile && trim((string) ($validated['billing_postcode'] ?? '')) === '') ? $sponsor->billing_postcode : ($validated['billing_postcode'] ?? null)),
            'recognition_public' => $organisation ? false : $recognitionPublic,
            'display_name' => $this->nullable($displayName),
            'recognition_company_name' => null,
            'website_url' => $organisation ? null : ($preserveExistingRecognition && trim((string) ($validated['website_url'] ?? '')) === '' ? $sponsor->website_url : $this->nullable($validated['website_url'] ?? null)),
        ]);
        $sponsor->save();

        if ($request->hasFile('logo')) {
            if ($organisation) {
                $oldLogo = $organisation->logo_path;
                $organisation->logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                $organisation->save();
            } else {
                $oldLogo = $sponsor->recognition_logo_path;
                $sponsor->recognition_logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                $sponsor->save();
            }
            if ($oldLogo) Storage::disk('local')->delete($oldLogo);
        }

        // An administrator creating or updating a manual record has reviewed
        // the supplied public name, website and logo, so approve it immediately.
        if ($organisation) {
            $organisation->sponsorship_recognition_approved_at = $recognitionPublic ? now() : null;
            $organisation->sponsorship_recognition_approved_by = $recognitionPublic ? auth()->id() : null;
            $organisation->sponsorship_recognition_approval_notified_at = null;
            $organisation->save();
        } else {
            $sponsor->recognition_approved_at = $recognitionPublic ? now() : null;
            $sponsor->recognition_approved_by = $recognitionPublic ? auth()->id() : null;
            $sponsor->recognition_approval_notified_at = null;
            $sponsor->save();
        }

        $support->fill([
            'sponsor_id' => $sponsor->id,
            'project_id' => $selectedProjectId,
            'recognition_level_id' => $validated['recognition_level_id'] ?? null,
            'support_method' => $validated['support_method'],
            'support_description' => $this->nullable($validated['support_description'] ?? null),
            'value_amount' => $amount,
            'currency' => 'AUD',
            'starts_on' => $startsOn,
            'ends_on' => $validated['ends_on'] ?? null,
            'internal_note' => $this->nullable($validated['internal_note'] ?? null),
        ]);
        if (! $support->exists) $support->created_by = auth()->id();
        $support->save();

        $invoiceNumber = null;
        if ($moneySupport) {
            if (! $support->sponsorship_id) {
                $project = $support->project_id
                    ? SponsorshipProject::query()->findOrFail($support->project_id)
                    : SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
                $sponsorship = Sponsorship::query()->create([
                    'sponsor_id' => $sponsor->id,
                    'project_id' => $project->id,
                    'frequency' => 'one_time',
                    'amount' => $amount,
                    'currency' => 'AUD',
                    'status' => Sponsorship::STATUS_PENDING,
                    'started_at' => \Illuminate\Support\Carbon::parse($startsOn)->startOfDay(),
                ]);
                $support->sponsorship_id = $sponsorship->id;
                $support->save();
            }

            $paymentMethod = match ($support->support_method) {
                'cheque' => Payment::PAYMENT_METHOD_CHEQUE,
                'bank_transfer' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                default => Payment::PAYMENT_METHOD_CASH,
            };
            $payment = $service->recordManualPayment(
                $support->sponsorship()->with(['sponsor', 'project'])->firstOrFail(),
                $paymentMethod,
                $request->boolean('send_invoice_email'),
            );
            $invoiceNumber = $payment->invoice_number;
        }

        $this->audit($isNewSponsor ? 'sponsor.manual.created' : 'sponsor.manual.updated', $sponsor, $sponsorBefore, $sponsor->only(['contact_name', 'sponsor_type', 'company_name', 'country', 'recognition_public', 'display_name', 'website_url', 'recognition_logo_path']));

        return $invoiceNumber;
    }

    public function updateSponsorRecognition(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->loadMissing('organisation');
        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $validated = $request->validate([
            'recognition_public' => ['required', 'boolean'],
            'sponsor_type' => ['required', Rule::in(['individual', 'organisation'])],
            'display_name' => ['nullable', 'string', 'max:255'],
            'website_url' => WebsiteUrl::validationRules(),
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $old = $sponsor->only(['recognition_public', 'sponsor_type', 'display_name', 'website_url', 'recognition_logo_path']);
        if ((bool) $validated['recognition_public'] && $validated['sponsor_type'] === 'individual' && trim((string) ($validated['display_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['display_name' => 'Add the name to show publicly before enabling recognition.']);
        }
        if ((bool) $validated['recognition_public'] && $validated['sponsor_type'] === 'organisation'
            && trim((string) ($sponsor->company_name ?: $sponsor->display_name)) === '') {
            throw ValidationException::withMessages(['display_name' => 'Add an organisation name before enabling public recognition.']);
        }
        $sponsor->sponsor_type = $validated['sponsor_type'];
        $sponsor->display_name = $this->nullable($validated['display_name'] ?? null);
        $sponsor->recognition_company_name = null;
        $organisation = null;
        if ($sponsor->sponsor_type === 'organisation') {
            $organisation = app(SponsorshipService::class)->syncSponsorOrganisationProfile($sponsor, [
                'sponsor_type' => 'organisation',
                'company_name' => $sponsor->organisation?->name ?: ($sponsor->getRawOriginal('company_name') ?: $sponsor->display_name),
                'recognition_public' => (bool) $validated['recognition_public'],
                'website_url' => $validated['website_url'] ?? null,
            ], $sponsor->user_id, true);
            if ($request->hasFile('logo') && $organisation) {
                $oldLogo = $organisation->logo_path;
                $organisation->logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                $organisation->save();
                if ($oldLogo && $oldLogo !== $organisation->logo_path) Storage::disk('local')->delete($oldLogo);
            }
            if ($organisation) {
                $organisation->sponsorship_recognition_approved_at = (bool) $validated['recognition_public'] ? now() : null;
                $organisation->sponsorship_recognition_approved_by = (bool) $validated['recognition_public'] ? auth()->id() : null;
                $organisation->sponsorship_recognition_approval_notified_at = null;
                $organisation->save();
            }
        } else {
            $sponsor->recognition_public = (bool) $validated['recognition_public'];
            $sponsor->website_url = $validated['website_url'] ?? null;
            if ($request->hasFile('logo')) {
                $oldLogo = $sponsor->recognition_logo_path;
                $sponsor->recognition_logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                if ($oldLogo && $oldLogo !== $sponsor->recognition_logo_path) Storage::disk('local')->delete($oldLogo);
            }
            $sponsor->recognition_approved_at = (bool) $validated['recognition_public'] ? now() : null;
            $sponsor->recognition_approved_by = (bool) $validated['recognition_public'] ? auth()->id() : null;
            $sponsor->recognition_approval_notified_at = null;
        }
        $sponsor->save();
        $this->audit('sponsor.recognition.updated', $sponsor, $old, $sponsor->only(array_keys($old)));

        return back()->with('message', 'Sponsor recognition settings updated.');
    }

    public function sponsorships(Request $request): View
    {
        $selectedStatus = $request->query('status');
        if ($selectedStatus === 'all') $selectedStatus = '';
        if (! is_string($selectedStatus) && ! $request->filled('frequency')) $selectedStatus = Sponsorship::STATUS_ACTIVE;

        $baseQuery = Sponsorship::query()->with(['sponsor.organisation', 'project', 'option'])->withCount('payments');
        if ($request->filled('ref')) $baseQuery->where('referral_source', substr(trim((string) $request->query('ref')), 0, 120));
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $baseQuery->whereHas('sponsor', fn (Builder $sponsor) => $sponsor->where(function (Builder $sponsor) use ($search): void {
                $sponsor->where('email', 'like', '%'.$search.'%')
                    ->orWhere('contact_name', 'like', '%'.$search.'%')
                    ->orWhere('company_name', 'like', '%'.$search.'%')
                    ->orWhereHas('organisation', fn (Builder $organisation) => $organisation->where('name', 'like', '%'.$search.'%'));
            }));
        }

        $allRecords = (clone $baseQuery)->orderByDesc('created_at')->get();
        $identityFor = fn (Sponsorship $record): string => $record->sponsor?->entityIdentityKey() ?? 'sponsorship:'.$record->id;
        $identityForSupport = fn (ManualSponsorSupport $support): string => $support->sponsor?->entityIdentityKey() ?? 'support:'.$support->id;
        $isCurrentSupport = fn (ManualSponsorSupport $support): bool => $support->starts_on
            && $support->starts_on->lte(today())
            && (! $support->ends_on || $support->ends_on->gte(today()));
        $allSupports = ManualSponsorSupport::query()->whereNull('sponsorship_id')->with('sponsor')
            ->with('sponsor.organisation')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->whereHas('sponsor', fn (Builder $sponsor) => $sponsor->where(function (Builder $sponsor) use ($search): void {
                    $sponsor->where('email', 'like', '%'.$search.'%')
                        ->orWhere('contact_name', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%')
                        ->orWhereHas('organisation', fn (Builder $organisation) => $organisation->where('name', 'like', '%'.$search.'%'));
                }));
            })
            ->orderByDesc('starts_on')->get();
        $statusCounts = collect([
            Sponsorship::STATUS_PENDING, Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE,
            Sponsorship::STATUS_CANCELLED, Sponsorship::STATUS_FAILED, Sponsorship::STATUS_COMPLETED,
        ])->mapWithKeys(fn (string $status) => [$status => $allRecords->where('status', $status)->map($identityFor)->unique()->count()]);
        $activeSponsorKeys = collect($allRecords->where('status', Sponsorship::STATUS_ACTIVE)->map($identityFor)->all())
            ->concat(collect($allSupports->filter($isCurrentSupport)->map($identityForSupport)->all()));
        $allSponsorKeys = collect($allRecords->map($identityFor)->all())
            ->concat(collect($allSupports->map($identityForSupport)->all()));
        $statusCounts->put(Sponsorship::STATUS_ACTIVE, $activeSponsorKeys->unique()->count());
        $statusCounts->put('all', $allSponsorKeys->unique()->count());
        $frequencyCounts = collect(['one_time', 'monthly'])->mapWithKeys(fn (string $frequency) => [
            $frequency => $allRecords->where('frequency', $frequency)->map($identityFor)->unique()->count(),
        ]);

        $visibleRecords = $allRecords
            ->when(is_string($selectedStatus) && $selectedStatus !== '', fn ($records) => $records->where('status', $selectedStatus))
            ->when($request->filled('frequency'), fn ($records) => $records->where('frequency', (string) $request->query('frequency')));
        $visibleSupports = $allSupports
            ->when(is_string($selectedStatus) && $selectedStatus !== '', fn ($supports) => $selectedStatus === Sponsorship::STATUS_ACTIVE
                ? $supports->filter($isCurrentSupport)
                : collect())
            ->when($request->filled('frequency'), fn () => collect())
            ->values();
        $financialGroups = $visibleRecords->groupBy($identityFor);
        $supportGroups = $visibleSupports->groupBy($identityForSupport);
        $identities = collect($financialGroups->keys()->all())->concat(collect($supportGroups->keys()->all()))->unique();
        $groupedSponsors = $identities->map(function (string $identity) use ($financialGroups, $supportGroups) {
            $records = ($financialGroups->get($identity) ?? collect())->sortByDesc('created_at')->values();
            $supports = ($supportGroups->get($identity) ?? collect())->sortByDesc('starts_on')->values();
            $startedDates = collect($records->pluck('started_at')->all())
                ->concat(collect($supports->pluck('starts_on')->all()))
                ->filter();

            $representativeSponsor = $records->first() !== null
                ? $records->first()->sponsor
                : $supports->first()->sponsor;

            return (object) [
                'sponsor' => $representativeSponsor,
                'records' => $records,
                'supports' => $supports,
                'payments_count' => (int) $records->sum('payments_count'),
                'latest_started_at' => $startedDates->max(),
            ];
        })->sortByDesc('latest_started_at')->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $sponsorships = new LengthAwarePaginator(
            $groupedSponsors->forPage($page, 25)->values(),
            $groupedSponsors->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.sponsorship.sponsorships', [
            'sponsorships' => $sponsorships,
            'statuses' => [Sponsorship::STATUS_PENDING, Sponsorship::STATUS_ACTIVE, Sponsorship::STATUS_PAST_DUE, Sponsorship::STATUS_CANCELLED, Sponsorship::STATUS_FAILED, Sponsorship::STATUS_COMPLETED],
            'statusCounts' => $statusCounts,
            'frequencyCounts' => $frequencyCounts,
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function payments(Request $request): View
    {
        $query = SponsorshipPayment::query()->with(['sponsorship.project', 'sponsorship.sponsor', 'invoice', 'payment']);
        if ($request->filled('status')) $query->where('status', (string) $request->query('status'));
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('square_payment_id', 'like', '%'.$search.'%')
                    ->orWhere('square_order_id', 'like', '%'.$search.'%')
                    ->orWhere('square_invoice_id', 'like', '%'.$search.'%')
                    ->orWhereHas('sponsorship.sponsor', fn (Builder $sponsor) => $sponsor
                        ->where('email', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('sponsorship.sponsor.organisation', fn (Builder $organisation) => $organisation->where('name', 'like', '%'.$search.'%'));
            });
        }

        return view('admin.sponsorship.payments', [
            'payments' => $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'statuses' => [SponsorshipPayment::STATUS_PENDING, SponsorshipPayment::STATUS_COMPLETED, SponsorshipPayment::STATUS_FAILED, SponsorshipPayment::STATUS_REFUNDED],
        ]);
    }

    public function cancel(Request $request, Sponsorship $sponsorship, SponsorshipService $service): RedirectResponse
    {
        abort_unless($sponsorship->isRecurring() && $sponsorship->status !== Sponsorship::STATUS_CANCELLED, 422);
        $before = $sponsorship->only(['status', 'next_payment_date', 'cancelled_at']);
        try {
            $service->cancel($sponsorship);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['cancellation' => 'The monthly sponsorship could not be cancelled. No local cancellation was recorded.']);
        }
        $this->audit('sponsorship.admin.cancelled', $sponsorship, $before, $sponsorship->only(array_keys($before)));
        return back()->with('message', 'Monthly sponsorship cancelled; no further payments will be scheduled.');
    }

    public function resendInvoice(SponsorshipPayment $payment, SponsorshipService $service): RedirectResponse
    {
        abort_unless($payment->status === SponsorshipPayment::STATUS_COMPLETED && $payment->invoice_id, 404);
        $service->ensureInvoicePdfAndEmail($payment, true);
        $this->audit('sponsorship.invoice.resent', $payment, null, ['invoice_number' => $payment->invoice_number]);
        return back()->with('message', 'Invoice emailed again to the sponsor.');
    }

    public function invoicePdf(SponsorshipPayment $payment, SponsorshipService $service): Response
    {
        abort_unless($payment->status === SponsorshipPayment::STATUS_COMPLETED && $payment->invoice_id, 404);
        $service->ensureInvoicePdfAndEmail($payment);
        $payment->refresh();
        abort_unless($payment->invoice_pdf_path && Storage::disk('local')->exists($payment->invoice_pdf_path), 404);
        return response()->download(Storage::disk('local')->path($payment->invoice_pdf_path), 'invoice-'.$payment->invoice_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function primaryProject(): SponsorshipProject
    {
        return SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
    }

    private function ensurePrimaryOption(SponsorshipOption $option): void
    {
        abort_unless((int) $option->project_id === (int) $this->primaryProject()->id, 404);
    }

    private function ensureGlobalRecognitionGroup(SponsorshipRecognitionLevel $level): void
    {
        abort_if($level->project_id !== null, 404);
    }

    private function orderedOptions(SponsorshipProject $project)
    {
        return SponsorshipOption::query()->where('project_id', $project->id)
            ->withCount('sponsorships')
            ->orderByRaw("CASE frequency WHEN 'one_time' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')->orderBy('amount')->get();
    }

    private function optionListResponse(string $message): JsonResponse
    {
        $project = $this->primaryProject();

        return response()->json([
            'message' => $message,
            'target' => 'sponsorship-options-list',
            'html' => view('admin.sponsorship.partials.options-list', [
                'options' => $this->orderedOptions($project),
                'project' => $project,
            ])->render(),
        ]);
    }

    private function recognitionGroupListResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'target' => 'business-sponsor-groups-list',
            'html' => view('admin.sponsorship.partials.recognition-groups-list', [
                'levels' => SponsorshipRecognitionLevel::query()->whereNull('project_id')->orderByDesc('minimum_total')->orderBy('sort_order')->get(),
            ])->render(),
        ]);
    }

    private function validateOption(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'additional_benefits' => ['nullable', 'string', 'max:2000'],
            'checkout_group' => ['required', Rule::in([
                SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT,
                SponsorshipOption::CHECKOUT_GROUP_BUSINESS,
                SponsorshipOption::CHECKOUT_GROUP_BOTH,
            ])],
            'frequency' => ['required', Rule::in(['one_time', 'monthly'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'recognition_enabled' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function ensureOptionAmountAvailable(array $validated, ?int $exceptId = null): void
    {
        $projectId = $this->primaryProject()->id;
        $duplicate = SponsorshipOption::query()->where('project_id', $projectId)
            ->where('frequency', $validated['frequency'])
            ->where('amount', $validated['amount'])
            ->when($exceptId, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['amount' => 'That amount already exists for this frequency. Edit the existing option instead.']);
        }
    }

    private function optionAttributes(array $validated): array
    {
        return [
            'project_id' => $this->primaryProject()->id,
            'label' => trim($validated['label']),
            'additional_benefits' => trim((string) ($validated['additional_benefits'] ?? '')) ?: null,
            'checkout_group' => $validated['checkout_group'],
            'frequency' => $validated['frequency'],
            'amount' => $validated['amount'],
            'recognition_enabled' => filter_var($validated['recognition_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'enabled' => filter_var($validated['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function validateRecognitionGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'minimum_total' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function recognitionGroupAttributes(array $validated): array
    {
        return [
            'project_id' => null,
            'name' => trim($validated['name']),
            'minimum_total' => $validated['minimum_total'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled' => filter_var($validated['enabled'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function manualSupportMessage(string $action, ?string $invoiceNumber, bool $sendEmail): string
    {
        $message = $action === 'recorded' ? 'Sponsor support recorded.' : 'Sponsor support updated.';

        if ($invoiceNumber === null) {
            return $message.' In-kind support does not create an invoice or receipt.';
        }

        $message .= ' Invoice/receipt number: '.$invoiceNumber.'.';

        return $message.($sendEmail ? ' Email queued for the sponsor.' : ' No email was sent.');
    }

    private function audit(string $event, object $model, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'actor_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->url(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
