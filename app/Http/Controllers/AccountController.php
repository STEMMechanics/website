<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UserDelete;
use App\Mail\UserEmailUpdateRequest;
use App\Models\Media;
use App\Models\Token;
use App\Models\User;
use App\Providers\QRCodeProvider;
use App\Rules\UsernameRule;
use App\Support\RememberedDeviceManager;
use App\Support\UserAnonymizer;
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

        $baseViewData = [
            'user' => $user,
            'rememberedDevices' => $this->rememberedDeviceManager->listRememberedDevices($user, $request),
            'currentRememberedTokenId' => $this->rememberedDeviceManager->currentTokenId($request),
        ];

        if ($user->isChildAccount()) {
            return view('account-child', $baseViewData);
        }

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
    public function update(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->isChildAccount()) {
            return $this->updateChildAccount($request, $user);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => 'required_with:surname,phone',
            'surname' => 'required_with:surname,phone',
            'company' => 'nullable|string|max:255',
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            ...$this->avatarValidationRules($user),
            'username' => ['required', 'string', 'max:32', 'unique:users,username,'.$user->id, new UsernameRule($user->isAdmin())],
            'phone' => 'required_with:surname,phone',

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
        $userData['username'] = User::normalizeUsername((string) $userData['username']);
        $userData = $this->normalizeAvatarData($userData, $user);
        $userData = User::filterToExistingDatabaseColumns($userData);

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

        $userData['subscribed'] = ($request->get('subscribed', false) === 'on');
        $user->update($userData);
        $user->save();

        $this->syncRememberedDevicesFromRequest($request, $user);

        session()->flash('message', 'Your account details have been saved');
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

        if ($user->isChildAccount()) {
            session()->flash('message', 'Child accounts must be deleted by their parent account.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'warning');

            return redirect()->route('account.show');
        }

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
        $tfa = new TwoFactorAuth(new QRCodeProvider(), 'STEMMechanics', 6, 30, $algorithm);
        $tfa->ensureCorrectTime();

        return $tfa;
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
        if ($user->tfa_secret === null && $request->has('secret')) {
            $tfa = self::getTFAInstance();

            $qrCodeProvider = new QRCodeProvider();
            $qrCode = $qrCodeProvider->getQRCodeImage(
                $tfa->getQRText($user->canReceiveEmail() ? $user->email : $user->username, $request->get('secret')),
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
            $secret = $request->get('secret');
            $code = (string) $request->get('code');

            if (self::verifyTfaCode((string) $secret, $code)) {
                $user->tfa_secret = $secret;
                $user->save();

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

    private function updateChildAccount(Request $request, User $user): RedirectResponse
    {
        $canEditAvatar = $user->canEditAvatar();

        $validator = Validator::make($request->all(), [
            ...($canEditAvatar ? $this->avatarValidationRules($user) : []),
            'username' => ['required', 'string', 'max:32', 'unique:users,username,'.$user->id, new UsernameRule(false)],
            'current_device_nickname' => 'nullable|string|max:60',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userData = $validator->validated();
        $userData['username'] = User::normalizeUsername((string) $userData['username']);
        if ($canEditAvatar) {
            $userData = $this->normalizeAvatarData($userData, $user);
        }
        $userData = User::filterToExistingDatabaseColumns($userData);

        $user->update($userData);
        $user->save();
        $this->syncRememberedDevicesFromRequest($request, $user);

        session()->flash('message', 'Your child account settings have been saved');
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function avatarValidationRules(User $mediaOwner, bool $allowMediaSelection = true): array
    {
        return [
            'avatar_mode' => ['nullable', 'string', Rule::in(User::avatarModes())],
            'avatar_letters' => ['nullable', 'string', 'max:3'],
            'avatar_icon_class' => ['nullable', 'string', Rule::in(User::avatarIconOptions())],
            'avatar_background_color' => ['nullable', 'string', 'max:7'],
            'avatar_media_name' => [
                'nullable',
                'string',
                'exists:media,name',
                function (string $attribute, mixed $value, \Closure $fail) use ($mediaOwner, $allowMediaSelection): void {
                    $mediaName = trim((string) $value);
                    if ($mediaName === '') {
                        return;
                    }

                    $media = Media::query()->find($mediaName);
                    if (! $media) {
                        return;
                    }

                    if (! $mediaOwner->isAdmin() && (string) $media->user_id !== (string) $mediaOwner->id) {
                        $fail('You can only use media that you uploaded for your avatar.');

                        return;
                    }

                    if (! $allowMediaSelection && $mediaName !== trim((string) ($mediaOwner->avatar_media_name ?? ''))) {
                        $fail('Your parent has disabled image avatars for this account.');
                    }
                },
            ],
            'avatar_zoom' => ['nullable', 'integer', 'min:100', 'max:250'],
            'avatar_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'avatar_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeAvatarData(array $validated, User $existingUser, bool $allowMediaSelection = true): array
    {
        $validated['avatar_media_name'] = trim((string) ($validated['avatar_media_name'] ?? '')) ?: null;
        $validated['avatar_mode'] = User::normalizeAvatarMode((string) ($validated['avatar_mode'] ?? ''));
        $validated['avatar_letters'] = User::normalizeAvatarLetters((string) ($validated['avatar_letters'] ?? ''));
        $validated['avatar_icon_class'] = User::normalizeAvatarIconClass((string) ($validated['avatar_icon_class'] ?? ''));
        $validated['avatar_background_color'] = User::normalizeAvatarBackgroundColor((string) ($validated['avatar_background_color'] ?? ''));
        $validated['avatar_zoom'] = (int) ($validated['avatar_zoom'] ?? 100);
        $validated['avatar_offset_x'] = (int) ($validated['avatar_offset_x'] ?? 0);
        $validated['avatar_offset_y'] = (int) ($validated['avatar_offset_y'] ?? 0);

        if (! $allowMediaSelection && $validated['avatar_media_name'] !== null && $validated['avatar_media_name'] !== (string) ($existingUser->avatar_media_name ?? '')) {
            $validated['avatar_media_name'] = trim((string) ($existingUser->avatar_media_name ?? '')) ?: null;
        }

        if ($validated['avatar_mode'] === User::AVATAR_MODE_MEDIA && $validated['avatar_media_name'] === null) {
            $validated['avatar_mode'] = $validated['avatar_icon_class'] ? User::AVATAR_MODE_ICON : User::AVATAR_MODE_LETTERS;
        }

        if ($validated['avatar_media_name'] === null) {
            $validated['avatar_zoom'] = 100;
            $validated['avatar_offset_x'] = 0;
            $validated['avatar_offset_y'] = 0;
        }

        return $validated;
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
