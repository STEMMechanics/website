<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\SponsorshipInvoiceRequestConfirmation;
use App\Mail\SponsorshipManageLink;
use App\Models\Invoice;
use App\Models\SiteOption;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipInvoiceRequest;
use App\Models\SponsorshipMagicLink;
use App\Models\SponsorshipOption;
use App\Models\SponsorshipPayment;
use App\Models\SponsorshipProject;
use App\Models\SponsorshipRecognitionLevel;
use App\Providers\QRCodeProvider;
use App\Services\SquareApiService;
use App\Services\SponsorshipRecognitionService;
use App\Services\SponsorshipService;
use App\Support\AltchaTrust;
use App\Support\WebsiteUrl;
use GrantHolle\Altcha\Rules\ValidAltcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SponsorshipController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Older links selected a project as the thing being sponsored. Keep those
        // links working while treating the project as referral attribution only.
        $legacyProject = $request->query('project');
        if (! $request->query->has('ref') && is_string($legacyProject) && $legacyProject !== '') {
            $source = trim($legacyProject);
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/', $source)) {
                return redirect()->route('sponsor.index', ['ref' => $source]);
            }
        }

        $referralSource = $this->rememberReferralSource($request);
        $project = $this->primarySupportProject()->load(['options' => fn ($query) => $query->where('enabled', true)->orderBy('sort_order')->orderBy('amount')]);
        $communitySupportOptions = $project->options->filter(fn (SponsorshipOption $option) => in_array($option->checkout_group, [SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, SponsorshipOption::CHECKOUT_GROUP_BOTH], true));
        $businessOptions = $project->options->filter(fn (SponsorshipOption $option) => in_array($option->checkout_group, [SponsorshipOption::CHECKOUT_GROUP_BUSINESS, SponsorshipOption::CHECKOUT_GROUP_BOTH], true));
        return view('sponsorship.index', [
            'project' => $project,
            'communitySupportOptions' => $communitySupportOptions,
            'businessOptions' => $businessOptions,
            'communitySupportMinimum' => $communitySupportOptions->where('frequency', 'one_time')->min('amount'),
            'businessMinimum' => $businessOptions->where('frequency', 'one_time')->min('amount'),
            'communitySupportMonthlyAvailable' => $this->monthlyPaymentsAvailable($communitySupportOptions),
            'referralSource' => $referralSource,
            'bitcoinEnabled' => SiteOption::booleanValue('sponsorship.bitcoin.enabled'),
            'bitcoinAddress' => trim((string) SiteOption::value('sponsorship.bitcoin.receive-address', '')),
            'bitcoinQrAvailable' => SiteOption::booleanValue('sponsorship.bitcoin.enabled')
                && trim((string) SiteOption::value('sponsorship.bitcoin.receive-address', '')) !== '',
        ]);
    }

    public function sponsors(SponsorshipRecognitionService $recognition): View
    {
        $groups = $recognition->groups();

        return view('sponsorship.sponsors', [
            'groups' => $groups,
            'publicSponsors' => collect($groups)->flatten(1),
            'majorRecognitionLevel' => $this->majorRecognitionLevel(),
        ]);
    }

    private function checkoutData(SponsorshipProject $project, ?string $referralSource): array
    {
        $squareEnabled = app(SquareApiService::class)->isEnabled();

        return [
            'project' => $project,
            'squareEnabled' => $squareEnabled,
            'squareEnvironment' => config('services.square.environment', 'sandbox'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'monthlyEnabled' => $squareEnabled && $this->monthlyPaymentsAvailable($project->options),
            'user' => auth()->user(),
            'recipientThreshold' => (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'),
            'referralSource' => $referralSource,
        ];
    }

    public function communitySupport(Request $request): View
    {
        $request->session()->forget('sponsorship.complete_id');
        $referralSource = $this->rememberReferralSource($request);
        $project = $this->primarySupportProject()->load(['options' => fn ($query) => $query->where('enabled', true)->orderBy('sort_order')->orderBy('amount')]);
        $project->setRelation('options', $project->options->filter(fn (SponsorshipOption $option) => in_array($option->checkout_group, [SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, SponsorshipOption::CHECKOUT_GROUP_BOTH], true))->values());

        return view('sponsorship.community-support', array_merge(
            $this->checkoutData($project, $referralSource),
            [
                'savedFrequency' => old('frequency', 'one_time'),
                'savedOption' => old('option_id', ''),
                'savedCustomAmount' => old('custom_amount', ''),
                'customAmountMinimum' => (float) $project->custom_amount_min,
                'majorRecognitionLevel' => $this->majorRecognitionLevel(),
            ],
        ));
    }

    public function processCommunitySupport(Request $request, SponsorshipService $service): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'non_resident_declaration' => ['nullable', 'boolean'],
            'frequency' => ['required', 'in:one_time,monthly'],
            'option_id' => ['nullable', 'integer'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ]);

        $project = $this->primarySupportProject();
        $option = null;
        $amount = null;
        if (! empty($validated['option_id'])) {
            $option = SponsorshipOption::query()
                ->whereKey($validated['option_id'])
                ->where('project_id', $project->id)
                ->whereIn('checkout_group', [SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, SponsorshipOption::CHECKOUT_GROUP_BOTH])
                ->where('frequency', $validated['frequency'])
                ->where('enabled', true)
                ->first();
            if (! $option) {
                return back()->withInput()->withErrors(['option_id' => 'Choose an available sponsorship amount.']);
            }
            $amount = (float) $option->amount;
        } elseif ($project->allow_custom_amount && $validated['frequency'] === 'one_time' && isset($validated['custom_amount'])) {
            $amount = round((float) $validated['custom_amount'], 2);
            if ($amount < (float) $project->custom_amount_min || $amount > (float) $project->custom_amount_max) {
                return back()->withInput()->withErrors(['custom_amount' => 'Enter an amount within the available range.']);
            }
        } else {
            return back()->withInput()->withErrors(['option_id' => 'Choose an amount or enter a custom one-time amount.']);
        }

        if ($validated['frequency'] === 'monthly'
            && (! $this->monthlyPaymentsAvailable([$option]) || ! $option)) {
            return back()->withInput()->withErrors(['option_id' => 'Monthly sponsorship is not available for this amount. Choose another amount or make a one-time sponsorship.']);
        }

        $threshold = max(0, (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'));
        if ($amount >= $threshold) {
            $errorField = $option ? 'option_id' : 'custom_amount';
            return redirect()->route('sponsor.community-support')
                ->withInput()
                ->withErrors([$errorField => 'For a sponsorship of this size, please use Business Sponsorship so we can collect the invoice details needed.']);
        }

        $recognitionDetails = [];
        if ($option?->recognition_enabled) {
            $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
            $recognition = $request->validate([
                'recognition_public' => ['nullable', 'boolean'],
                'display_name' => ['nullable', 'string', 'max:255'],
                'website_url' => WebsiteUrl::validationRules(),
                'logo' => $this->recognitionLogoAllowed($option, (float) $amount) ? ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'] : ['nullable'],
            ]);
            $recognitionPublic = $request->boolean('recognition_public');
            $displayName = trim((string) ($recognition['display_name'] ?? ''));
            if ($recognitionPublic && $displayName === '') {
                $displayName = trim((string) ($validated['contact_name'] ?? ''));
            }
            if ($recognitionPublic && $displayName === '') {
                return back()->withInput()->withErrors(['display_name' => 'Add the name to show publicly before enabling recognition.']);
            }

            $recognitionDetails = [
                'recognition_public' => $recognitionPublic,
                'display_name' => $displayName ?: null,
                'website_url' => $recognition['website_url'] ?? null,
                'recognition_logo_temp_path' => $this->recognitionLogoAllowed($option, (float) $amount) && $request->hasFile('logo')
                    ? $request->file('logo')->store('sponsorship-assets/pending', 'local')
                    : null,
            ];
        }

        $checkout = [
            'checkout_type' => Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT,
            'sponsor_type' => 'individual',
            'details' => [
                'email' => strtolower(trim((string) $validated['email'])),
                'contact_name' => trim((string) ($validated['contact_name'] ?? '')) ?: 'Supporter',
                'company_name' => null,
                'country' => trim((string) $validated['country']),
                'non_resident_declaration' => (bool) ($validated['non_resident_declaration'] ?? false),
                'abn' => null,
                'foreign_tax_id' => null,
                'billing_address' => null,
                'billing_address2' => null,
                'billing_city' => null,
                'billing_state' => null,
                'billing_postcode' => null,
            ] + $recognitionDetails,
            'payment' => [
                'payment_method' => 'square',
                'frequency' => $validated['frequency'],
                'option_id' => $option?->id,
                'custom_amount' => $option ? null : $amount,
            ],
        ];
        $request->session()->put('sponsorship.checkout', $checkout);

        return $this->processCheckout($request, $service);
    }

    public function start(Request $request): RedirectResponse
    {
        $request->session()->forget('sponsorship.complete_id');
        $this->rememberReferralSource($request);
        if ($request->boolean('new')) {
            $this->forgetCheckout($request);
        }
        $checkout = (array) $request->session()->get('sponsorship.checkout', []);
        if (($checkout['sponsor_type'] ?? null) !== 'organisation') {
            $this->deletePendingLogo(data_get($checkout, 'details.recognition_logo_temp_path'));
            unset($checkout['details'], $checkout['payment']);
        }
        $checkout['checkout_type'] = 'business';
        $checkout['sponsor_type'] = 'organisation';
        $request->session()->put('sponsorship.checkout', $checkout);

        return redirect()->route('sponsor.details');
    }

    public function details(Request $request): View|RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        if (($checkout['sponsor_type'] ?? null) !== 'organisation' || ($checkout['checkout_type'] ?? 'business') !== 'business') {
            return redirect()->route('sponsor.start');
        }

        $user = $request->user();
        $defaults = [
            'email' => (string) ($user?->email ?? ''),
            'contact_name' => (string) ($user?->getName() ?? ''),
            'company_name' => (string) ($user?->primaryOrganisation?->name ?? ''),
            'country' => 'Australia',
            'display_name' => '',
            'recognition_public' => false,
            'website_url' => '',
        ];

        return view('sponsorship.details', [
            'details' => array_merge($defaults, (array) ($checkout['details'] ?? [])),
        ]);
    }

    public function saveDetails(Request $request): RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        $sponsorType = $checkout['sponsor_type'] ?? null;
        if ($sponsorType !== 'organisation' || ($checkout['checkout_type'] ?? 'business') !== 'business') {
            return redirect()->route('sponsor.start');
        }

        $request->merge(['sponsor_type' => $sponsorType]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'required_if:sponsor_type,organisation', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'max:20'],
            'foreign_tax_id' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:120'],
            'non_resident_declaration' => ['nullable', 'boolean'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_address2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_postcode' => ['nullable', 'string', 'max:40'],
        ]);

        $country = trim((string) $validated['country']);
        $isAustralia = $this->isAustralianCountry($country);
        $abn = preg_replace('/\s+/', '', (string) ($validated['abn'] ?? ''));
        if ($isAustralia && $sponsorType === 'organisation' && $abn !== '' && ! preg_match('/^[0-9]{11}$/', $abn)) {
            return back()->withInput()->withErrors(['abn' => 'Enter an 11-digit ABN.']);
        }
        if (! $isAustralia
            && SiteOption::booleanValue('sponsorship.tax.gst-free-exports-enabled', true)
            && ! $request->boolean('non_resident_declaration')) {
            return back()->withInput()->withErrors(['non_resident_declaration' => 'Confirm that the overseas sponsor is a non-resident.']);
        }

        $validated['country'] = $country;
        $validated['abn'] = $isAustralia && $sponsorType === 'organisation' && $abn !== '' ? $abn : null;
        $validated['foreign_tax_id'] = $isAustralia ? null : ($validated['foreign_tax_id'] ?? null);
        $validated['company_name'] = $sponsorType === 'organisation' ? trim((string) $validated['company_name']) : null;
        $previousDetails = (array) data_get($checkout, 'details', []);
        foreach (['recognition_public', 'display_name', 'website_url', 'recognition_logo_temp_path'] as $recognitionField) {
            if (array_key_exists($recognitionField, $previousDetails)) {
                $validated[$recognitionField] = $previousDetails[$recognitionField];
            }
        }
        $checkout['details'] = $validated;
        unset($checkout['payment']);
        $request->session()->put('sponsorship.checkout', $checkout);

        return redirect()->route('sponsor.payment');
    }

    public function payment(Request $request): View|RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        if (($checkout['sponsor_type'] ?? null) !== 'organisation' || ($checkout['checkout_type'] ?? 'business') !== 'business') {
            return redirect()->route('sponsor.start');
        }
        if (! is_array($checkout['details'] ?? null)) {
            return redirect()->route('sponsor.details');
        }

        $checkoutGroup = ($checkout['checkout_type'] ?? Sponsorship::CHECKOUT_TYPE_BUSINESS) === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
            ? SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT
            : SponsorshipOption::CHECKOUT_GROUP_BUSINESS;
        $project = $this->primarySupportProject()->load(['options' => fn ($query) => $query->where('enabled', true)->orderBy('sort_order')->orderBy('amount')]);
        $project->setRelation('options', $project->options->filter(fn (SponsorshipOption $option) => in_array($option->checkout_group, [$checkoutGroup, SponsorshipOption::CHECKOUT_GROUP_BOTH], true))->values());
        $customAmountMinimum = $checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT
            ? (float) $project->custom_amount_min
            : $this->businessCustomAmountMinimum($project);

        return view('sponsorship.payment', array_merge(
            $this->checkoutData($project, $this->currentReferralSource()),
            [
                'checkout' => $checkout,
                'customAmountMinimum' => $customAmountMinimum,
                'majorRecognitionLevel' => $this->majorRecognitionLevel(),
                'monthlyEnabled' => $this->hasMonthlyOption($project->options),
            ],
        ));
    }

    private function majorRecognitionLevel(): ?SponsorshipRecognitionLevel
    {
        return SponsorshipRecognitionLevel::query()
            ->whereNull('project_id')
            ->where('enabled', true)
            ->orderByDesc('minimum_total')
            ->orderBy('sort_order')
            ->first();
    }

    public function savePayment(Request $request): RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        if (! is_array($checkout['details'] ?? null)) {
            return redirect()->route('sponsor.details');
        }

        $rules = [
            'payment_method' => ['required', 'in:square,invoice'],
            'frequency' => ['required', 'in:one_time,monthly'],
            'option_id' => ['nullable', 'integer'],
            'custom_amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ];
        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha()];
        }
        $validated = $request->validate($rules);
        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        if ($validated['payment_method'] === 'invoice'
            && ! $this->isAustralianCountry(data_get($checkout, 'details.country'))) {
            return back()->withInput()->withErrors(['payment_method' => $this->invoiceUnavailableMessage()]);
        }

        if ($validated['payment_method'] === 'invoice' && $validated['frequency'] !== 'one_time') {
            return back()->withInput()->withErrors(['frequency' => 'Pay by invoice is available for one-time sponsorships.']);
        }

        $project = $this->primarySupportProject();
        $checkoutGroup = ($checkout['checkout_type'] ?? Sponsorship::CHECKOUT_TYPE_BUSINESS) === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
            ? SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT
            : SponsorshipOption::CHECKOUT_GROUP_BUSINESS;
        $customAmountMinimum = $checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT
            ? (float) $project->custom_amount_min
            : $this->businessCustomAmountMinimum($project);
        $option = null;
        $amount = null;
        if (! empty($validated['option_id'])) {
            $option = SponsorshipOption::query()
                ->whereKey($validated['option_id'])
                ->where('project_id', $project->id)
                ->whereIn('checkout_group', [$checkoutGroup, SponsorshipOption::CHECKOUT_GROUP_BOTH])
                ->where('frequency', $validated['frequency'])
                ->where('enabled', true)
                ->first();
            if (! $option) {
                return back()->withInput()->withErrors(['option_id' => 'Choose an available sponsorship amount.']);
            }
            $amount = (float) $option->amount;
        } elseif ($project->allow_custom_amount && isset($validated['custom_amount']) && $validated['frequency'] === 'one_time') {
            $amount = round((float) $validated['custom_amount'], 2);
            if ($amount < $customAmountMinimum || $amount > (float) $project->custom_amount_max) {
                return back()->withInput()->withErrors(['custom_amount' => 'Enter an amount within the available range.']);
            }
        } else {
            return back()->withInput()->withErrors(['option_id' => 'Choose an available amount.']);
        }

        if ($validated['frequency'] === 'monthly' && ! $option) {
            return back()->withInput()->withErrors(['frequency' => 'Monthly sponsorship is not configured for this option. Please choose one-time sponsorship.']);
        }

        $benefitOption = $option;
        if (! $benefitOption && $checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_BUSINESS) {
            $benefitOption = $this->businessBenefitOption($project, (float) $amount);
        }

        $threshold = max(0, (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'));
        $details = (array) $checkout['details'];

        if (! $benefitOption?->recognition_enabled) {
            $this->deletePendingLogo($details['recognition_logo_temp_path'] ?? null);
            foreach (['recognition_public', 'display_name', 'website_url', 'recognition_logo_temp_path'] as $recognitionField) {
                unset($details[$recognitionField]);
            }
        }

        if ($amount >= $threshold) {
            if ($checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT) {
                return back()->withInput()->withErrors(['option_id' => 'This amount needs business sponsorship checkout so we can collect the right invoice details.']);
            }
            $missing = [];
            foreach (['billing_address', 'billing_city', 'billing_state', 'billing_postcode'] as $field) {
                if (trim((string) ($details[$field] ?? '')) === '') {
                    $missing[$field] = 'Please add the recipient address details needed for this invoice.';
                }
            }
            if (($checkout['sponsor_type'] ?? '') === 'organisation' && trim((string) ($details['company_name'] ?? '')) === '') {
                $missing['company_name'] = 'Please enter the organisation name for this invoice.';
            }
            if ($missing !== []) {
                return redirect()->route('sponsor.details')->withErrors($missing);
            }
        }

        $checkout['details'] = $details;
        $checkout['payment'] = [
            'payment_method' => $validated['payment_method'],
            'frequency' => $validated['frequency'],
            'option_id' => $option?->id,
            'custom_amount' => $option ? null : $amount,
        ];

        if ($validated['payment_method'] === 'invoice') {
            return $this->sendInvoiceConfirmationLink($request, $checkout, $project, $benefitOption, $amount);
        }

        $request->session()->put('sponsorship.checkout', $checkout);

        return redirect()->route('sponsor.checkout');
    }

    private function sendInvoiceConfirmationLink(
        Request $request,
        array $checkout,
        SponsorshipProject $project,
        ?SponsorshipOption $option,
        float $amount,
    ): RedirectResponse {
        $details = (array) ($checkout['details'] ?? []);
        $email = strtolower(trim((string) data_get($details, 'email')));
        $frequency = (string) data_get($checkout, 'payment.frequency', 'one_time');
        $rawToken = Str::random(64);
        SponsorshipInvoiceRequest::query()->create([
            'email' => $email,
            'token_hash' => hash('sha256', $rawToken),
            'payload' => [
                'sponsor_type' => $checkout['sponsor_type'] ?? 'organisation',
                'details' => $details,
                'project_id' => $project->id,
                'option_id' => $option?->id,
                'option_label' => $option?->label,
                'recognition_enabled' => (bool) $option?->recognition_enabled,
                'frequency' => $frequency,
                'amount' => $amount,
                'currency' => $project->currency,
                'referral_source' => $this->currentReferralSource(),
            ],
            'expires_at' => now()->addMinutes(30),
        ]);

        dispatch(new SendEmail(
            $email,
            new SponsorshipInvoiceRequestConfirmation(
                confirmUrl: route('sponsor.invoice-request.confirm', ['token' => $rawToken]),
                email: $email,
                organisationName: trim((string) data_get($details, 'company_name', 'STEMMechanics')) ?: 'STEMMechanics',
                amount: 'AUD $'.number_format($amount, 2).($frequency === 'monthly' ? ' per month' : ''),
                frequency: $frequency,
            ),
        ))->onQueue('mail');

        $request->session()->forget('sponsorship.checkout');

        return redirect()->route('sponsor.invoice-request.sent');
    }

    public function bitcoin(Request $request): RedirectResponse
    {
        $this->forgetCheckout($request);

        return redirect()->to(route('sponsor.index').'#bitcoin');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        if (! is_array($checkout['details'] ?? null)) {
            return redirect()->route('sponsor.details');
        }
        if (! is_array($checkout['payment'] ?? null)) {
            return redirect()->route('sponsor.payment');
        }
        if (($checkout['payment']['payment_method'] ?? 'square') !== 'square') {
            return redirect()->route('sponsor.payment');
        }

        $project = $this->primarySupportProject()->load(['options' => fn ($query) => $query->where('enabled', true)]);
        $paymentChoice = (array) $checkout['payment'];
        $selectedOption = $project->options->firstWhere('id', (int) ($paymentChoice['option_id'] ?? 0));
        $amount = $selectedOption ? (float) $selectedOption->amount : (float) ($paymentChoice['custom_amount'] ?? 0);
        $benefitOption = $selectedOption ?? $this->businessBenefitOption($project, $amount);
        $initialMonthlyPayment = ($paymentChoice['frequency'] ?? '') === 'monthly'
            ? app(\App\Services\SponsorshipBillingScheduleService::class)->initialPayment($amount)
            : null;
        $invoiceAvailable = $this->isAustralianCountry(data_get($checkout, 'details.country'));
        return view('sponsorship.checkout', array_merge(
            $this->checkoutData($project, $this->currentReferralSource()),
            [
                'checkout' => $checkout,
                'benefitOption' => $benefitOption,
                'majorRecognitionLevel' => $this->majorRecognitionLevel(),
                'customAmountMinimum' => $this->businessCustomAmountMinimum($project),
                'initialMonthlyPaymentAmount' => $initialMonthlyPayment ? $initialMonthlyPayment['amount_cents'] / 100 : null,
                'nextMonthlyPaymentDate' => $initialMonthlyPayment['next_payment_date'] ?? null,
                'monthlyBillingLabel' => $initialMonthlyPayment['billing_label'] ?? null,
                'invoiceAvailable' => $invoiceAvailable,
            ],
        ));
    }

    public function processCheckout(Request $request, SponsorshipService $service): RedirectResponse
    {
        $checkout = $request->session()->get('sponsorship.checkout', []);
        if (! is_array($checkout['details'] ?? null) || ! is_array($checkout['payment'] ?? null)) {
            return redirect()->route('sponsor.start')->withErrors(['checkout' => 'Your sponsorship checkout has expired. Please start again.']);
        }
        $isCommunitySupportCheckout = ($checkout['checkout_type'] ?? Sponsorship::CHECKOUT_TYPE_BUSINESS) === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT;
        $paymentMethod = $isCommunitySupportCheckout
            ? 'square'
            : $request->validate(['payment_method' => ['required', 'in:square,invoice']])['payment_method'];
        if ($paymentMethod === 'invoice'
            && ! $this->isAustralianCountry(data_get($checkout, 'details.country'))) {
            return back()->withInput()->withErrors(['payment_method' => $this->invoiceUnavailableMessage()]);
        }
        if ($paymentMethod === 'invoice') {
            $invoiceRules = [];
            if (AltchaTrust::shouldRequire($request)) {
                $invoiceRules['altcha'] = ['required', new ValidAltcha()];
            }
            if ($invoiceRules !== []) {
                $request->validate($invoiceRules);
                AltchaTrust::markVerified($request);
            }

            $project = $this->primarySupportProject();
            $paymentChoice = (array) $checkout['payment'];
            $frequency = (string) ($paymentChoice['frequency'] ?? 'one_time');
            $checkoutGroup = ($checkout['checkout_type'] ?? Sponsorship::CHECKOUT_TYPE_BUSINESS) === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
                ? SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT
                : SponsorshipOption::CHECKOUT_GROUP_BUSINESS;
            $option = null;
            if (! empty($paymentChoice['option_id'])) {
                $option = SponsorshipOption::query()
                    ->whereKey($paymentChoice['option_id'])
                    ->where('project_id', $project->id)
                    ->whereIn('checkout_group', [$checkoutGroup, SponsorshipOption::CHECKOUT_GROUP_BOTH])
                    ->where('frequency', $frequency)
                    ->where('enabled', true)
                    ->first();
                if (! $option) {
                    return back()->withInput()->withErrors(['payment_method' => 'This sponsorship option is no longer available. Please choose another.']);
                }
                $amount = (float) $option->amount;
            } elseif ($project->allow_custom_amount && $frequency === 'one_time' && isset($paymentChoice['custom_amount'])) {
                $amount = round((float) $paymentChoice['custom_amount'], 2);
                $minimum = $checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT ? (float) $project->custom_amount_min : $this->businessCustomAmountMinimum($project);
                if ($amount < $minimum || $amount > (float) $project->custom_amount_max) {
                    return back()->withInput()->withErrors(['payment_method' => 'The custom sponsorship amount is no longer available. Please choose another.']);
                }
            } else {
                return back()->withInput()->withErrors(['payment_method' => 'Choose a sponsorship amount before requesting an invoice.']);
            }

            $benefitOption = $option;
            if (! $benefitOption && $checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_BUSINESS) {
                $benefitOption = $this->businessBenefitOption($project, (float) $amount);
            }

            if ($checkoutGroup === SponsorshipOption::CHECKOUT_GROUP_BUSINESS) {
                $this->captureBusinessRecognition($request, $checkout, $benefitOption, $this->recognitionLogoAllowed($benefitOption, (float) $amount));
            }
            $checkout['payment']['payment_method'] = 'invoice';
            return $this->sendInvoiceConfirmationLink($request, $checkout, $project, $benefitOption, $amount);
        }
        if (($checkout['payment']['payment_method'] ?? 'square') !== 'square') {
            return redirect()->route('sponsor.payment');
        }
        $validated = array_merge(
            (array) $checkout['details'],
            ['sponsor_type' => $checkout['sponsor_type'] ?? 'organisation'],
            (array) $checkout['payment'],
            $request->validate(['source_id' => ['required', 'string', 'max:16384']]),
        );

        $project = $this->primarySupportProject();
        $checkoutType = ($checkout['checkout_type'] ?? Sponsorship::CHECKOUT_TYPE_BUSINESS) === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
            ? Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
            : Sponsorship::CHECKOUT_TYPE_BUSINESS;
        $checkoutGroup = $checkoutType;
        $customAmountMinimum = $checkoutType === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT
            ? (float) $project->custom_amount_min
            : $this->businessCustomAmountMinimum($project);

        if (! app(SquareApiService::class)->isEnabled()
            || trim((string) config('services.square.application_id')) === ''
            || trim((string) config('services.square.location_id')) === '') {
            return back()->withInput()->withErrors(['source_id' => 'Card sponsorships are temporarily unavailable.']);
        }

        $option = null;
        $amount = null;
        if (! empty($validated['option_id'])) {
            $option = SponsorshipOption::query()
                ->whereKey($validated['option_id'])
                ->where('project_id', $project->id)
                ->whereIn('checkout_group', [$checkoutGroup, SponsorshipOption::CHECKOUT_GROUP_BOTH])
                ->where('frequency', $validated['frequency'])
                ->where('enabled', true)
                ->first();
            if (! $option) {
                return back()->withInput()->withErrors(['option_id' => 'Choose an available sponsorship amount.']);
            }
            $amount = (float) $option->amount;
        } elseif ($project->allow_custom_amount && isset($validated['custom_amount'])) {
            if ($validated['frequency'] === 'monthly') {
                return back()->withInput()->withErrors(['custom_amount' => 'Choose one of the configured monthly sponsorship amounts.']);
            }
            $amount = round((float) $validated['custom_amount'], 2);
            if ($amount < $customAmountMinimum || $amount > (float) $project->custom_amount_max) {
                return back()->withInput()->withErrors(['custom_amount' => 'Enter an amount within the configured sponsorship range.']);
            }
        } else {
            return back()->withInput()->withErrors(['custom_amount' => 'Choose an amount or enter a custom amount.']);
        }

        $threshold = max(0, (float) SiteOption::value('sponsorship.invoice.recipient-details-threshold', '1000'));
        if ($amount >= $threshold) {
            if ($checkoutType === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT) {
                return back()->withInput()->withErrors(['source_id' => 'This amount needs business sponsorship checkout so we can collect the right invoice details.']);
            }
            $missing = [];
            foreach (['billing_address', 'billing_city', 'billing_state', 'billing_postcode'] as $field) {
                if (trim((string) ($validated[$field] ?? '')) === '') $missing[$field] = 'Please provide the recipient address details needed for this invoice.';
            }
            if ($validated['sponsor_type'] === 'organisation' && trim((string) ($validated['company_name'] ?? '')) === '') {
                $missing['company_name'] = 'Please provide the organisation name needed for this invoice.';
            }
            if ($missing !== []) return back()->withInput()->withErrors($missing);
        }

        $country = trim((string) $validated['country']);
        if ($validated['frequency'] === 'monthly' && (! $option || ! $this->monthlyPaymentsAvailable([$option]))) {
            return back()->withInput()->withErrors(['option_id' => 'This monthly amount is not available. Choose another amount or make a one-time sponsorship.']);
        }
        $benefitOption = $option;
        if (! $benefitOption && $checkoutType === Sponsorship::CHECKOUT_TYPE_BUSINESS) {
            $benefitOption = $this->businessBenefitOption($project, (float) $amount);
        }
        if ($checkoutType === Sponsorship::CHECKOUT_TYPE_BUSINESS) {
            $this->captureBusinessRecognition($request, $checkout, $benefitOption, $this->recognitionLogoAllowed($benefitOption, (float) $amount));
        }
        if (! $this->isAustralianCountry($country)
            && empty($validated['non_resident_declaration'])
            && SiteOption::booleanValue('sponsorship.tax.gst-free-exports-enabled', true)) {
            return back()->withInput()->withErrors(['non_resident_declaration' => 'Confirm the sponsor is a non-resident so we can check whether GST applies.']);
        }

        $isAustralianOrganisation = $validated['sponsor_type'] === 'organisation' && $this->isAustralianCountry($country);
        $abn = isset($validated['abn']) ? preg_replace('/\s+/', '', $validated['abn']) : null;
        if ($isAustralianOrganisation && $abn !== null && $abn !== '' && ! preg_match('/^[0-9]{11}$/', $abn)) {
            return back()->withInput()->withErrors(['abn' => 'Enter an 11-digit ABN.']);
        }
        $validated['abn'] = $isAustralianOrganisation ? ($abn ?: null) : null;
        if (! $isAustralianOrganisation) {
            $validated['abn'] = null;
        }
        if (in_array(strtolower($country), ['australia', 'au', 'aus'], true)) {
            $validated['foreign_tax_id'] = null;
        }
        if ($validated['sponsor_type'] !== 'organisation') {
            $validated['company_name'] = null;
        }
        try {
            // The billing contact entered in this sponsorship is the account
            // owner for the sponsor and invoice. Do not attach another
            // person's billing details to the account that happened to start
            // the checkout while logged in.
            $sponsor = $service->createSponsor($validated);
            $sponsorship = Sponsorship::query()->create([
                'sponsor_id' => $sponsor->id,
                'project_id' => $project->id,
                'option_id' => $option?->id ?? $benefitOption?->id,
                'checkout_type' => $checkoutType,
                'invoice_recipient_customized' => false,
                'frequency' => $validated['frequency'],
                'billing_method' => 'square',
                'amount' => $amount,
                'currency' => $project->currency,
                'status' => Sponsorship::STATUS_PENDING,
                'referral_source' => $this->currentReferralSource(),
            ]);

            if ($validated['frequency'] === 'monthly') {
                $service->createMonthly($sponsorship->load(['sponsor', 'project']), $validated['source_id']);
            } else {
                $response = $service->createOneTime($sponsorship->load(['sponsor', 'project']), $validated['source_id']);
                $payment = $response['payment'] ?? [];
                if (strtoupper((string) ($payment['status'] ?? '')) !== 'COMPLETED') {
                    throw new \RuntimeException((string) data_get($payment, 'status', 'Payment was not completed.'));
                }
            }

            if ($benefitOption?->recognition_enabled) {
                $this->applyBusinessRecognition($sponsor, (array) ($checkout['details'] ?? []), $this->recognitionLogoAllowed($benefitOption, (float) $amount));
            } else {
                $this->deletePendingLogo(data_get($checkout, 'details.recognition_logo_temp_path'));
            }

            $request->session()->put('sponsorship.complete_id', $sponsorship->id);
            $request->session()->forget('sponsorship.checkout');
            $this->clearReferralSource();
            return redirect()->route('sponsor.complete');
        } catch (\Throwable $exception) {
            if (isset($sponsorship) && $sponsorship->payments()->where('status', SponsorshipPayment::STATUS_COMPLETED)->exists()) {
                $request->session()->put('sponsorship.complete_id', $sponsorship->id);
                $request->session()->forget('sponsorship.checkout');
                $this->clearReferralSource();
                report($exception);
                return redirect()->route('sponsor.complete');
            }
            if (isset($sponsorship)
                && $sponsorship->frequency === 'monthly'
                && $sponsorship->payments()->where('status', SponsorshipPayment::STATUS_PENDING)->whereNotNull('billing_period')->exists()) {
                $sponsorship->status = Sponsorship::STATUS_PENDING;
                $sponsorship->save();
                $request->session()->put('sponsorship.complete_id', $sponsorship->id);
                $request->session()->forget('sponsorship.checkout');
                $this->clearReferralSource();
                report($exception);
                return redirect()->route('sponsor.complete')->with('sponsorship_payment_pending', true);
            }
            if (isset($sponsorship)) {
                $sponsorship->status = Sponsorship::STATUS_FAILED;
                $sponsorship->save();
            }
            report($exception);
            return back()->withInput()->withErrors(['source_id' => app(SquareApiService::class)->userFacingPaymentErrorMessage($exception->getMessage())]);
        }
    }

    public function invoiceRequestSent(): View
    {
        return view('sponsorship.invoice-request-sent');
    }

    public function invoiceRequestComplete(string $token): View|RedirectResponse
    {
        $requestRecord = SponsorshipInvoiceRequest::query()
            ->with('invoice')
            ->where('token_hash', hash('sha256', $token))
            ->whereNotNull('used_at')
            ->first();

        if (! $requestRecord?->invoice) {
            return redirect()->route('sponsor.invoice-request.confirm', ['token' => $token]);
        }

        return view('sponsorship.invoice-request-complete', ['invoice' => $requestRecord->invoice]);
    }

    public function confirmInvoiceRequestForm(string $token): View
    {
        $requestRecord = SponsorshipInvoiceRequest::query()
            ->with('invoice')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        return view('sponsorship.invoice-request-confirm', [
            'requestRecord' => $requestRecord,
            'token' => $token,
        ]);
    }

    public function confirmInvoiceRequest(
        Request $request,
        string $token,
        SponsorshipService $service,
    ): RedirectResponse {
        $result = DB::transaction(function () use ($token, $service): array {
            $requestRecord = SponsorshipInvoiceRequest::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $requestRecord || $requestRecord->expires_at->isPast()) {
                return ['status' => 'expired'];
            }

            if ($requestRecord->used_at) {
                return ['status' => 'used', 'invoice_id' => $requestRecord->invoice_id];
            }

            $payload = (array) $requestRecord->payload;
            $details = (array) ($payload['details'] ?? []);
            $project = SponsorshipProject::query()
                ->whereKey((int) ($payload['project_id'] ?? 0))
                ->where('enabled', true)
                ->where('sponsorship_enabled', true)
                ->first();
            $amount = round((float) ($payload['amount'] ?? 0), 2);
            if (! $project || $amount <= 0 || trim((string) ($details['email'] ?? '')) === '') {
                return ['status' => 'expired'];
            }

            $sponsor = $service->createSponsor(
                array_merge($details, ['sponsor_type' => $payload['sponsor_type'] ?? 'organisation']),
            );
            $optionId = (int) ($payload['option_id'] ?? 0);
            $option = $optionId > 0
                ? SponsorshipOption::query()->whereKey($optionId)->where('project_id', $project->id)->first()
                : null;
            $frequency = (string) ($payload['frequency'] ?? 'one_time');
            if (! in_array($frequency, ['one_time', 'monthly'], true)) $frequency = 'one_time';
            $startedAt = null;
            $billingPeriod = null;
            $schedule = null;
            if ($frequency === 'monthly') {
                $startedAt = now(\App\Services\SponsorshipBillingScheduleService::TIMEZONE);
                $schedule = app(\App\Services\SponsorshipBillingScheduleService::class)->initialPayment(
                    $amount,
                    \Carbon\CarbonImmutable::parse($startedAt, \App\Services\SponsorshipBillingScheduleService::TIMEZONE),
                );
                $billingPeriod = $startedAt->toDateString();
            }
            $sponsorship = Sponsorship::query()->create([
                'sponsor_id' => $sponsor->id,
                'project_id' => $project->id,
                'option_id' => $option?->id,
                'checkout_type' => 'business',
                'invoice_recipient_customized' => false,
                'frequency' => $frequency,
                'billing_method' => 'invoice',
                'amount' => $amount,
                'currency' => $project->currency,
                'status' => Sponsorship::STATUS_PENDING,
                'referral_source' => $payload['referral_source'] ?? null,
                'started_at' => $startedAt,
                'billing_anchor_date' => $schedule['billing_anchor_date'] ?? null,
                'next_payment_date' => $schedule['next_payment_date'] ?? null,
            ]);
            $sponsorshipPayment = $service->createUnpaidInvoice(
                $sponsorship->load(['sponsor', 'project']),
                $billingPeriod,
            );

            $requestRecord->used_at = now();
            $requestRecord->invoice_id = $sponsorshipPayment->invoice_id;
            $requestRecord->save();

            return [
                'status' => 'created',
                'invoice_id' => (int) $sponsorshipPayment->invoice_id,
                'payment_id' => (int) $sponsorshipPayment->id,
                'sponsor_id' => (int) $sponsor->id,
                'option_id' => $option?->id,
                'amount' => $amount,
                'recognition_enabled' => (bool) ($payload['recognition_enabled'] ?? $option?->recognition_enabled),
                'details' => $details,
            ];
        }, 3);

        if (($result['status'] ?? '') === 'used' && ! empty($result['invoice_id'])) {
            return redirect()->route('sponsor.invoice-request.complete', ['token' => $token]);
        }

        if (($result['status'] ?? '') === 'expired') {
            return redirect()->route('sponsor.invoice-request.confirm', ['token' => $token]);
        }

        if (! empty($result['recognition_enabled'])) {
            $sponsor = Sponsor::query()->findOrFail((int) $result['sponsor_id']);
            $option = ! empty($result['option_id']) ? SponsorshipOption::query()->find((int) $result['option_id']) : null;
            $this->applyBusinessRecognition($sponsor, (array) ($result['details'] ?? []), $this->recognitionLogoAllowed($option, (float) data_get($result, 'amount', 0)));
        } else {
            $this->deletePendingLogo(data_get($result, 'details.recognition_logo_temp_path'));
        }

        $sponsorshipPayment = SponsorshipPayment::query()->findOrFail((int) $result['payment_id']);
        if (! $service->emailUnpaidInvoice($sponsorshipPayment)) {
            report(new \RuntimeException('Unable to queue the sponsorship invoice email.'));
        }

        $this->clearReferralSource();

        return redirect()->route('sponsor.invoice-request.complete', ['token' => $token]);
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $id = (int) $request->session()->get('sponsorship.complete_id', 0);
        $sponsorship = $id > 0 ? Sponsorship::query()->with(['project', 'sponsor', 'payments.invoice'])->find($id) : null;
        if (! $sponsorship) return redirect()->route('sponsor.index');

        $paidPayment = $sponsorship->payments->first(fn (SponsorshipPayment $payment) => $payment->status === SponsorshipPayment::STATUS_COMPLETED && $payment->invoice_id);

        return view('sponsorship.complete', compact('sponsorship', 'paidPayment'));
    }

    public function saveCommunitySupportInvoiceRecipient(Request $request, SponsorshipService $service): RedirectResponse
    {
        $id = (int) $request->session()->get('sponsorship.complete_id', 0);
        $sponsorship = Sponsorship::query()->with('sponsor')->whereKey($id)->firstOrFail();
        abort_unless($sponsorship->checkout_type === Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT, 404);
        $payment = $sponsorship->payments()
            ->where('status', SponsorshipPayment::STATUS_COMPLETED)
            ->whereNotNull('invoice_id')
            ->with('invoice')
            ->latest('paid_at')->firstOrFail();

        $validated = $request->validate([
            'sponsor_type' => ['required', 'in:individual,organisation'],
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'required_if:sponsor_type,organisation', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'max:20'],
            'foreign_tax_id' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_address2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_postcode' => ['nullable', 'string', 'max:40'],
        ]);

        $sponsor = $sponsorship->sponsor;
        $isAustralia = $this->isAustralianCountry($sponsor->country);
        $abn = preg_replace('/\s+/', '', (string) ($validated['abn'] ?? ''));
        if ($isAustralia && $validated['sponsor_type'] === 'organisation' && $abn !== '' && ! preg_match('/^[0-9]{11}$/', $abn)) {
            return back()->withInput()->withErrors(['abn' => 'Enter an 11-digit ABN.']);
        }

        DB::transaction(function () use ($sponsor, $sponsorship, $payment, $validated, $isAustralia, $abn, $service): void {
            $organisation = $validated['sponsor_type'] === 'organisation'
                ? $service->syncSponsorOrganisationProfile($sponsor, [
                    'sponsor_type' => 'organisation',
                    'company_name' => trim((string) $validated['company_name']),
                    'country' => $sponsor->country,
                    'abn' => $isAustralia && $abn !== '' ? $abn : null,
                    'foreign_tax_id' => $isAustralia ? null : ($validated['foreign_tax_id'] ?? null),
                    'billing_address' => $validated['billing_address'] ?? null,
                    'billing_address2' => $validated['billing_address2'] ?? null,
                    'billing_city' => $validated['billing_city'] ?? null,
                    'billing_state' => $validated['billing_state'] ?? null,
                    'billing_postcode' => $validated['billing_postcode'] ?? null,
                ], $sponsor->user_id)
                : null;

            $sponsor->fill([
                'organisation_id' => $organisation?->id,
                'contact_name' => trim($validated['contact_name']),
                'sponsor_type' => $validated['sponsor_type'],
                'company_name' => null,
                'abn' => null,
                'foreign_tax_id' => $organisation || $isAustralia ? null : ($validated['foreign_tax_id'] ?? null),
                'billing_address' => $organisation ? null : ($validated['billing_address'] ?? null),
                'billing_address2' => $organisation ? null : ($validated['billing_address2'] ?? null),
                'billing_city' => $organisation ? null : ($validated['billing_city'] ?? null),
                'billing_state' => $organisation ? null : ($validated['billing_state'] ?? null),
                'billing_postcode' => $organisation ? null : ($validated['billing_postcode'] ?? null),
            ])->save();
            $sponsor->unsetRelation('organisation');

            $payment->invoice->fill([
                'billing_name' => $sponsor->contact_name,
                'billing_email' => $sponsor->email,
                'billing_company' => $sponsor->company_name,
                'billing_address' => $sponsor->billing_address,
                'billing_address2' => $sponsor->billing_address2,
                'billing_city' => $sponsor->billing_city,
                'billing_state' => $sponsor->billing_state,
                'billing_postcode' => $sponsor->billing_postcode,
                'billing_country' => $sponsor->country,
                'recipient_abn' => $sponsor->abn,
                'recipient_foreign_tax_id' => $sponsor->foreign_tax_id,
            ])->save();
            $sponsorship->invoice_recipient_customized = true;
            $sponsorship->save();
        });

        $service->refreshInvoicePdfAndEmail($payment->fresh());

        return redirect()->route('sponsor.complete')->with('message', 'Invoice details saved. The updated invoice has been emailed to you.');
    }

    public function asset(string $type, int $id): Response
    {
        $path = null;
        if ($type === 'project') {
            $path = SponsorshipProject::query()->whereKey($id)->where('enabled', true)->value('logo_path');
        } elseif ($type === 'btc') {
            abort_unless(SiteOption::booleanValue('sponsorship.bitcoin.enabled'), 404);
            $address = trim((string) SiteOption::value('sponsorship.bitcoin.receive-address', ''));
            abort_unless($address !== '', 404);

            $qrCode = app(QRCodeProvider::class)->getQRCodeImage('bitcoin:'.$address, 240);

            return response($qrCode, 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300',
            ]);
        } elseif ($type === 'sponsor') {
            $sponsor = Sponsor::query()->publiclyRecognized()->whereKey($id)
                ->where(fn ($query) => $query
                    ->whereHas('sponsorships.payments', fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED))
                    ->orWhereHas('manualSupports'))
                ->firstOrFail();
            $majorLevel = SponsorshipRecognitionLevel::query()
                ->whereNull('project_id')
                ->where('enabled', true)
                ->orderByDesc('minimum_total')
                ->orderBy('sort_order')
                ->first();
            $majorSponsors = $majorLevel
                ? collect(app(SponsorshipRecognitionService::class)->groups()[$majorLevel->name] ?? [])
                : collect();
            $sponsorIdentity = $sponsor->entityIdentityKey();
            abort_unless($majorSponsors->contains(fn (Sponsor $majorSponsor): bool => $majorSponsor->entityIdentityKey() === $sponsorIdentity), 404);
            $path = $sponsor->recognition_logo_path;
        }

        abort_unless(is_string($path) && str_starts_with($path, 'sponsorship-assets/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=3600']);
    }

    public function manageRequest(): View
    {
        return view('sponsorship.manage-request');
    }

    public function sendManageLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim((string) $validated['email']));
        $sponsor = Sponsor::query()->whereRaw('LOWER(email) = ?', [$email])
            ->whereHas('sponsorships.payments', fn ($query) => $query->where('status', SponsorshipPayment::STATUS_COMPLETED))
            ->latest('id')->first();

        if ($sponsor) {
            $rawToken = Str::random(64);
            SponsorshipMagicLink::query()->create([
                'sponsor_id' => $sponsor->id,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addMinutes(30),
            ]);
            $url = route('sponsor.manage.link', ['token' => $rawToken]);
            dispatch(new SendEmail($email, new SponsorshipManageLink($url, $email)))->onQueue('mail');
        }

        return redirect()->route('sponsor.manage.request')->with([
            'manage_link_requested' => true,
            'manage_link_email' => $email,
        ]);
    }

    public function redeemManageLink(Request $request, string $token): RedirectResponse
    {
        $link = SponsorshipMagicLink::query()->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')->where('expires_at', '>', now())->first();
        if (! $link || SponsorshipMagicLink::query()->whereKey($link->id)->whereNull('used_at')
            ->where('expires_at', '>', now())->update(['used_at' => now()]) !== 1) {
            return redirect()->route('sponsor.manage.request')->with('message', 'That link has expired or has already been used.');
        }
        $request->session()->put('sponsorship.manage_sponsor_id', $link->sponsor_id);
        $request->session()->put('sponsorship.manage_expires_at', now()->addHours(2)->timestamp);
        return redirect()->route('sponsor.manage.portal');
    }

    public function managePortal(SponsorshipService $service): View|RedirectResponse
    {
        $sponsor = $this->currentSponsor();
        if (! $sponsor) return redirect()->route('sponsor.manage.request');

        // Reconcile this sponsor's outstanding monthly payment before showing
        // their status. This gives the portal a prompt fallback when webhook
        // delivery or the server scheduler is delayed.
        $service->reconcilePendingMonthlyPayments((int) $sponsor->id);

        $sponsorships = $sponsor->sponsorships()
            ->with(['project', 'option', 'payments.invoice'])
            ->get();

        $sponsorships = $sponsorships
            ->reject(fn (Sponsorship $record) => $record->status === Sponsorship::STATUS_FAILED
                && $record->payments->isEmpty())
            ->sortByDesc(fn (Sponsorship $record) => $record->started_at?->timestamp ?? $record->created_at?->timestamp ?? 0)
            ->values();
        $sponsor->setRelation('sponsorships', $sponsorships);

        $recognitionSponsorshipIds = $sponsorships
            ->filter(fn (Sponsorship $record) => (bool) $record->option?->recognition_enabled
                && $record->payments->contains(fn (SponsorshipPayment $payment) => $payment->status === SponsorshipPayment::STATUS_COMPLETED))
            ->pluck('id')
            ->all();
        $majorRecognitionLevel = $this->majorRecognitionLevel();
        $recognitionLogoAvailable = $majorRecognitionLevel !== null && $sponsorships->contains(
            fn (Sponsorship $record): bool => (bool) $record->option?->recognition_enabled
                && (float) $record->amount >= (float) $majorRecognitionLevel->minimum_total
                && $record->payments->contains(fn (SponsorshipPayment $payment) => $payment->status === SponsorshipPayment::STATUS_COMPLETED),
        );

        $paymentHistory = $sponsorships
            ->flatMap(fn (Sponsorship $record) => $record->payments->map(fn (SponsorshipPayment $payment) => [
                'payment' => $payment,
                'sponsorship' => $record,
            ]))
            ->sortByDesc(fn (array $item) => $item['payment']->paid_at?->timestamp ?? $item['payment']->created_at?->timestamp ?? 0)
            ->values();

        return view('sponsorship.manage', compact('sponsor', 'recognitionSponsorshipIds', 'paymentHistory', 'recognitionLogoAvailable'));
    }

    public function accountIndex(): View
    {
        $sponsors = Sponsor::query()->where('user_id', auth()->id())->with(['sponsorships.project', 'sponsorships.payments.invoice'])->get();
        return view('sponsorship.account', compact('sponsors'));
    }

    public function updateRecognition(Request $request, SponsorshipService $service): RedirectResponse
    {
        $sponsor = $this->currentSponsor();
        abort_unless($sponsor, 403);
        abort_unless($sponsor->sponsorships()
            ->whereHas('option', fn ($query) => $query->where('recognition_enabled', true))
            ->whereHas('payments', fn ($query) => $query->where('status', SponsorshipPayment::STATUS_COMPLETED))
            ->exists(), 403);

        $majorRecognitionLevel = $this->majorRecognitionLevel();
        $recognitionLogoAvailable = $majorRecognitionLevel !== null && $sponsor->sponsorships()
            ->with('option')
            ->whereHas('option', fn ($query) => $query->where('recognition_enabled', true))
            ->where('amount', '>=', (float) $majorRecognitionLevel->minimum_total)
            ->whereHas('payments', fn ($query) => $query->where('status', SponsorshipPayment::STATUS_COMPLETED))
            ->exists();
        $sponsor->loadMissing('organisation');
        $oldPublic = $sponsor->isRecognitionPublic();
        $oldDisplayName = trim((string) $sponsor->display_name);
        $oldWebsite = trim((string) $sponsor->website_url);

        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $validated = $request->validate([
            'recognition_public' => ['required', 'boolean'],
            'sponsor_type' => ['required', 'in:individual,organisation'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'website_url' => WebsiteUrl::validationRules(),
            'logo' => $recognitionLogoAvailable ? ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'] : ['nullable'],
        ]);
        if ((bool) $validated['recognition_public'] && $validated['sponsor_type'] === 'individual' && trim((string) ($validated['display_name'] ?? '')) === '') {
            return back()->withErrors(['display_name' => 'Add the name to show publicly before making recognition public.']);
        }
        if ((bool) $validated['recognition_public'] && $validated['sponsor_type'] === 'organisation'
            && trim((string) ($sponsor->company_name ?: $sponsor->display_name)) === '') {
            return back()->withErrors(['display_name' => 'Add an organisation name before making recognition public.']);
        }

        $sponsor->sponsor_type = $validated['sponsor_type'];
        $sponsor->display_name = trim((string) ($validated['display_name'] ?? '')) ?: null;
        $sponsor->recognition_company_name = null;

        if ($sponsor->sponsor_type === 'organisation') {
            $organisation = $service->syncSponsorOrganisationProfile($sponsor, [
                'sponsor_type' => 'organisation',
                'company_name' => $sponsor->organisation?->name ?: ($sponsor->getRawOriginal('company_name') ?: $sponsor->display_name),
                'recognition_public' => (bool) $validated['recognition_public'],
                'website_url' => $validated['website_url'] ?? null,
            ], $sponsor->user_id, true);
            abort_unless($organisation, 422, 'Add an organisation name before enabling public recognition.');
            if ($recognitionLogoAvailable && $request->hasFile('logo')) {
                $old = $organisation->logo_path;
                $organisation->logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                $organisation->save();
                if ($old && $old !== $organisation->logo_path) Storage::disk('local')->delete($old);
            }
            $recognitionChanged = $oldPublic !== (bool) $validated['recognition_public']
                || $oldDisplayName !== trim((string) $sponsor->display_name)
                || $oldWebsite !== trim((string) $organisation->website_url)
                || ($recognitionLogoAvailable && $request->hasFile('logo'));
            if ($recognitionChanged || ! (bool) $validated['recognition_public']) {
                $organisation->sponsorship_recognition_approval_notified_at = null;
                $organisation->sponsorship_recognition_approved_at = null;
                $organisation->sponsorship_recognition_approved_by = null;
                $organisation->save();
            }
        } else {
            $sponsor->recognition_public = (bool) $validated['recognition_public'];
            $sponsor->website_url = $validated['website_url'] ?? null;
            if ($recognitionLogoAvailable && $request->hasFile('logo')) {
                $old = $sponsor->recognition_logo_path;
                $sponsor->recognition_logo_path = $request->file('logo')->store('sponsorship-assets/sponsors', 'local');
                if ($old && $old !== $sponsor->recognition_logo_path) Storage::disk('local')->delete($old);
            }
            $recognitionChanged = $oldPublic !== (bool) $validated['recognition_public']
                || $oldDisplayName !== trim((string) $sponsor->display_name)
                || $oldWebsite !== trim((string) $sponsor->website_url)
                || ($recognitionLogoAvailable && $request->hasFile('logo'));
            if ($recognitionChanged || ! (bool) $validated['recognition_public']) {
                $sponsor->recognition_approval_notified_at = null;
                $sponsor->recognition_approved_at = null;
                $sponsor->recognition_approved_by = null;
            }
        }
        $sponsor->save();
        $sponsor->load('organisation');
        app(SponsorshipRecognitionService::class)->notifyPendingApproval($sponsor);

        return back()->with('message', 'Your sponsor recognition details have been updated.');
    }

    public function cancel(Request $request, int $sponsorship, SponsorshipService $service): RedirectResponse
    {
        $sponsor = $this->currentSponsor();
        $record = Sponsorship::query()->whereKey($sponsorship)->where('sponsor_id', $sponsor?->id)->firstOrFail();
        abort_unless($record->isRecurring() && $record->status !== Sponsorship::STATUS_CANCELLED, 422);
        try {
            $service->cancel($record);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['cancellation' => 'We could not confirm the cancellation. Please try again or contact STEMMechanics before your next billing date.']);
        }
        return back()->with('message', 'Your cancellation has been received. No new recurring payments will be scheduled after the current billing period.');
    }

    public function invoice(int $payment, SponsorshipService $service): Response
    {
        $sponsor = $this->currentSponsor();
        $record = SponsorshipPayment::query()->whereKey($payment)->whereHas('sponsorship', fn ($query) => $query->where('sponsor_id', $sponsor?->id))->firstOrFail();
        abort_unless($record->status === SponsorshipPayment::STATUS_COMPLETED && $record->invoice_id, 404);
        $path = $record->invoice_pdf_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            $service->ensureInvoicePdfAndEmail($record);
            $record->refresh();
            $path = $record->invoice_pdf_path;
        }
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->download(Storage::disk('local')->path($path), 'invoice-'.$record->invoice_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function primarySupportProject(): SponsorshipProject
    {
        return SponsorshipProject::query()
            ->where('is_primary', true)
            ->where('enabled', true)
            ->where('sponsorship_enabled', true)
            ->firstOrFail();
    }

    private function monthlyPaymentsAvailable(iterable $options): bool
    {
        if (! app(SquareApiService::class)->isEnabled()
            || trim((string) config('services.square.application_id')) === ''
            || trim((string) config('services.square.location_id')) === '') {
            return false;
        }

        foreach ($options as $option) {
            if ($option instanceof SponsorshipOption && $option->enabled && $option->frequency === 'monthly') {
                return true;
            }
        }

        return false;
    }

    private function hasMonthlyOption(iterable $options): bool
    {
        foreach ($options as $option) {
            if ($option instanceof SponsorshipOption && $option->enabled && $option->frequency === 'monthly') {
                return true;
            }
        }

        return false;
    }

    private function businessCustomAmountMinimum(SponsorshipProject $project): float
    {
        $businessMinimum = SponsorshipOption::query()
            ->where('project_id', $project->id)
            ->where('enabled', true)
            ->where('frequency', 'one_time')
            ->whereIn('checkout_group', ['business', 'both'])
            ->min('amount');

        return max((float) $project->custom_amount_min, (float) ($businessMinimum ?? 0));
    }

    private function businessBenefitOption(SponsorshipProject $project, float $amount): ?SponsorshipOption
    {
        return SponsorshipOption::query()
            ->where('project_id', $project->id)
            ->where('enabled', true)
            ->where('frequency', 'one_time')
            ->whereIn('checkout_group', ['business', 'both'])
            ->where('amount', '<=', $amount)
            ->orderByDesc('amount')
            ->first();
    }

    private function recognitionLogoAllowed(?SponsorshipOption $option, float $amount): bool
    {
        $major = $this->majorRecognitionLevel();

        return (bool) $option?->recognition_enabled
            && $major !== null
            && $amount >= (float) $major->minimum_total;
    }

    private function captureBusinessRecognition(Request $request, array &$checkout, ?SponsorshipOption $option, bool $allowLogo = true): void
    {
        $details = (array) ($checkout['details'] ?? []);
        if (! $option?->recognition_enabled) {
            $this->deletePendingLogo($details['recognition_logo_temp_path'] ?? null);
            foreach (['recognition_public', 'display_name', 'website_url', 'recognition_logo_temp_path'] as $field) {
                unset($details[$field]);
            }
            $checkout['details'] = $details;
            $request->session()->put('sponsorship.checkout', $checkout);

            return;
        }

        $request->merge(['website_url' => WebsiteUrl::normalize($request->input('website_url'))]);
        $recognition = $request->validate([
            'recognition_public' => ['nullable', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'website_url' => WebsiteUrl::validationRules(),
            'logo' => $allowLogo ? ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'] : ['nullable'],
        ]);

        $recognitionPublic = $request->boolean('recognition_public');
        $displayName = trim((string) ($recognition['display_name'] ?? ''));
        if ($recognitionPublic && $displayName === '' && ($checkout['sponsor_type'] ?? null) === 'organisation') {
            $displayName = trim((string) ($details['company_name'] ?? ''));
        }
        if ($recognitionPublic && $displayName === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'display_name' => 'Add the name to show publicly before enabling recognition.',
            ]);
        }

        $oldTempLogo = $details['recognition_logo_temp_path'] ?? null;
        if ($allowLogo && $request->hasFile('logo')) {
            $details['recognition_logo_temp_path'] = $request->file('logo')->store('sponsorship-assets/pending', 'local');
            $this->deletePendingLogo($oldTempLogo);
        } elseif (! $allowLogo) {
            $this->deletePendingLogo($oldTempLogo);
            unset($details['recognition_logo_temp_path']);
        }
        $details['recognition_public'] = $recognitionPublic;
        $details['display_name'] = $displayName ?: null;
        $details['website_url'] = $recognition['website_url'] ?? null;
        $checkout['details'] = $details;
        $request->session()->put('sponsorship.checkout', $checkout);
    }

    private function applyBusinessRecognition(Sponsor $sponsor, array $details, bool $allowLogo = true): void
    {
        $hasRecognitionInput = array_key_exists('recognition_public', $details)
            || trim((string) ($details['display_name'] ?? '')) !== ''
            || trim((string) ($details['website_url'] ?? '')) !== ''
            || trim((string) ($details['recognition_logo_temp_path'] ?? '')) !== '';
        if (! $hasRecognitionInput) return;

        $sponsor->loadMissing('organisation');
        $oldPublic = $sponsor->isRecognitionPublic();
        $oldDisplayName = trim((string) $sponsor->display_name);
        $oldWebsite = trim((string) $sponsor->website_url);
        $oldLogo = trim((string) $sponsor->recognition_logo_path);
        $requestedPublic = (bool) ($details['recognition_public'] ?? false);
        $requestedDisplayName = trim((string) ($details['display_name'] ?? '')) ?: null;
        $requestedWebsite = trim((string) ($details['website_url'] ?? '')) ?: null;
        $temporaryPath = $allowLogo ? trim((string) ($details['recognition_logo_temp_path'] ?? '')) : '';

        $sponsor->display_name = $requestedDisplayName;
        if ($sponsor->display_name === null && $sponsor->sponsor_type === 'organisation') {
            $sponsor->display_name = trim((string) $sponsor->company_name) ?: null;
        }
        $sponsor->recognition_company_name = null;

        $newPublic = $requestedPublic;
        $newDisplayName = trim((string) $sponsor->display_name);
        $newWebsite = $requestedWebsite;
        $recognitionChanged = $oldPublic !== $newPublic || $oldDisplayName !== $newDisplayName || $oldWebsite !== $newWebsite;
        if ($sponsor->sponsor_type === 'organisation') {
            $organisation = $sponsor->organisation;
            if ($organisation) {
                $oldOrganisationLogo = trim((string) $organisation->logo_path);
                $organisation->sponsorship_recognition_public = $newPublic;
                $organisation->website_url = $newWebsite;
                if ($temporaryPath !== '' && str_starts_with($temporaryPath, 'sponsorship-assets/pending/') && Storage::disk('local')->exists($temporaryPath)) {
                    $destination = 'sponsorship-assets/sponsors/'.Str::uuid().'.'.pathinfo($temporaryPath, PATHINFO_EXTENSION);
                    Storage::disk('local')->move($temporaryPath, $destination);
                    $organisation->logo_path = $destination;
                    if ($oldOrganisationLogo && $oldOrganisationLogo !== $destination) Storage::disk('local')->delete($oldOrganisationLogo);
                    $recognitionChanged = true;
                }
                if ($oldWebsite !== trim((string) $organisation->website_url)) $recognitionChanged = true;
                if ($recognitionChanged || ! $newPublic) {
                    $organisation->sponsorship_recognition_approved_at = null;
                    $organisation->sponsorship_recognition_approved_by = null;
                    $organisation->sponsorship_recognition_approval_notified_at = null;
                }
                $organisation->save();
            } else {
                // Keep older sponsorship records usable if they predate the
                // shared organisation profile link.
                $sponsor->recognition_public = $newPublic;
                $sponsor->website_url = $newWebsite;
                if ($recognitionChanged || ! $newPublic) {
                    $sponsor->recognition_approved_at = null;
                    $sponsor->recognition_approved_by = null;
                    $sponsor->recognition_approval_notified_at = null;
                }
            }
        } else {
            $sponsor->recognition_public = $newPublic;
            $sponsor->website_url = $newWebsite;
            if ($temporaryPath !== '' && str_starts_with($temporaryPath, 'sponsorship-assets/pending/') && Storage::disk('local')->exists($temporaryPath)) {
                $destination = 'sponsorship-assets/sponsors/'.Str::uuid().'.'.pathinfo($temporaryPath, PATHINFO_EXTENSION);
                Storage::disk('local')->move($temporaryPath, $destination);
                $sponsor->recognition_logo_path = $destination;
                if ($oldLogo && $oldLogo !== $destination) Storage::disk('local')->delete($oldLogo);
                $recognitionChanged = true;
            }
            if ($recognitionChanged || ! $newPublic) {
                $sponsor->recognition_approved_at = null;
                $sponsor->recognition_approved_by = null;
                $sponsor->recognition_approval_notified_at = null;
            }
        }
        $sponsor->save();
        $sponsor->load('organisation');
        app(SponsorshipRecognitionService::class)->notifyPendingApproval($sponsor);
    }

    private function deletePendingLogo(mixed $path): void
    {
        if (is_string($path) && str_starts_with($path, 'sponsorship-assets/pending/')) {
            Storage::disk('local')->delete($path);
        }
    }

    private function forgetCheckout(Request $request): void
    {
        $checkout = (array) $request->session()->get('sponsorship.checkout', []);
        $this->deletePendingLogo(data_get($checkout, 'details.recognition_logo_temp_path'));
        $request->session()->forget('sponsorship.checkout');
    }

    private function currentSponsor(): ?Sponsor
    {
        if (auth()->check()) {
            $sponsor = Sponsor::query()->where('user_id', auth()->id())->first();
            if ($sponsor) return $sponsor;
        }
        $sponsorId = (int) session('sponsorship.manage_sponsor_id', 0);
        $expiresAt = (int) session('sponsorship.manage_expires_at', 0);
        if ($sponsorId <= 0 || $expiresAt < now()->timestamp) return null;
        return Sponsor::query()->find($sponsorId);
    }

    private function rememberReferralSource(Request $request): ?string
    {
        if ($request->query->has('ref')) {
            $referralSource = $request->query('ref');
            if (is_string($referralSource) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/', trim($referralSource))) {
                $request->session()->put('sponsorship.referral_source', [
                    'value' => trim($referralSource),
                    'captured_at' => now()->timestamp,
                ]);
            } else {
                $request->session()->forget('sponsorship.referral_source');
            }
        }

        return $this->currentReferralSource();
    }

    private function currentReferralSource(): ?string
    {
        $captured = session()->get('sponsorship.referral_source');
        if (! is_array($captured) || ! isset($captured['value'], $captured['captured_at'])) return null;
        if ((int) $captured['captured_at'] < now()->subHours(24)->timestamp) {
            $this->clearReferralSource();
            return null;
        }

        return is_string($captured['value']) ? $captured['value'] : null;
    }

    private function clearReferralSource(): void
    {
        session()->forget('sponsorship.referral_source');
    }

    private function isAustralianCountry(mixed $country): bool
    {
        return in_array(strtolower(trim((string) $country)), ['australia', 'au', 'aus'], true);
    }

    private function invoiceUnavailableMessage(): string
    {
        return 'Invoice payment is currently available for Australian sponsors. Please pay by card in AUD.';
    }
}
