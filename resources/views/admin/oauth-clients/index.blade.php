@php
    $recentClientId = (string) ($recentClientId ?? '');
    $recentClientSecret = trim((string) ($recentClientSecret ?? ''));
    $clients = collect($clients ?? []);
    $internalClients = collect($internalClients ?? []);
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

        <div class="mt-8 grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Create client</h2>
                    <p class="mt-1 text-sm text-gray-600">Add a new OAuth client for another platform or integration.</p>
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

                    <div class="mt-6">
                        <x-ui.button type="submit" color="primary" class="w-full">Create client</x-ui.button>
                    </div>
                </form>
            </section>

            <section class="space-y-4">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Login clients</h2>
                        <p class="mt-1 text-sm text-gray-600">These are the OAuth clients that can sign users in from external platforms.</p>
                    </div>
                        <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $clients->count() }} total</div>
                    </div>
                </div>

                @forelse($clients as $client)
                    <article class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 {{ $client->revoked ? 'opacity-70' : '' }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h3>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xxs font-semibold uppercase tracking-wide {{ $client->revoked ? 'bg-gray-200 text-gray-700' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $client->revoked ? 'Revoked' : 'Active' }}
                                    </span>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xxs font-semibold uppercase tracking-wide {{ $client->confidential() ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $client->confidential() ? 'Confidential' : 'Public' }}
                                    </span>
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
                        <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $internalClients->count() }} total</div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse($internalClients as $client)
                            <article class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h3>
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xxs font-semibold uppercase tracking-wide {{ $client->revoked ? 'bg-gray-200 text-gray-700' : 'bg-emerald-100 text-emerald-800' }}">
                                                {{ $client->revoked ? 'Revoked' : 'Active' }}
                                            </span>
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xxs font-semibold uppercase tracking-wide bg-slate-100 text-slate-800">
                                                Internal
                                            </span>
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
