@php
    $openidScopes = array_values($openidScopes ?? []);
    $openidDiscoveryUrl = (string) ($openidDiscoveryUrl ?? route('openid.discovery'));
    $openidJwksUrl = (string) ($openidJwksUrl ?? route('openid.jwks'));
    $openidUserinfoUrl = (string) ($openidUserinfoUrl ?? route('openid.userinfo'));
    $openidLogoUrl = (string) ($openidLogoUrl ?? 'https://www.stemmechanics.com.au/toolbox-sm.png');
@endphp

<x-layout>
    <x-mast description="Create an OAuth client after you have the redirect URIs and provider details ready.">Create OAuth Client</x-mast>

    <x-container inner-class="max-w-7xl">
        <section class="mt-8 rounded-3xl border border-sky-200 bg-sky-50 p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Before you create</div>
                    <h2 class="mt-1 text-2xl font-semibold text-sky-950">Fill in the questions below, then the secret will be shown once.</h2>
                    <p class="mt-3 text-sm leading-6 text-sky-900">
                        This page collects the details an OpenID client needs first. After you submit, the new client ID and secret are returned to the list page one time only.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('admin.oauth-clients.index') }}" color="secondary">Back to clients</x-ui.button>
                </div>
            </div>
        </section>

        <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create client</h3>
                    <p class="mt-1 text-sm text-gray-600">Answer these questions to register the client.</p>
                </div>

                <form method="POST" action="{{ route('admin.oauth-clients.store') }}" class="mt-6">
                    @csrf
                    <x-ui.input label="Client name" name="name" value="{{ old('name') }}" placeholder="Gitea" />
                    <x-ui.input
                        type="textarea"
                        label="Redirect URIs"
                        name="redirect_uris"
                        value="{{ old('redirect_uris') }}"
                        placeholder="https://git.example.com/user/oauth2/stemmechanics/callback"
                        fieldClasses="min-h-[10rem]"
                    />

                    <div class="mt-5 space-y-3 rounded-2xl bg-gray-50 p-4">
                        <x-ui.checkbox
                            label="Public client (no secret)"
                            name="public_client"
                            checked="{{ old('public_client', false) }}"
                        />
                        <x-ui.checkbox
                            label="Enable device authorization flow"
                            name="enable_device_flow"
                            checked="{{ old('enable_device_flow', false) }}"
                        />
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button type="submit" color="primary">Create client</x-ui.button>
                        <x-ui.button href="{{ route('admin.oauth-clients.index') }}" color="secondary">Cancel</x-ui.button>
                    </div>
                </form>
            </section>

            <aside class="space-y-4">
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-start gap-4">
                        <img src="{{ $openidLogoUrl }}" alt="STEMMechanics toolbox icon" class="h-14 w-14 flex-none rounded-2xl bg-sky-50 p-2 ring-1 ring-sky-200">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">OpenID Connect</div>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">Values to hand to the provider</h3>
                        </div>
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div>
                            <dt class="font-semibold text-gray-900">Icon URL</dt>
                            <dd class="break-all text-gray-700">{{ $openidLogoUrl }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900">Auto Discovery URL</dt>
                            <dd class="break-all text-gray-700">{{ $openidDiscoveryUrl }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900">JWKS URI</dt>
                            <dd class="break-all text-gray-700">{{ $openidJwksUrl }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900">UserInfo URI</dt>
                            <dd class="break-all text-gray-700">{{ $openidUserinfoUrl }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Scopes and claims</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($openidScopes as $scope)
                            <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">{{ $scope }}</span>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-3 text-sm text-gray-700">
                        <div><span class="font-semibold text-gray-900">Full name claim:</span> name</div>
                        <div><span class="font-semibold text-gray-900">Email claim:</span> email</div>
                        <div><span class="font-semibold text-gray-900">Profile claim:</span> profile</div>
                        <div><span class="font-semibold text-gray-900">SSH public key claim:</span> Not currently exposed by this site</div>
                        <div><span class="font-semibold text-gray-900">Group claim:</span> Not currently exposed by this site</div>
                    </div>
                </section>
            </aside>
        </div>
    </x-container>
</x-layout>
