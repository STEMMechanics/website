<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UserDelete;
use App\Mail\UserEmailUpdateRequest;
use App\Models\Organisation;
use App\Models\OrganisationMembershipRequest;
use App\Models\Token;
use App\Models\User;
use App\Providers\QRCodeProvider;
use App\Services\OrganisationNameMatcher;
use App\Services\OrganisationMembershipService;
use App\Support\RememberedDeviceManager;
use App\Support\UserAnonymizer;
use App\Support\WebsiteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RobThree\Auth\Algorithm;
use RobThree\Auth\TwoFactorAuth;

class AccountController extends Controller
{
    public function __construct(
        private readonly RememberedDeviceManager $rememberedDeviceManager,
        private readonly UserAnonymizer $userAnonymizer,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $pendingOrganisationRequests = OrganisationMembershipRequest::query()
            ->with(['organisation.parent', 'reviewer'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
        $organisationMembershipRequests = OrganisationMembershipRequest::query()
            ->with(['organisation.parent', 'reviewer'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'pending')
            ->latest()
            ->limit(20)
            ->get();
        $organisationRequestsForApproval = collect();
        $linkedOrganisations = $user->organisations()->with('parent')->orderBy('name')->get();
        $linkedOrganisationIds = $linkedOrganisations
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $linkedPrimaryOrganisationId = in_array((string) $user->primary_organisation_id, $linkedOrganisationIds, true)
            ? (string) $user->primary_organisation_id
            : null;
        $canViewOrganisation = $linkedOrganisationIds !== [];
        if ($user->isAdmin()) {
            $organisationRequestsForApproval = OrganisationMembershipRequest::query()
                ->with(['organisation', 'user'])
                ->where('status', 'pending')
                ->when($linkedOrganisationIds !== [], fn ($query) => $query->whereNotIn('organisation_id', $linkedOrganisationIds))
                ->latest()
                ->limit(50)
                ->get();
        }

        $baseViewData = [
            'user' => $user,
            'linkedOrganisations' => $linkedOrganisations,
            'primaryOrganisationId' => $linkedPrimaryOrganisationId,
            'rememberedDevices' => $this->rememberedDeviceManager->listRememberedDevices($user, $request),
            'currentRememberedTokenId' => $this->rememberedDeviceManager->currentTokenId($request),
            'organisationSuggestions' => Organisation::query()
                ->when($linkedOrganisationIds !== [], fn ($query) => $query->whereNotIn('id', $linkedOrganisationIds))
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->all(),
            'organisationNames' => Organisation::query()
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->all(),
            'organisationMatchCandidates' => $request->session()->get('organisation_match_candidates', []),
            'pendingOrganisationRequests' => $pendingOrganisationRequests,
            'organisationMembershipRequests' => $organisationMembershipRequests,
            'organisationRequestsForApproval' => $organisationRequestsForApproval,
            'canViewOrganisation' => $canViewOrganisation,
        ];

        return view('account', $baseViewData);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OrganisationNameMatcher $organisationNames, OrganisationMembershipService $organisationMemberships)
    {
        /** @var User $user */
        $user = auth()->user();

        $requestedOrganisationName = $request->input('organisation_name');
        $requestedOrganisationName = is_string($requestedOrganisationName)
            ? (preg_replace('/\s+/u', ' ', trim($requestedOrganisationName)) ?? '')
            : '';
        $isNewOrganisationName = $requestedOrganisationName !== ''
            && ! Organisation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($requestedOrganisationName)])->exists();
        $newOrganisationDetailFields = [
            'new_organisation_type',
            'new_organisation_website_url',
            'new_organisation_address',
            'new_organisation_address2',
            'new_organisation_city',
            'new_organisation_state',
            'new_organisation_postcode',
            'new_organisation_country',
        ];
        $newOrganisationWebsite = $request->input('new_organisation_website_url');
        $request->merge([
            'new_organisation_website_url' => ! $isNewOrganisationName
                ? null
                : (is_string($newOrganisationWebsite) ? WebsiteUrl::normalize($newOrganisationWebsite) : $newOrganisationWebsite),
        ]);
        if (! $isNewOrganisationName) {
            foreach ($newOrganisationDetailFields as $field) {
                $request->merge([$field => null]);
            }
        }

        $validator = Validator::make($request->all(), [
            'firstname' => ['sometimes', 'required', 'string', 'max:120'],
            'surname' => 'required_with:surname,phone',
            'organisation_name' => 'nullable|string|max:255',
            'organisation_keep_as_entered' => ['nullable', 'boolean'],
            'new_organisation_type' => $isNewOrganisationName
                ? ['required', Rule::in(array_keys(Organisation::TYPES))]
                : ['nullable'],
            'new_organisation_website_url' => $isNewOrganisationName
                ? WebsiteUrl::validationRules()
                : ['nullable'],
            'new_organisation_address' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:255', 'required_with:new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_address2' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:255']
                : ['nullable'],
            'new_organisation_city' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_state,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_state' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_postcode,new_organisation_country']
                : ['nullable'],
            'new_organisation_postcode' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:40', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_country']
                : ['nullable'],
            'new_organisation_country' => $isNewOrganisationName
                ? ['nullable', 'string', 'max:120', 'required_with:new_organisation_address,new_organisation_address2,new_organisation_city,new_organisation_state,new_organisation_postcode']
                : ['nullable'],
            'primary_organisation_id' => [
                'nullable',
                'uuid',
                Rule::exists('organisation_user', 'organisation_id')->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone' => 'required_with:surname,phone',
            'use_organisation_billing_address' => ['nullable', 'boolean'],
            'use_organisation_shipping_address' => ['nullable', 'boolean'],

            'shipping_address' => 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state',
            'shipping_city' => 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state',
            'shipping_postcode' => 'required_with:shipping_address,shipping_city,shipping_country,shipping_state',
            'shipping_country' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state',
            'shipping_state' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country',

            'billing_address' => 'required_with:billing_city,billing_postcode,billing_country,billing_state',
            'billing_city' => 'required_with:billing_address,billing_postcode,billing_country,billing_state',
            'billing_postcode' => 'required_with:billing_address,billing_city,billing_country,billing_state',
            'billing_country' => 'required_with:billing_address,billing_city,billing_postcode,billing_state',
            'billing_state' => 'required_with:billing_address,billing_city,billing_postcode,billing_country',
            'current_device_nickname' => 'nullable|string|max:60',
        ], [
            'firstname.required' => __('validation.custom_messages.firstname_required'),
            'surname.required' => __('validation.custom_messages.surname_required'),
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
            'phone.required' => __('validation.custom_messages.phone_required'),

            'shipping_address.required' => __('validation.custom_messages.shipping_address_required'),
            'shipping_city.required' => __('validation.custom_messages.shipping_city_required'),
            'shipping_postcode.required' => __('validation.custom_messages.shipping_postcode_required'),
            'shipping_country.required' => __('validation.custom_messages.shipping_country_required'),
            'shipping_state.required' => __('validation.custom_messages.shipping_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userData = $validator->validated();
        $organisationName = trim((string) ($userData['organisation_name'] ?? ''));
        $primaryOrganisationSubmitted = $request->exists('primary_organisation_id');
        $primaryOrganisationId = isset($userData['primary_organisation_id']) && $userData['primary_organisation_id'] !== ''
            ? (string) $userData['primary_organisation_id']
            : null;
        $useOrganisationBillingAddress = (bool) ($userData['use_organisation_billing_address'] ?? false);
        $useOrganisationShippingAddress = (bool) ($userData['use_organisation_shipping_address'] ?? false);

        if (($useOrganisationBillingAddress || $useOrganisationShippingAddress) && $primaryOrganisationId === null) {
            return redirect()->back()
                ->withErrors(['primary_organisation_id' => 'Choose a primary organisation before using its address.'])
                ->withInput();
        }

        $organisationName = preg_replace('/\s+/u', ' ', $organisationName) ?? '';
        if ($organisationName !== ''
            && ! (bool) ($userData['organisation_keep_as_entered'] ?? false)
            && ! Organisation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($organisationName)])->exists()) {
            $similarOrganisations = $organisationNames->suggestions($organisationName);
            if ($similarOrganisations !== []) {
                return redirect()->back()
                    ->withInput()
                    ->with('organisation_match_candidates', $similarOrganisations);
            }
        }

        $organisationDetails = [
            'type' => $userData['new_organisation_type'] ?? null,
            'website_url' => $userData['new_organisation_website_url'] ?? null,
            'billing_address' => $userData['new_organisation_address'] ?? null,
            'billing_address2' => $userData['new_organisation_address2'] ?? null,
            'billing_city' => $userData['new_organisation_city'] ?? null,
            'billing_state' => $userData['new_organisation_state'] ?? null,
            'billing_postcode' => $userData['new_organisation_postcode'] ?? null,
            'billing_country' => $userData['new_organisation_country'] ?? null,
            'shipping_address' => $userData['new_organisation_address'] ?? null,
            'shipping_address2' => $userData['new_organisation_address2'] ?? null,
            'shipping_city' => $userData['new_organisation_city'] ?? null,
            'shipping_state' => $userData['new_organisation_state'] ?? null,
            'shipping_postcode' => $userData['new_organisation_postcode'] ?? null,
            'shipping_country' => $userData['new_organisation_country'] ?? null,
        ];

        $userData = User::filterToExistingDatabaseColumns($userData);
        unset($userData['organisation_name']);
        unset($userData['organisation_keep_as_entered']);
        foreach ($newOrganisationDetailFields as $field) {
            unset($userData[$field]);
        }
        unset($userData['primary_organisation_id']);
        $userData['use_organisation_billing_address'] = $useOrganisationBillingAddress;
        $userData['use_organisation_shipping_address'] = $useOrganisationShippingAddress;

        $newEmail = $userData['email'];
        unset($userData['email']);

        if (strtolower($user->email) !== strtolower($newEmail)) {
            $user->tokens()->where('type', 'email-update')->delete();

            $token = $user->tokens()->create([
                'type' => 'email-update',
                'data' => [
                    'email' => $newEmail,
                ],
                'expires_at' => now()->addMinutes(30),
            ]);

            dispatch(new SendEmail($user->email, new UserEmailUpdateRequest($token->id, $user->email, $newEmail)))->onQueue('mail');
        }

        if ($user->isAdmin()) {
            $userData['dashboard_email_opt_in'] = $request->boolean('dashboard_email_opt_in');
        }
        $userData['subscribed'] = ($request->get('subscribed', false) === 'on');
        $user->update($userData);
        $membershipResult = ['state' => 'unchanged', 'organisation' => null];
        if ($organisationName !== '') {
            $membershipResult = $organisationMemberships->applyProfileOrganisation($user, $organisationName, $organisationDetails);
        }

        if ($primaryOrganisationSubmitted) {
            $currentPrimaryOrganisationId = $user->primary_organisation_id !== null
                ? (string) $user->primary_organisation_id
                : null;
            if ($primaryOrganisationId !== $currentPrimaryOrganisationId) {
                $user->updateQuietly([
                    'primary_organisation_id' => $primaryOrganisationId,
                    'use_organisation_account_terms' => false,
                ]);
            }
        }

        $this->syncRememberedDevicesFromRequest($request, $user);

        $message = match ($membershipResult['state']) {
            'pending' => 'Your account details have been saved. Your request to join '.$membershipResult['organisation']->name.' is waiting for approval.',
            'auto_approved' => 'Your account details have been saved. Your verified email domain was approved for '.$membershipResult['organisation']->name.'.',
            'created' => 'Your account details have been saved. You are the first contact for '.$membershipResult['organisation']->name.'.',
            default => 'Your account details have been saved',
        };
        session()->flash('message', $message);
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($request->boolean('clear_password')) {
            $user->password = null;
            $user->save();

            session()->flash('message', 'Password login has been removed.');
            session()->flash('message-title', 'Security updated');
            session()->flash('message-type', 'success');

            return redirect()->route('account.show');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = (string) $validated['password'];
        $user->save();

        session()->flash('message', 'Password login has been updated.');
        session()->flash('message-title', 'Security updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.show');
    }

    public function destroyRememberedDevice(Request $request, Token $token): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ((string) $token->user_id !== (string) $user->id || (string) $token->type !== RememberedDeviceManager::DEVICE_TOKEN_TYPE) {
            abort(403);
        }

        $this->rememberedDeviceManager->forgetDeviceById($user, (string) $token->id, $request);

        session()->flash('message', 'The selected remembered device has been removed.');
        session()->flash('message-title', 'Device removed');
        session()->flash('message-type', 'success');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect' => route('account.show'),
            ]);
        }

        return redirect()->route('account.show');
    }

    public function updateRememberedDeviceNickname(Request $request, Token $token): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ((string) $token->user_id !== (string) $user->id || (string) $token->type !== RememberedDeviceManager::DEVICE_TOKEN_TYPE) {
            abort(403);
        }

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:60',
        ]);

        $nickname = is_string($validated['nickname'] ?? null) ? $validated['nickname'] : '';
        $this->rememberedDeviceManager->setDeviceNickname($user, (string) $token->id, $nickname);

        return response()->json([
            'success' => true,
            'nickname' => trim($nickname),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            dispatch(new SendEmail($email, new UserDelete($email)))->onQueue('mail');
        }
        $this->rememberedDeviceManager->forgetCurrentDevice($request, $user);
        auth()->logout();

        $this->userAnonymizer->anonymize($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        session()->flash('message', 'Your account has been deleted');
        session()->flash('message-title', 'Account Deleted');
        session()->flash('message-type', 'danger');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('index'),
            ]);
        }

        return redirect()->route('index');
    }

    public static function getTFAInstance(Algorithm $algorithm = Algorithm::Sha512)
    {
        // TOTP uses the host's system clock. Avoid making account security depend on
        // reaching public NTP/HTTP time services during requests and test runs.
        return new TwoFactorAuth(new QRCodeProvider, 'STEMMechanics', 6, 30, $algorithm);
    }

    public static function verifyTfaCode(string $secret, string $code): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code));
        if (! is_string($normalizedCode) || $normalizedCode === '') {
            return false;
        }

        $sha512 = self::getTFAInstance(Algorithm::Sha512);
        if ($sha512->verifyCode($secret, $normalizedCode, 4)) {
            return true;
        }

        // Bitwarden/manual setups frequently default to SHA1.
        $sha1 = self::getTFAInstance(Algorithm::Sha1);

        return $sha1->verifyCode($secret, $normalizedCode, 4);
    }

    public function show_tfa()
    {
        $user = auth()->user();
        if ($user->tfa_secret === null) {
            $tfa = self::getTFAInstance();
            $secret = $tfa->createSecret();
            session()->put('tfa.enrolment_secret', $secret);

            return response()->json([
                'secret' => $secret,
            ]);
        } else {
            abort(404);
        }
    }

    public function show_tfa_image(Request $request)
    {
        $user = auth()->user();
        if ($user->tfa_secret === null && $request->session()->has('tfa.enrolment_secret')) {
            $tfa = self::getTFAInstance();

            $qrCodeProvider = new QRCodeProvider;
            $qrCode = $qrCodeProvider->getQRCodeImage(
                $tfa->getQRText((string) $user->email, (string) $request->session()->get('tfa.enrolment_secret')),
                200
            );

            return response()->stream(function () use ($qrCode) {
                echo $qrCode;
            }, 200, ['Content-Type' => $qrCodeProvider->getMimeType()]);
        } else {
            abort(404);
        }
    }

    public function post_tfa(Request $request)
    {
        $user = auth()->user();

        if ($user->tfa_secret === null && $request->has('secret') && $request->has('code')) {
            $secret = (string) $request->session()->get('tfa.enrolment_secret', '');
            abort_unless($secret !== '' && is_string($request->input('secret'))
                && hash_equals($secret, $request->input('secret')), 403);
            $code = (string) $request->get('code');

            if (self::verifyTfaCode((string) $secret, $code)) {
                $user->tfa_secret = $secret;
                $user->save();
                $request->session()->forget('tfa.enrolment_secret');

                $codes = $user->generateBackupCodes();

                return response()->json([
                    'success' => true,
                    'codes' => $codes,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                ]);
            }
        } else {
            abort(403);
        }
    }

    public function destroy_tfa(Request $request)
    {
        abort_if(config('security.admin_mfa_required') && $request->user()?->isAdmin(), 403, 'Administrators must keep two-factor authentication enabled.');
        $user = auth()->user();

        if ($user->tfa_secret !== null) {
            $user->tfa_secret = null;
            $user->save();

            $user->backupCodes()->delete();

            return response()->json([
                'success' => true,
            ]);
        } else {
            abort(403);
        }
    }

    public function post_tfa_reset_backup_codes(Request $request)
    {
        $user = auth()->user();

        if ($user->tfa_secret !== null) {
            $codes = $user->generateBackupCodes();

            return response()->json([
                'success' => true,
                'codes' => $codes,
            ]);
        } else {
            abort(403);
        }
    }

    private function syncRememberedDevicesFromRequest(Request $request, User $user): void
    {
        if ($request->boolean('keep_signed_in_device')) {
            $token = $this->rememberedDeviceManager->rememberUserOnCurrentDevice($request, $user);
            $this->rememberedDeviceManager->setDeviceNickname(
                $user,
                (string) $token->id,
                (string) $request->input('current_device_nickname', '')
            );
        } else {
            $this->rememberedDeviceManager->forgetCurrentDevice($request, $user);
        }

        $nicknameMap = $request->input('remembered_device_nicknames', []);
        if (! is_array($nicknameMap)) {
            return;
        }

        foreach ($nicknameMap as $tokenId => $nickname) {
            if (! is_string($tokenId)) {
                continue;
            }

            $this->rememberedDeviceManager->setDeviceNickname($user, $tokenId, is_string($nickname) ? $nickname : '');
        }
    }
}
