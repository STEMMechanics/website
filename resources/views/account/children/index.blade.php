@php
    $childAccounts = collect($childAccounts ?? []);
    $childAccountsEnabled = \App\Models\SiteOption::booleanValue('users.child-accounts-enabled', true);
@endphp

<x-layout>
    <x-mast description="{{ $childAccountsEnabled ? 'Create and edit child accounts without leaving the user menu.' : 'Create and edit linked accounts without leaving the user menu.' }}">{{ $childAccountsEnabled ? 'Child Accounts' : 'Linked Accounts' }}</x-mast>

    <x-container inner-class="max-w-6xl">
        <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $childAccountsEnabled ? 'Managed child accounts' : 'Managed linked accounts' }}</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $childAccountsEnabled
                                ? 'Create child accounts with their own username, password, and avatar settings.'
                                : 'Review linked accounts and manage their username, password, and avatar settings.' }}
                        </p>
                    </div>

                    @if($childAccountsEnabled)
                        <x-ui.button href="{{ route('account.children.create') }}">Create child account</x-ui.button>
                    @endif
                </div>

                @if($childAccounts->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-sm text-gray-500">
                        {{ $childAccountsEnabled ? 'No child accounts have been created yet.' : 'No linked accounts have been created yet.' }}
                    </div>
                @else
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach($childAccounts as $childAccount)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ $childAccount->username }}</div>
                                    </div>
                                    <x-ui.button
                                        href="{{ route('account.children.edit', $childAccount) }}"
                                        class="text-xs"
                                    >Manage</x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Account access</div>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">What this page controls</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        {{ $childAccountsEnabled
                            ? 'Child accounts can sign in separately while you keep control over account access.'
                            : 'Linked accounts can sign in separately while you keep control over account access.' }}
                    </p>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Back</div>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Return to your account settings to manage profile details, devices, and security settings.
                    </p>
                    <div class="mt-4">
                        <x-ui.button href="{{ route('account.show') }}" color="primary-outline">Back to account settings</x-ui.button>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
