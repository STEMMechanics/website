<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePrivilegedMfa;
use App\Jobs\RequestLoginLink;
use App\Jobs\SendEmail;
use App\Mail\UserEmailUpdateConfirm;
use App\Mail\UserLogin;
use App\Mail\UserLoginBackupCode;
use App\Mail\UserRegister;
use App\Mail\UserWelcome;
use App\Models\Token;
use App\Models\User;
use App\Support\AltchaTrust;
use App\Support\RememberedDeviceManager;
use App\Support\SafeRedirect;
use GrantHolle\Altcha\Rules\ValidAltcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const PASSWORD_LOGIN_SESSION_KEY = 'auth.password_login';

    public function __construct(
        private readonly RememberedDeviceManager $rememberedDeviceManager
    ) {}

    /**
     * Show the login form or if token present, process the login
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->action([HomeController::class, 'index']);
        }

        $token = $request->query('token');
        if ($token) {
            return $this->LoginByToken($token);
        }

        $rememberedUser = $this->rememberedDeviceManager->resolveRememberedUser($request);
        if ($rememberedUser) {
            return $this->loginByUser(
                $rememberedUser,
                ['url' => session()->pull('url.intended', null)],
                null,
                null,
                'success',
                false
            );
        }

        $response = view('auth.login', [
            'rememberedLogin' => $this->rememberedDeviceManager->getRememberedEmail($request),
        ]);

        return $response;
    }

    /**
     * Process the login form
     */
    public function postLogin(Request $request): View|RedirectResponse
    {
        $login = trim((string) $request->input('login', $request->input('email', '')));
        $rememberEmailProvided = $request->has('remember_email');
        $rememberEmail = $rememberEmailProvided && $request->boolean('remember_email');

        if ($request->has('remember_email')) {
            if ($rememberEmail) {
                $this->rememberedDeviceManager->queueRememberedEmail($login);
            } else {
                $this->rememberedDeviceManager->queueRememberedEmail(null);
            }
        }

        $rules = [
            'login' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'totp' => 'nullable|string|max:100',
            'otp' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:100',
            'backup_code' => 'nullable|string|max:100',
        ];
        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha];
        }

        $request->validate($rules, [
            'login.required' => 'Email is required.',
        ]);
        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        if (config('security.generic_login') && $request->input('method') === 'email') {
            RequestLoginLink::dispatch($login, array_filter([
                'url' => session()->pull('url.intended', null),
                'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                'remember_email_value' => $rememberEmailProvided ? $login : null,
            ], fn ($value) => $value !== null));

            return view('auth.login-link');
        }
        $forceEmailLogin = false;
        $rememberEmailValue = $rememberEmailProvided ? ($rememberEmail ? '1' : '0') : '0';
        $password = (string) $request->input('password', '');

        $otpCode = trim((string) ($request->input('totp', $request->input('otp', $request->input('code', '')))));

        if ($otpCode !== '') {
            $user = $this->findUserByLogin($login);
            if ($user && trim((string) $user->tfa_secret) !== '') {
                if (AccountController::verifyTfaCode((string) $user->tfa_secret, $otpCode)
                    && (! config('security.admin_mfa_required') || ! $user->isAdmin()
                        || Cache::add('mfa-used:'.hash('sha256', $user->id.'|'.$otpCode), true, 300))) {
                    $pendingPasswordData = $this->pullPendingPasswordLoginData($request, $user);
                    if ($pendingPasswordData !== null) {
                        return $this->loginByUser($user, $pendingPasswordData, mfaVerified: true);
                    }

                    if ($this->allowsAuthenticatorLogin($user)) {
                        return $this->loginByUser($user, array_filter([
                            'url' => session()->pull('url.intended', null),
                            'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                            'remember_email_value' => $rememberEmailProvided ? $login : null,
                        ], fn ($value) => $value !== null), mfaVerified: true);
                    }

                    if ($user->canUseEmailLogin() && (! config('security.admin_mfa_required') || ! $user->isAdmin())) {
                        return $this->loginByUser($user, ['url' => session()->pull('url.intended', null)]);
                    }
                }
            }

            return view('auth.login-2fa', [
                'login' => $login,
                'allowEmailMethod' => config('security.generic_login') || ($user?->canUseEmailLogin() ?? false),
                'allowPasswordMethod' => $user?->canUsePasswordLogin() ?? false,
            ])->withErrors([
                'totp' => 'The 2FA code is not valid',
            ]);
        }

        if ($request->has('backup_code')) {
            $user = $this->findUserByLogin($login);
            if ($user && trim((string) $user->tfa_secret) !== '') {
                if ($user->verifyBackupCode($request->backup_code)) {
                    $pendingPasswordData = $this->pullPendingPasswordLoginData($request, $user);
                    if ($pendingPasswordData !== null) {
                        if ($user->canReceiveEmail()) {
                            dispatch(new SendEmail($user->email, new UserLoginBackupCode($user->email)))->onQueue('mail');
                        }

                        return $this->loginByUser($user, $pendingPasswordData, mfaVerified: true);
                    }

                    if ($this->allowsAuthenticatorLogin($user)) {
                        if ($user->canReceiveEmail()) {
                            dispatch(new SendEmail($user->email, new UserLoginBackupCode($user->email)))->onQueue('mail');
                        }

                        return $this->loginByUser($user, array_filter([
                            'url' => session()->pull('url.intended', null),
                            'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                            'remember_email_value' => $rememberEmailProvided ? $login : null,
                        ], fn ($value) => $value !== null), mfaVerified: true);
                    }

                    if ($user->canUseEmailLogin() && (! config('security.admin_mfa_required') || ! $user->isAdmin())) {
                        if ($user->canReceiveEmail()) {
                            dispatch(new SendEmail($user->email, new UserLoginBackupCode($user->email)))->onQueue('mail');
                        }

                        return $this->loginByUser($user, ['url' => session()->pull('url.intended', null)]);
                    }
                }
            }

            return view('auth.login-2fa', [
                'login' => $login,
                'method' => 'backup',
                'allowEmailMethod' => config('security.generic_login') || ($user?->canUseEmailLogin() ?? false),
                'allowPasswordMethod' => $user?->canUsePasswordLogin() ?? false,
            ])->withErrors([
                'backup_code' => 'The backup code is not valid',
            ]);
        }

        if ($request->has('method')) {
            if ($request->get('method') === 'email') {
                $forceEmailLogin = true;
            } elseif ($request->get('method') === 'password') {
                $user = $this->findUserByLogin($login);

                return $this->passwordPromptView($login, $user?->canUseEmailLogin() ?? false, $rememberEmailValue, trim((string) $user?->tfa_secret) !== '');
            } else {
                abort(404);
            }
        }

        $user = $this->findUserByLogin($login);

        if ($password !== '') {
            if (! $user || ! $user->canUsePasswordLogin() || ! Hash::check($password, (string) $user->password)) {
                return $this->passwordPromptView(
                    $login,
                    config('security.generic_login') || ($user?->canUseEmailLogin() ?? false),
                    $rememberEmailValue,
                    trim((string) $user?->tfa_secret) !== ''
                )->withErrors([
                    'password' => 'The password is not valid.',
                ]);
            }

            if ($user->tfa_secret !== null) {
                $this->storePendingPasswordLogin($request, $user, array_filter([
                    'url' => session()->pull('url.intended', null),
                    'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                    'remember_email_value' => $rememberEmailProvided ? $login : null,
                ], fn ($value) => $value !== null));

                return view('auth.login-2fa', [
                    'user' => $user,
                    'login' => $login,
                    'allowEmailMethod' => $user->canUseEmailLogin(),
                    'allowPasswordMethod' => $user->canUsePasswordLogin(),
                ]);
            }

            $this->forgetPendingPasswordLogin($request);

            return $this->loginByUser($user, array_filter([
                'url' => session()->pull('url.intended', null),
                'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                'remember_email_value' => $rememberEmailProvided ? $login : null,
            ], fn ($value) => $value !== null), identityVerified: true);
        }

        if ($user && $user->tfa_secret !== null && ! $forceEmailLogin) {
            return view('auth.login-2fa', [
                'user' => $user,
                'login' => $login,
                'allowEmailMethod' => $user->canUseEmailLogin(),
                'allowPasswordMethod' => $user->canUsePasswordLogin(),
            ]);
        }

        if ($user && $user->canUsePasswordLogin() && ! $forceEmailLogin) {
            return $this->passwordPromptView(
                $login,
                $user->canUseEmailLogin(),
                $rememberEmailValue
            );
        }

        $user = $this->findVerifiedUserByLogin($login);
        if ($user) {
            if (! $forceEmailLogin && $user->tfa_secret !== null) {
                return view('auth.login-2fa', [
                    'user' => $user,
                    'login' => $login,
                    'allowEmailMethod' => $user->canUseEmailLogin(),
                    'allowPasswordMethod' => $user->canUsePasswordLogin(),
                ]);
            }

            $token = $user->tokens()->create([
                'type' => 'login',
                'data' => array_filter([
                    'url' => session()->pull('url.intended', null),
                    'remember_email' => $rememberEmailProvided ? $rememberEmail : null,
                    'remember_email_value' => $rememberEmailProvided ? $login : null,
                ], fn ($value) => $value !== null),
            ]);

            dispatch(new SendEmail($user->email, new UserLogin($token->id, $user->getName(), $user->email)))->onQueue('mail');

            return view('auth.login-link');
        }

        if (config('security.generic_login')) {
            return view('auth.login-link');
        }
        session()->flash('status', 'not-found');

        return view('auth.login', [
            'rememberedLogin' => $this->rememberedDeviceManager->getRememberedEmail($request),
        ]);
    }

    /**
     * Process the login by token
     */
    public function loginByToken(string $tokenStr): View|RedirectResponse
    {
        $token = Token::where('id', $tokenStr)
            ->where('type', 'login')
            ->where('expires_at', '>', now())
            ->first();

        if ($token) {
            $user = $token->user;
            if ($user instanceof User && Token::query()->whereKey($token->getKey())->delete() === 1) {

                return $this->loginByUser($user, $token->data, identityVerified: true);
            }
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Log in failed');
        session()->flash('message-type', 'danger');

        return view('auth.login', [
            'rememberedLogin' => $this->rememberedDeviceManager->getRememberedEmail(request()),
        ]);
    }

    /**
     * Process the login by user
     *
     * @return RedirectResponse
     */
    public function loginByUser(
        User $user,
        array $data = [],
        ?string $message = null,
        ?string $title = null,
        string $type = 'success',
        bool $flashMessage = true,
        bool $mfaVerified = false,
        bool $identityVerified = false
    ) {
        $url = null;
        if (isset($data['url']) && $data['url']) {
            $url = $data['url'];
        }

        if (! SafeRedirect::allows($url)) {
            $url = null;
        }

        if (is_string($url)) {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && in_array($path, ['/admin/server/deploy/log', '/admin/server/log'], true)) {
                $url = route('admin.server.index');
            }
        }

        request()->session()->forget(['privileged_mfa', 'mfa_enrolment_identity', 'tfa.enrolment_secret']);
        Auth::login($user);
        request()->session()->regenerate();
        if ($identityVerified || $mfaVerified) {
            request()->session()->forget('auth.require_fresh_login');
        }
        if ($identityVerified && $user->tfa_secret === null) {
            RequirePrivilegedMfa::confirmEnrolmentIdentity(request(), $user);
        }
        if ($mfaVerified) {
            RequirePrivilegedMfa::confirm(request(), $user);
        }
        $this->rememberedDeviceManager->refreshCurrentDeviceForUser(request(), $user);
        if (array_key_exists('remember_email', $data)) {
            if ((bool) $data['remember_email']) {
                $emailValue = trim((string) ($data['remember_email_value'] ?? $user->email));
                $this->rememberedDeviceManager->queueRememberedEmail($emailValue !== '' ? $emailValue : $user->email);
            } else {
                $this->rememberedDeviceManager->queueRememberedEmail(null);
            }
        }

        if ($flashMessage) {
            session()->flash('message', $message ?? 'You have been logged in');
            session()->flash('message-title', $title ?? 'Logged in');
            session()->flash('message-type', $type);
        }

        if ($url) {
            return redirect($url);
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->action([HomeController::class, 'index']);
    }

    public function showLogout(Request $request): View|RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->route('index');
        }

        return view('auth.logout');
    }

    /**
     * Process the user logout
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if ($user instanceof User) {
            $this->rememberedDeviceManager->forgetCurrentDevice($request, $user);
        }

        $this->forgetPendingPasswordLogin($request);

        auth()->logout();
        AltchaTrust::clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        session()->flash('message', 'You have been logged out');
        session()->flash('message-title', 'Logged out');
        session()->flash('message-type', 'warning');

        return redirect()->route('index');
    }

    /**
     * Show the registration form or if token present, process the registration
     */
    public function showRegister(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('index');
        }

        $tokenStr = $request->query('token');
        if ($tokenStr) {
            $token = Token::where('id', $tokenStr)
                ->where('type', 'register')
                ->where('expires_at', '>', now())
                ->first();

            if ($token) {
                $user = $token->user;
                if ($user instanceof User) {
                    $user->email_verified_at = now();
                    $user->save();

                    $user->tokens()->where('type', 'register')->delete();

                    dispatch(new SendEmail($user->email, new UserWelcome($user->email)))->onQueue('mail');

                    return $this->loginByUser(
                        $user,
                        [],
                        'Your account has been created and you have been logged in',
                        'Welcome to STEMMechanics',
                        'success'
                    );
                }
            }

            session()->flash('message', 'That token has expired or is invalid');
            session()->flash('message-title', 'Registration failed');
            session()->flash('message-type', 'danger');
        }

        return view('auth.register');
    }

    /**
     * Process the registration form
     */
    public function postRegister(Request $request): View|RedirectResponse
    {
        $rules = [
            'email' => 'required|email',
        ];
        if (AltchaTrust::shouldRequire($request)) {
            $rules['altcha'] = ['required', new ValidAltcha];
        }

        $request->validate($rules, [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);
        if (array_key_exists('altcha', $rules)) {
            AltchaTrust::markVerified($request);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            if ($user->email_verified_at !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'email' => __('validation.custom_messages.email_exists'),
                ]);
            }
        } else {
            $user = User::create([
                'email' => $request->email,
            ]);
        }

        $user->tokens()->where('type', 'register')->delete();
        $token = $user->tokens()->create([
            'type' => 'register',
            'data' => ['url' => session()->pull('url.intended', null)],
        ]);

        dispatch(new SendEmail($user->email, new UserRegister($token->id, $user->email)))->onQueue('mail');

        return view('auth.register-link');
    }

    /**
     * Confirm the user email update.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $tokenStr = $request->query('token');

        $token = Token::where('id', $tokenStr)
            ->where('type', 'email-update')
            ->where('expires_at', '>', now())
            ->first();

        if ($token) {
            $user = $token->user;
            if (! $user instanceof User) {
                session()->flash('message', 'That token has expired or is invalid');
                session()->flash('message-title', 'Email update failed');
                session()->flash('message-type', 'danger');

                return redirect()->route('index');
            }

            if ($token->data && isset($token->data['email'])) {
                $user->email = $token->data['email'];
                $user->email_verified_at = now();
                $user->save();

                $user->tokens()->where('type', 'email-update')->delete();

                session()->flash('message', 'Your email has been updated');
                session()->flash('message-title', 'Email updated');
                session()->flash('message-type', 'success');

                dispatch(new SendEmail($user->email, new UserEmailUpdateConfirm($user->email)))->onQueue('mail');

                return redirect()->route('index');
            }
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Email update failed');
        session()->flash('message-type', 'danger');

        return redirect()->route('index');
    }

    private function allowsAuthenticatorLogin(User $user): bool
    {
        return $user->email_verified_at !== null
            && $user->tfa_secret !== null;
    }

    private function findVerifiedUserByLogin(string $login): ?User
    {
        $user = $this->findUserByLogin($login);

        return $user && $user->canUseEmailLogin() ? $user : null;
    }

    private function findUserByLogin(string $login): ?User
    {
        $identifier = trim($login);
        if ($identifier === '') {
            return null;
        }

        $query = User::query()->whereNull('anonymized_at');
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', strtolower($identifier))->first();
        }

        return null;
    }

    private function storePendingPasswordLogin(Request $request, User $user, array $data): void
    {
        $request->session()->put(self::PASSWORD_LOGIN_SESSION_KEY, [
            'user_id' => (string) $user->id,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'data' => $data,
        ]);
    }

    private function pullPendingPasswordLoginData(Request $request, User $user): ?array
    {
        $payload = $request->session()->get(self::PASSWORD_LOGIN_SESSION_KEY);
        if (! is_array($payload)) {
            return null;
        }

        $expiresAt = trim((string) ($payload['expires_at'] ?? ''));
        if (
            (string) ($payload['user_id'] ?? '') !== (string) $user->id
            || $expiresAt === ''
            || now()->greaterThan(Carbon::parse($expiresAt))
        ) {
            $this->forgetPendingPasswordLogin($request);

            return null;
        }

        $this->forgetPendingPasswordLogin($request);

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    private function forgetPendingPasswordLogin(Request $request): void
    {
        $request->session()->forget(self::PASSWORD_LOGIN_SESSION_KEY);
    }

    private function passwordPromptView(string $login, bool $allowEmailMethod, string $rememberEmailValue = '0', bool $allowAuthenticatorMethod = false): View
    {
        return view('auth.login-password', [
            'login' => $login,
            'allowEmailMethod' => $allowEmailMethod,
            'allowAuthenticatorMethod' => $allowAuthenticatorMethod,
            'rememberEmailValue' => $rememberEmailValue,
        ]);
    }
}
