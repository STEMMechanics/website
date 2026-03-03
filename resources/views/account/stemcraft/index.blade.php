<x-layout>
    <x-mast description="Manage the Minecraft accounts linked to your profile, keep track of whitelist access, and review recent STEMCraft activity.">STEMCraft</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8" x-data="{ addAccountOpen: {{ $errors->has('platform') || $errors->has('username') ? 'true' : 'false' }} }">
        @php
            $accountCount = $accounts->count();
            $whitelistedCount = $accounts->filter(fn ($account) => (bool) $account->is_whitelisted)->count();
            $activeRestrictionCount = $accounts->filter(fn ($account) => $account->activePenalty() || $account->activeBlacklistEntry())->count();
        @endphp

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Account access</div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Keep your STEMCraft player accounts linked and ready to use.</h2>
                    <p class="mt-3 text-base leading-7 text-gray-600">Use this page to connect your Java or Bedrock usernames to your website profile, check whether they are currently whitelisted, and review recent sessions or moderation history tied to each account.</p>
                </div>

                <div class="mt-8 flex flex-col gap-4 rounded-3xl bg-gray-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Add another Minecraft account</h3>
                        <p class="mt-1 text-sm text-gray-600">UUIDs are filled automatically after the STEMCraft server next sees that player.</p>
                    </div>
                    <x-ui.button type="button" x-on:click="addAccountOpen = true">Add Minecraft Account</x-ui.button>
                </div>

                @if($accounts->isEmpty())
                    <section class="mt-8 rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                        <h3 class="text-lg font-semibold text-gray-900">No STEMCraft accounts linked yet</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Add your Minecraft username to connect it with your website profile. Once the server sees that player, the account record will pick up its UUID automatically.</p>
                    </section>
                @else
                    <div class="mt-8 space-y-6">
                        @foreach($accounts as $account)
                            @php($status = $account->statusSummary())
                            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h3 class="text-xl font-semibold text-gray-900">{{ $account->username }}</h3>
                                            <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700">{{ $account->platform }}</span>
                                            <span class="text-sm font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        </div>

                                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">UUID</div>
                                                <div class="mt-1 break-all text-sm text-gray-900 font-mono">{{ $account->uuid ?: 'Pending first login' }}</div>
                                            </div>
                                            <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Whitelist</div>
                                                <div class="mt-1 text-sm text-gray-900">{{ $account->is_whitelisted ? 'Enabled' : 'Not enabled' }}</div>
                                            </div>
                                            <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last seen</div>
                                                <div class="mt-1 text-sm text-gray-900">{{ $account->last_seen_at?->format('j M Y g:i a') ?? 'Never' }}</div>
                                            </div>
                                            <div class="rounded-2xl bg-gray-50 px-4 py-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recent sessions</div>
                                                <div class="mt-1 text-sm text-gray-900">{{ $account->sessions->take(5)->count() }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('account.stemcraft.destroy', $account) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Remove STEMCraft account?', 'This will de-whitelist the account and disconnect it from your website profile.', $el)">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" color="danger-outline">Remove account</x-ui.button>
                                    </form>
                                </div>

                                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                                    <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                        <h4 class="text-sm font-semibold text-gray-900">Recent sessions</h4>
                                        <p class="mt-1 text-sm text-gray-600">The latest login activity recorded for this player.</p>

                                        @if($account->sessions->isEmpty())
                                            <p class="mt-4 text-sm text-gray-500">No login history yet.</p>
                                        @else
                                            <div class="mt-4 space-y-3">
                                                @foreach($account->sessions->take(5) as $session)
                                                    <div class="rounded-2xl bg-white px-4 py-3">
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Login</div>
                                                                <div class="mt-1 text-sm text-gray-900">{{ $session->logged_in_at?->format('j M Y g:i a') ?? '-' }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Logout</div>
                                                                <div class="mt-1 text-sm text-gray-900">{{ $session->logged_out_at?->format('j M Y g:i a') ?? '-' }}</div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Duration</div>
                                                                <div class="mt-1 text-sm text-gray-900">{{ $session->formattedDuration() ?? '-' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>

                                    <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                        <h4 class="text-sm font-semibold text-gray-900">Penalties</h4>
                                        <p class="mt-1 text-sm text-gray-600">Recent bans, mutes, or other moderation records linked to this account.</p>

                                        @if($account->penalties->isEmpty())
                                            <p class="mt-4 text-sm text-gray-500">No penalties recorded.</p>
                                        @else
                                            <div class="mt-4 space-y-3">
                                                @foreach($account->penalties->take(5) as $penalty)
                                                    <div class="rounded-2xl bg-white px-4 py-3">
                                                        <div class="flex flex-wrap items-center gap-3">
                                                            <span class="text-sm font-semibold uppercase tracking-wide text-gray-900">{{ $penalty->type }}</span>
                                                            <span class="text-sm text-gray-600">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</span>
                                                            @if($penalty->is_permanent)
                                                                <span class="text-sm text-red-700">Permanent</span>
                                                            @elseif($penalty->ends_at)
                                                                <span class="text-sm text-gray-600">Until {{ $penalty->ends_at->format('j M Y g:i a') }}</span>
                                                            @endif
                                                        </div>
                                                        @if($penalty->reason)
                                                            <div class="mt-2 text-sm text-gray-700">{{ $penalty->reason }}</div>
                                                        @endif
                                                        @if($penalty->by_username)
                                                            <div class="mt-2 text-xs text-gray-500">By {{ $penalty->by_username }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">STEMCraft overview</h2>
                    <div class="mt-4 space-y-4 text-sm text-gray-600">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Linked accounts</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $accountCount }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Whitelisted</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $whitelistedCount }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active restrictions</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $activeRestrictionCount }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Before you add an account</h2>
                    <div class="mt-4 space-y-4 text-sm leading-6 text-gray-600">
                        <p>Add the exact Minecraft username you use on the server.</p>
                        <p>Java and Bedrock usernames can be linked separately if you use both.</p>
                        <p>If the UUID still says pending, it usually means that player has not logged into STEMCraft yet.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Need help?</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If an account is not linking correctly, or if you need help with whitelist access, get in touch and include the username you are trying to use.</p>
                    <div class="mt-5 flex flex-col gap-3">
                        <x-ui.button href="{{ route('contact') }}">Contact support</x-ui.button>
                        <x-ui.button href="{{ route('account.show') }}" color="primary-outline">Back to account settings</x-ui.button>
                    </div>
                </section>
            </div>
        </div>

        <div
            x-show="addAccountOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="addAccountOpen = false"
        >
            <div class="w-full max-w-2xl rounded-3xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Add Minecraft account</h2>
                        <p class="mt-1 text-sm text-gray-600">Link a Java or Bedrock account to your website profile. The UUID is filled automatically after the server next sees this player.</p>
                    </div>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="addAccountOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('account.stemcraft.store') }}" class="mt-6">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.select name="platform" label="Platform">
                            <option value="java" {{ old('platform') === 'java' ? 'selected' : '' }}>Java</option>
                            <option value="bedrock" {{ old('platform') === 'bedrock' ? 'selected' : '' }}>Bedrock</option>
                        </x-ui.select>
                        <x-ui.input name="username" label="Minecraft Username" value="{{ old('username') }}" />
                    </div>
                    <div class="mt-5 flex justify-end gap-3">
                        <x-ui.button type="button" color="outline" x-on:click="addAccountOpen = false">Cancel</x-ui.button>
                        <x-ui.button type="submit">Add account</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </x-container>
</x-layout>
