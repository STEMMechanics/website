@php
    $recentClientId = (string) ($recentClientId ?? '');
    $recentClientSecret = trim((string) ($recentClientSecret ?? ''));
    $clients = collect($clients ?? []);
    $internalClients = collect($internalClients ?? []);
    $openidScopes = array_values(array_keys(config('openid.passport.tokens_can', [])));
    $openidDiscoveryUrl = route('openid.discovery');
    $openidJwksUrl = route('openid.jwks');
    $openidUserinfoUrl = route('openid.userinfo');
    $openidLogoUrl = 'https://www.stemmechanics.com.au/toolbox-sm.png';
@endphp

<x-layout>
    <x-mast description="Create, rotate, and revoke OAuth clients for external platforms.">OAuth Clients</x-mast>

    <x-container inner-class="max-w-7xl">
        @if($recentClientId !== '')
            <section class="mt-8 rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Client created</div>
                        <h2 class="mt-1 text-lg font-semibold text-amber-950">Copy these credentials now</h2>
                        <p class="mt-1 text-sm text-amber-900">The client secret is only shown once when it is created or rotated.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[34rem]">
                        <div class="rounded-2xl border border-amber-200 bg-white px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Client ID</div>
                            <div class="mt-1 break-all text-sm font-medium text-gray-900">{{ $recentClientId }}</div>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-white px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Client Secret</div>
                            @if($recentClientSecret !== '')
                                <div class="mt-1 break-all font-mono text-sm text-gray-900">{{ $recentClientSecret }}</div>
                            @else
                                <div class="mt-1 text-sm text-gray-600">No secret was generated for this client.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="mt-8 rounded-3xl border border-sky-200 bg-sky-50 p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-4">
                        <img src="{{ $openidLogoUrl }}" alt="STEMMechanics toolbox icon" class="h-16 w-16 flex-none rounded-2xl bg-white p-2 shadow-sm ring-1 ring-sky-200">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">OpenID Connect</div>
                            <h2 class="mt-1 text-2xl font-semibold text-sky-950">Discovery details</h2>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-sky-900">
                        These are the values other services will ask for when they connect to STEMMechanics as an OpenID Provider.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('admin.oauth-clients.create') }}" color="primary">Create client</x-ui.button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Discovery URL</div>
                    <div class="mt-1 break-all text-sm text-gray-900">{{ $openidDiscoveryUrl }}</div>
                </div>
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Icon URL</div>
                    <div class="mt-1 break-all text-sm text-gray-900">{{ $openidLogoUrl }}</div>
                </div>
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">JWKS URI</div>
                    <div class="mt-1 break-all text-sm text-gray-900">{{ $openidJwksUrl }}</div>
                </div>
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">UserInfo URI</div>
                    <div class="mt-1 break-all text-sm text-gray-900">{{ $openidUserinfoUrl }}</div>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Supported scopes</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($openidScopes as $scope)
                            <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">{{ $scope }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Common claim names</div>
                    <div class="mt-2 grid gap-2 text-sm text-gray-700 sm:grid-cols-2">
                        <div><span class="font-semibold text-gray-900">Name:</span> name</div>
                        <div><span class="font-semibold text-gray-900">Email:</span> email</div>
                        <div><span class="font-semibold text-gray-900">Username:</span> preferred_username</div>
                        <div><span class="font-semibold text-gray-900">Profile:</span> profile</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-sky-100">
                <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">What to enter in providers like Gitea</div>
                <div class="mt-3 grid gap-3 text-sm text-gray-700 lg:grid-cols-2">
                    <div>
                        <div class="font-semibold text-gray-900">Icon URL</div>
                        <div class="break-all">{{ $openidLogoUrl }}</div>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">OpenID Connect Auto Discovery URL</div>
                        <div class="break-all">{{ $openidDiscoveryUrl }}</div>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Additional scopes</div>
                        <div>openid profile email</div>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Full name claim</div>
                        <div>name</div>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">SSH public key claim</div>
                        <div>Not currently exposed by this site</div>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Required claim</div>
                        <div>Leave blank unless you want to restrict login to a specific claim value.</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-8 grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Create client</h2>
                    <p class="mt-1 text-sm text-gray-600">Open a dedicated page to set up a client before the secret is shown.</p>
                </div>

                <div class="mt-6">
                    <x-ui.button href="{{ route('admin.oauth-clients.create') }}" color="primary" class="w-full">Create client</x-ui.button>
                </div>

                <p class="mt-4 text-sm leading-6 text-gray-600">
                    You will be asked for a client name, redirect URIs, and a few compatibility options first.
                </p>
            </section>

            <section class="space-y-4">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Login clients</h2>
                                <p class="mt-1 text-sm text-gray-600">These are the OAuth clients that can sign users in from external platforms.</p>
                            </div>
                        <x-ui.badge color="gray" size="xs" uppercase="true">{{ $clients->count() }} total</x-ui.badge>
                        </div>
                </div>

                @forelse($clients as $client)
                    <article class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 {{ $client->revoked ? 'opacity-70' : '' }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h3>
                                    <x-ui.badge :color="$client->revoked ? 'gray' : 'success'" size="xs" uppercase="true">{{ $client->revoked ? 'Revoked' : 'Active' }}</x-ui.badge>
                                    <x-ui.badge :color="$client->confidential() ? 'sky' : 'warning'" size="xs" uppercase="true">{{ $client->confidential() ? 'Confidential' : 'Public' }}</x-ui.badge>
                                </div>
                                <div class="mt-2 grid gap-1 text-sm text-gray-600">
                                    <div class="break-all"><span class="font-semibold text-gray-900">Client ID:</span> {{ $client->id }}</div>
                                    <div><span class="font-semibold text-gray-900">Owner:</span> {{ $client->owner?->getName() ?: 'System' }}</div>
                                    <div><span class="font-semibold text-gray-900">Active tokens:</span> {{ (int) ($client->active_token_count ?? 0) }}</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if(! $client->revoked)
                                    <x-ui.button href="{{ route('admin.oauth-clients.edit', $client) }}" color="primary-outline" class="px-4! py-1.5!">Edit</x-ui.button>
                                @endif

                                @if(! $client->revoked && $client->confidential())
                                    <form method="POST" action="{{ route('admin.oauth-clients.rotate-secret', $client) }}">
                                        @csrf
                                        <x-ui.button type="submit" color="primary-outline" class="px-4! py-1.5!">Rotate secret</x-ui.button>
                                    </form>
                                @endif

                                @if(! $client->revoked)
                                    <form method="POST" action="{{ route('admin.oauth-clients.destroy', $client) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" color="danger-outline" class="px-4! py-1.5!">Revoke</x-ui.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.oauth-clients.purge', $client) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete permanently?', 'Are you sure you want to delete this OAuth client permanently? This cannot be undone.', $el, 'Delete')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" color="danger-outline" class="px-4! py-1.5!">Delete permanently</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl bg-gray-50 px-4 py-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Redirect URIs</div>
                                <div class="mt-2 space-y-1 text-sm text-gray-700">
                                    @forelse($client->redirect_uris as $redirectUri)
                                        <div class="break-all rounded-lg bg-white px-3 py-2">{{ $redirectUri }}</div>
                                    @empty
                                        <div class="text-gray-500">No redirect URIs configured.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-2xl bg-gray-50 px-4 py-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grant types</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($client->grant_types as $grantType)
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700">{{ $grantType }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white px-5 py-8 text-sm text-gray-500 shadow-sm">
                        No OAuth clients have been created yet.
                    </div>
                @endforelse

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Internal Passport clients</h2>
                                <p class="mt-1 text-sm text-gray-600">These are Passport-managed clients such as personal access clients. They are not external login platforms.</p>
                            </div>
                        <x-ui.badge color="gray" size="xs" uppercase="true">{{ $internalClients->count() }} total</x-ui.badge>
                        </div>

                    <div class="mt-5 space-y-4">
                        @forelse($internalClients as $client)
                            <article class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h3>
                                            <x-ui.badge :color="$client->revoked ? 'gray' : 'success'" size="xs" uppercase="true">{{ $client->revoked ? 'Revoked' : 'Active' }}</x-ui.badge>
                                            <x-ui.badge color="slate" size="xs" uppercase="true">Internal</x-ui.badge>
                                        </div>
                                        <div class="mt-2 grid gap-1 text-sm text-gray-600">
                                            <div class="break-all"><span class="font-semibold text-gray-900">Client ID:</span> {{ $client->id }}</div>
                                            <div><span class="font-semibold text-gray-900">Grant types:</span> {{ implode(', ', $client->grant_types ?: []) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-sm text-gray-500">
                                No internal Passport clients were found.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
