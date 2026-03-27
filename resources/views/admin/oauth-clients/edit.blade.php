@php
    $client = $client->loadMissing(['owner']);
    $redirectUris = implode("\n", $client->redirect_uris ?? []);
@endphp

<x-layout>
    <x-mast description="Update the client name and redirect URIs for this integration.">Edit OAuth Client</x-mast>

    <x-container inner-class="max-w-4xl">
        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h2>
                    <p class="mt-1 text-sm text-gray-600">Rename this client or update its redirect URIs. Grant type and client type are intentionally fixed.</p>
                </div>

                <form method="POST" action="{{ route('admin.oauth-clients.update', $client) }}" class="mt-6">
                    @csrf
                    @method('PUT')

                    <x-ui.input label="Client name" name="name" value="{{ old('name', $client->name) }}" />
                    <x-ui.input
                        type="textarea"
                        label="Redirect URIs"
                        name="redirect_uris"
                        value="{{ old('redirect_uris', $redirectUris) }}"
                        fieldClasses="min-h-[10rem]"
                    />

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button type="submit" color="primary">Save changes</x-ui.button>
                        <x-ui.button href="{{ route('admin.oauth-clients.index') }}" color="secondary">Back</x-ui.button>
                    </div>
                </form>
            </section>

            <section class="space-y-4">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Client ID</div>
                    <div class="mt-2 break-all text-sm text-gray-900">{{ $client->id }}</div>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Security</div>
                    <div class="mt-3 space-y-2 text-sm text-gray-600">
                        <div><span class="font-semibold text-gray-900">Grant types:</span> {{ implode(', ', $client->grant_types ?: []) }}</div>
                        <div><span class="font-semibold text-gray-900">Client type:</span> {{ $client->confidential() ? 'Confidential' : 'Public' }}</div>
                        <div><span class="font-semibold text-gray-900">Owner:</span> {{ $client->owner?->getName() ?: 'System' }}</div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-gray-600">
                        If you need to change grant type or switch between public and confidential, create a new client and revoke the old one.
                    </p>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
