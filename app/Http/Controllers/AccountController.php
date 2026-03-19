<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UserDelete;
use App\Mail\UserEmailUpdateRequest;
use App\Models\ForumTopicUserState;
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

        $discussionNotificationCount = ForumTopicUserState::query()
            ->where('user_id', (string) $user->id)
            ->where('notifications_enabled', true)
            ->count();

        $baseViewData = [
            'user' => $user,
            'rememberedDevices' => $this->rememberedDeviceManager->listRememberedDevices($user, $request),
            'currentRememberedTokenId' => $this->rememberedDeviceManager->currentTokenId($request),
            'discussionNotificationCount' => $discussionNotificationCount,
        ];

        if ($user->isChildAccount()) {
            return view('account-child', $baseViewData);
        }

        $childAccounts = $user->children()
            ->whereNull('anonymized_at')
            ->withCount([
                'forumTopics as pending_topic_count' => fn ($query) => $query->where('is_approved', false),
                'forumPosts as pending_reply_count' => fn ($query) => $query
                    ->where('is_approved', false)
                    ->whereHas('topic', fn ($topicQuery) => $topicQuery->where('is_approved', true)),
            ])
            ->get();

        return view('account', [
            ...$baseViewData,
            'childAccounts' => $childAccounts,
        ]);
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
            'avatar_media_name' => [
                'nullable',
                'string',
                'exists:media,name',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    $mediaName = trim((string) $value);
                    if ($mediaName === '') {
                        return;
                    }

                    $media = Media::query()->find($mediaName);
                    if (! $media) {
                        return;
                    }

                    if (! $user->isAdmin() && (string) $media->user_id !== (string) $user->id) {
                        $fail('You can only use media that you uploaded for your avatar.');
                    }
                },
            ],
            'avatar_zoom' => ['nullable', 'integer', 'min:100', 'max:250'],
            'avatar_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'avatar_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
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
        $userData['avatar_media_name'] = trim((string) ($userData['avatar_media_name'] ?? '')) ?: null;
        $userData['avatar_zoom'] = (int) ($userData['avatar_zoom'] ?? 100);
        $userData['avatar_offset_x'] = (int) ($userData['avatar_offset_x'] ?? 0);
        $userData['avatar_offset_y'] = (int) ($userData['avatar_offset_y'] ?? 0);

        if ($userData['avatar_media_name'] === null) {
            $userData['avatar_zoom'] = 100;
            $userData['avatar_offset_x'] = 0;
            $userData['avatar_offset_y'] = 0;
        }

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

    public function unsubscribeAllDiscussionNotifications(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $updated = ForumTopicUserState::query()
            ->where('user_id', (string) $user->id)
            ->where('notifications_enabled', true)
            ->update([
                'notifications_enabled' => false,
            ]);

        if ($updated > 0) {
            session()->flash('message', 'All discussion notifications have been unsubscribed.');
            session()->flash('message-title', 'Preferences updated');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'You are already unsubscribed from discussion notifications.');
            session()->flash('message-title', 'No changes made');
            session()->flash('message-type', 'info');
        }

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

        $this->userAnonymizer->anonymize($user, $request->boolean('delete_discussion_threads'));
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
        $validator = Validator::make($request->all(), [
            'avatar_media_name' => [
                'nullable',
                'string',
                'exists:media,name',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    $mediaName = trim((string) $value);
                    if ($mediaName === '') {
                        return;
                    }

                    $media = Media::query()->find($mediaName);
                    if (! $media) {
                        return;
                    }

                    if (! $user->isAdmin() && (string) $media->user_id !== (string) $user->id) {
                        $fail('You can only use media that you uploaded for your avatar.');
                    }
                },
            ],
            'avatar_zoom' => ['nullable', 'integer', 'min:100', 'max:250'],
            'avatar_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'avatar_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'username' => ['required', 'string', 'max:32', 'unique:users,username,'.$user->id, new UsernameRule(false)],
            'current_device_nickname' => 'nullable|string|max:60',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userData = $validator->validated();
        $userData['username'] = User::normalizeUsername((string) $userData['username']);
        $userData['avatar_media_name'] = trim((string) ($userData['avatar_media_name'] ?? '')) ?: null;
        $userData['avatar_zoom'] = (int) ($userData['avatar_zoom'] ?? 100);
        $userData['avatar_offset_x'] = (int) ($userData['avatar_offset_x'] ?? 0);
        $userData['avatar_offset_y'] = (int) ($userData['avatar_offset_y'] ?? 0);

        if ($userData['avatar_media_name'] === null) {
            $userData['avatar_zoom'] = 100;
            $userData['avatar_offset_x'] = 0;
            $userData['avatar_offset_y'] = 0;
        }

        $user->update($userData);
        $user->save();
        $this->syncRememberedDevicesFromRequest($request, $user);

        session()->flash('message', 'Your child account settings have been saved');
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
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
