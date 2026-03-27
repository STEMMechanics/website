@php
    $connectedApps = collect($connectedApps ?? []);
@endphp

<x-layout>
    <x-mast description="Review which external platforms can access your account and revoke them at any time.">Connected Apps</x-mast>

    <x-container inner-class="max-w-6xl">
        <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Platforms with access</h2>
                        <p class="mt-1 text-sm text-gray-600">Disconnect any app you no longer trust. This only revokes access for your account.</p>
                    </div>

                    @if($connectedApps->isNotEmpty())
                        <form method="POST" action="{{ route('account.oauth-apps.destroy-all') }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" color="secondary" class="px-4! py-1.5!">Disconnect all</x-ui.button>
                        </form>
                    @endif
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($connectedApps as $connectedApp)
                        @php
                            /** @var \Laravel\Passport\Client $client */
                            $client = $connectedApp['client'];
                            $lastAuthorizedAt = $connectedApp['last_authorized_at'] ? \Illuminate\Support\Carbon::parse($connectedApp['last_authorized_at']) : null;
                        @endphp

                        <article class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-gray-900">{{ $client->name }}</h3>
                                        <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 text-xxs font-semibold uppercase tracking-wide text-gray-700">
                                            {{ (int) $connectedApp['token_count'] }} token{{ (int) $connectedApp['token_count'] === 1 ? '' : 's' }}
                                        </span>
                                    </div>

                                    <div class="mt-2 grid gap-1 text-sm text-gray-600">
                                        <div>
                                            <span class="font-semibold text-gray-900">Last authorized:</span>
                                            {{ $lastAuthorizedAt ? $lastAuthorizedAt->format('M j, Y g:i a') : 'Unknown' }}
                                        </div>
                                        <div>
                                            <span class="font-semibold text-gray-900">Redirect URIs:</span>
                                            {{ implode(', ', $client->redirect_uris ?: []) ?: 'Not shown by this platform' }}
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @forelse($connectedApp['scopes'] as $scope)
                                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700">{{ $scope }}</span>
                                        @empty
                                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500">No scopes recorded</span>
                                        @endforelse
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('account.oauth-apps.destroy', $client) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" color="danger-outline" class="px-4! py-1.5!">Disconnect</x-ui.button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-sm text-gray-500">
                            No external applications are currently connected to your account.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Account access</div>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">What this page controls</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Revoking access removes the tokens issued to that platform. The app will need to sign in again before it can access your account.
                    </p>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Back</div>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Return to your account settings to manage passwords, remembered devices, and profile details.
                    </p>
                    <div class="mt-4">
                        <x-ui.button href="{{ route('account.show') }}" color="primary-outline">Back to account settings</x-ui.button>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
