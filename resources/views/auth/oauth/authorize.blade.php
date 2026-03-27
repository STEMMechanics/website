@php
    $authorizeAction = route('passport.authorizations.approve');
    if (! empty($request->nonce)) {
        $authorizeAction .= '?nonce='.urlencode((string) $request->nonce);
    }

    $connectedUserName = trim((string) ($user->getName() ?? ''));
    $connectedUserEmail = trim((string) ($user->email ?? ''));
@endphp

<x-layout :bodyClass="'image-background'">
    <div class="flex min-h-[calc(100vh-2rem)] items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="w-full max-w-4xl overflow-hidden rounded-4xl bg-white/95 shadow-deep backdrop-blur">
            <div class="grid lg:grid-cols-[1.05fr_0.95fr]">
                <div class="relative overflow-hidden bg-primary-color px-8 py-10 text-white sm:px-10">
                    <div class="absolute inset-0 opacity-25">
                        <div class="absolute -left-16 top-10 h-56 w-56 rounded-full bg-white/20 blur-3xl"></div>
                        <div class="absolute -right-10 bottom-0 h-48 w-48 rounded-full bg-orange-400/20 blur-3xl"></div>
                    </div>

                    <div class="relative flex h-full flex-col">
                        <div>
                            <h1 class="max-w-md text-3xl font-bold leading-tight sm:text-4xl">
                                Authorize {{ $client->name }}
                            </h1>
                            <p class="mt-4 max-w-lg text-sm leading-6 text-slate-200 sm:text-base">
                                You are signing in with your STEMMechanics account. Review what this application will receive, then approve to continue.
                            </p>
                        </div>

                        <div class="mt-12 mb-8 rounded-2xl border border-white/10 bg-black/25 p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-orange-100/90">Connected account</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.15] text-lg font-bold text-white ring-1 ring-white/[0.15]">
                                    {{ strtoupper(substr($connectedUserName !== '' ? $connectedUserName : $connectedUserEmail, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-white">{{ $connectedUserName !== '' ? $connectedUserName : 'STEMMechanics member' }}</p>
                                    <p class="truncate text-xs text-slate-200/80">{{ $connectedUserEmail !== '' ? $connectedUserEmail : 'Signed in session' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white px-8 py-10 sm:px-10 flex flex-col justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-primary-color">Permissions</p>
                        <h2 class="mt-2 text-2xl font-bold text-gray-900">What will be shared</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-600">
                            {{ $client->name }} is requesting access to these information:
                        </p>

                        <div class="mt-6 space-y-3">
                            @if (count($scopes) > 0)
                                @foreach ($scopes as $scope)
                                    @if($scope->id !== 'openid')
                                        <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                            <span class="mt-0.5 inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-primary-color/10 text-[11px] font-bold text-primary-color">
                                                <i class="fa-solid fa-check"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900">{{ ucfirst($scope->id) }}</p>
                                                <p class="text-sm text-gray-600">{{ $scope->description }}</p>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                            <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                                <span class="mt-0.5 inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-danger-color/10 text-[11px] font-bold text-danger-color">
                                    <i class="fa-solid fa-xmark"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">Password</p>
                                    <p class="text-sm text-gray-600">Your password will not be shared</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="js-oauth-deny-form">
                            @csrf
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <x-ui.button type="submit" color="secondary" class="js-oauth-deny-button w-full">Cancel</x-ui.button>
                        </form>

                        <form method="POST" action="{{ $authorizeAction }}" class="js-oauth-approval-form">
                            @csrf
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <x-ui.button type="submit" color="success" class="js-oauth-approval-button w-full">Approve</x-ui.button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>

@pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const lockForm = (form, button, label) => {
                if (!form || !button) {
                    return;
                }

                form.addEventListener('submit', () => {
                    button.disabled = true;
                    button.textContent = label;
                });
            };

            lockForm(
                document.querySelector('.js-oauth-approval-form'),
                document.querySelector('.js-oauth-approval-button'),
                'Redirecting...'
            );

            lockForm(
                document.querySelector('.js-oauth-deny-form'),
                document.querySelector('.js-oauth-deny-button'),
                'Cancelling...'
            );
        });
    </script>
@endPushOnce
