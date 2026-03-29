<x-layout>
    <x-mast description="Review linked Minecraft accounts and whitelist status.">STEMCraft</x-mast>

    @php
        $canManageAccounts = (bool) ($canManageAccounts ?? false);
        $canCreateAccounts = (bool) ($canCreateAccounts ?? false);
        $childAccountsEnabled = \App\Models\SiteOption::booleanValue('users.child-accounts-enabled', true);
        $hasChildAccounts = (bool) (auth()->user()?->isFullAccount() ? auth()->user()?->children()->whereNull('anonymized_at')->exists() : false);
        $isChildAccount = (bool) auth()->user()?->isChildAccount();
        $ownerOptions = collect($ownerOptions ?? []);
        $currentUserId = (string) auth()->id();
        $accountCount = $accounts->count();
        $whitelistedCount = $accounts->filter(fn ($account) => (bool) $account->is_whitelisted)->count();
        $activeRestrictionCount = $accounts->filter(fn ($account) => $account->activePenalty() || $account->activeBlacklistEntry())->count();
    @endphp

    <x-container
        inner-class="max-w-6xl"
        class="py-8"
        x-data="{
            addAccountOpen: {{ $errors->has('platform') || $errors->has('username') ? 'true' : 'false' }},
            ownerDialogOpen: false,
            ownerDialog: {
                action: '',
                title: '',
                description: '',
                currentOwnerId: '',
                currentOwnerLabel: '',
                accountLabel: '',
            },
            openOwnerDialog(payload) {
                this.ownerDialog = {
                    ...this.ownerDialog,
                    ...payload,
                };
                this.ownerDialogOpen = true;
                this.$nextTick(() => {
                    this.$refs.ownerPicker?.focus();
                });
            },
            closeOwnerDialog() {
                this.ownerDialogOpen = false;
            },
        }"
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
                        {{ $canCreateAccounts ? 'Account access' : ($canManageAccounts ? 'Limited access' : 'Read-only access') }}
                    </div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">
                        {{ $canCreateAccounts
                            ? 'Keep your STEMCraft player accounts linked and ready to use.'
                            : ($canManageAccounts
                                ? 'View your linked STEMCraft accounts and whitelist status.'
                                : 'View your linked STEMCraft accounts and whitelist status.') }}
                    </h2>
                    <p class="mt-3 text-base leading-7 text-gray-600">
                        {{ $canCreateAccounts
                            ? 'Use this page to connect Java or Bedrock usernames to your website profile, check whitelist status, and jump into player stats when you need them.'
                            : ($canManageAccounts
                                ? 'You can review linked accounts and manage ownership here. Player stats now live on the leaderboard page.'
                                : ($childAccountsEnabled
                                    ? 'This page shows the Minecraft accounts linked to your profile. Player stats now live on the leaderboard page, while account management stays with the parent or admin account.'
                                    : 'This page shows the Minecraft accounts linked to your profile. Player stats now live on the leaderboard page.')) }}
                    </p>
                </div>

                @if($canCreateAccounts)
                    <div class="mt-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-yellow-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Add another Minecraft account</h3>
                            <p class="mt-1 text-sm text-gray-600">UUIDs are filled automatically after the server next sees that player.</p>
                        </div>
                        <x-ui.button type="button" x-on:click="addAccountOpen = true">Add Minecraft Account</x-ui.button>
                    </div>
                @elseif($canManageAccounts)
                    <div class="mt-8 rounded-3xl border border-sky-100 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-900">
                        You can view linked Minecraft stats and manage existing ownership, but creating new accounts is restricted to Minecraft access holders.
                    </div>
                @else
                    <div class="mt-8 rounded-3xl border border-sky-100 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-900">
                        Linked profiles can view their linked Minecraft stats here, but they cannot add or remove linked accounts.
                    </div>
                @endif

                @if($accounts->isEmpty())
                    <section class="mt-8 rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                        <h3 class="text-lg font-semibold text-gray-900">No STEMCraft accounts linked yet</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            {{ $canCreateAccounts
                                ? 'Add your Minecraft username to connect it with your website profile. Once the server sees that player, the account record will pick up its UUID automatically.'
                                : ($canManageAccounts
                                    ? 'This profile can view linked accounts, but adding new Minecraft usernames is restricted.'
                                    : 'No Minecraft accounts are currently linked to this profile.') }}
                        </p>
                    </section>
                @else
                    <div class="mt-8 space-y-6">
                        @foreach($accounts as $account)
                            @php
                                $status = $account->statusSummary();
                                $owner = $account->user;
                                $ownerLabel = $owner?->id === $currentUserId
                                    ? 'You'
                                    : ($owner?->username ?: $owner?->getName() ?: 'Unknown');
                                $playerStatsUrl = route('stemcraft.leaderboards', [
                                    'player' => $account->uuid ?: $account->username,
                                ]);
                            @endphp
                            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm sm:p-6">
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <h3 class="text-xl font-semibold text-gray-900">{{ $account->username }}</h3>
                                                <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $account->platform }}</span>
                                                <span class="text-sm font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span>
                                            </div>
                                            @if($childAccountsEnabled || $hasChildAccounts)
                                                <div class="text-sm text-gray-500">
                                                    <span class="font-semibold text-gray-700">Linked to</span>: {{ $ownerLabel }}
                                                    <span
                                                            class="text-xs ml-2 text-primary-color hover:text-primary-color-dark hover:underline cursor-pointer"
                                                            data-dialog-action="{{ route('account.stemcraft.owner.update', $account) }}"
                                                            data-dialog-title="Update linked profile"
                                                            data-dialog-description="Move this Minecraft account to your profile or one of your child profiles."
                                                            data-dialog-current-owner-id="{{ (string) $account->user_id }}"
                                                            data-dialog-current-owner-label="{{ e($ownerLabel) }}"
                                                            data-dialog-account-label="{{ e($account->username.' ('.$account->platform.')') }}"
                                                            x-on:click="openOwnerDialog({
                                                            action: $el.dataset.dialogAction,
                                                            title: $el.dataset.dialogTitle,
                                                            description: $el.dataset.dialogDescription,
                                                            currentOwnerId: $el.dataset.dialogCurrentOwnerId,
                                                            currentOwnerLabel: $el.dataset.dialogCurrentOwnerLabel,
                                                            accountLabel: $el.dataset.dialogAccountLabel,
                                                        })"
                                                    >
                                                        [Change]
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="mt-1 text-xs font-mono text-gray-900">{{ $account->uuid ?: 'Pending first login' }}</div>
                                        </div>

                                        <div class="flex flex-col gap-2">
                                            <x-ui.button
                                                    href="{{ $playerStatsUrl }}"
                                                    type="button"
                                                    color="primary-outline"
                                                    class="w-full sm:w-auto"
                                            >
                                                View player stats
                                            </x-ui.button>
                                            @if($canManageAccounts)
                                                <form method="POST" action="{{ route('account.stemcraft.destroy', $account) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Remove STEMCraft account?', 'This will de-whitelist the account and disconnect it from your website profile.', $el)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button class="w-full sm:w-auto" type="submit" color="danger-outline">Remove account</x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last seen</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $account->last_seen_at?->format('j M Y g:i a') ?? 'Never' }}</div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sessions</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $account->sessions->count() }}</div>
                                        </div>
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penalties</div>
                                            <div class="mt-1 text-sm text-gray-900">{{ $account->penalties->count() + $account->blacklistEntries->count() }}</div>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <summary class="cursor-pointer text-sm font-semibold text-gray-900">Recent sessions</summary>
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
                                        </details>

                                        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                            <summary class="cursor-pointer text-sm font-semibold text-gray-900">Penalties</summary>
                                            <p class="mt-1 text-sm text-gray-600">Recent bans, mutes, or other moderation records linked to this account.</p>

                                            @if($account->penalties->isEmpty() && $account->blacklistEntries->isEmpty())
                                                <p class="mt-4 text-sm text-gray-500">No penalties recorded.</p>
                                            @else
                                                <div class="mt-4 space-y-3">
                                                    @foreach($account->penalties->take(5) as $penalty)
                                                        @php
                                                            $historyStatus = 'Recorded';
                                                            $historyStatusClass = 'text-gray-600';
                                                            if ($penalty->lifted_at) {
                                                                $historyStatus = 'Lifted '.($penalty->lifted_at->format('j M Y g:i a') ?? '-');
                                                                $historyStatusClass = 'text-green-700';
                                                            } elseif ($penalty->is_permanent) {
                                                                $historyStatus = 'Permanent';
                                                                $historyStatusClass = $penalty->type === \App\Models\MinecraftPenalty::TYPE_BAN ? 'text-red-700' : 'text-amber-700';
                                                            } elseif ($penalty->ends_at) {
                                                                if ($penalty->ends_at->isFuture()) {
                                                                    $historyStatus = 'Until '.$penalty->ends_at->format('j M Y g:i a');
                                                                    $historyStatusClass = $penalty->type === \App\Models\MinecraftPenalty::TYPE_BAN ? 'text-red-700' : 'text-amber-700';
                                                                } else {
                                                                    $historyStatus = 'Expired '.$penalty->ends_at->format('j M Y g:i a');
                                                                    $historyStatusClass = 'text-gray-600';
                                                                }
                                                            }
                                                        @endphp
                                                            <div class="rounded-2xl border border-gray-200 bg-slate-50 px-4 py-3">
                                                            <div class="flex flex-wrap items-center gap-3">
                                                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-900">{{ $penalty->type }}</span>
                                                                <span class="text-sm text-gray-600">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</span>
                                                                <span class="text-sm {{ $historyStatusClass }}">{{ $historyStatus }}</span>
                                                            </div>
                                                            @if($penalty->reason)
                                                                <div class="mt-2 text-sm text-gray-700">{{ $penalty->reason }}</div>
                                                            @endif
                                                            @if($penalty->by_username)
                                                                <div class="mt-2 text-xs text-gray-500">By {{ $penalty->by_username }}</div>
                                                            @endif
                                                            @if($penalty->lift_reason)
                                                                <div class="mt-2 text-xs text-gray-500">Lift reason: {{ $penalty->lift_reason }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach

                                                    @foreach($account->blacklistEntries->take(5) as $entry)
                                                        <div class="rounded-2xl border border-gray-200 bg-slate-50 px-4 py-3">
                                                            <div class="flex flex-wrap items-center gap-3">
                                                                <span class="text-sm font-semibold uppercase tracking-wide text-gray-900">Legacy ban</span>
                                                                <span class="text-sm text-gray-600">{{ $entry->starts_at?->format('j M Y g:i a') ?? '-' }}</span>
                                                                <span class="text-sm {{ $entry->isActive() ? 'text-red-700' : 'text-gray-600' }}">{{ $entry->isActive() ? ($entry->is_permanent ? 'Permanent' : 'Active') : 'Expired' }}</span>
                                                            </div>
                                                            @if($entry->reason)
                                                                <div class="mt-2 text-sm text-gray-700">{{ $entry->reason }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </details>
                                    </div>
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
                        <h2 class="text-lg font-semibold text-gray-900">{{ $canCreateAccounts ? 'Before you add an account' : ($childAccountsEnabled ? 'Need a parent to link an account?' : 'Need help linking an account?') }}</h2>
                        <div class="mt-4 space-y-4 text-sm leading-6 text-gray-600">
                            @if($canCreateAccounts)
                                <p>Add the exact Minecraft username you use on the server.</p>
                                <p>Java and Bedrock usernames can be linked separately if you use both.</p>
                                <p>If the UUID still says pending, it usually means that player has not logged into STEMCraft yet.</p>
                            @elseif($isChildAccount)
                                <p>{{ $childAccountsEnabled ? 'This is a read-only view for child accounts. A parent or admin can link a Minecraft username to your profile.' : 'This is a read-only linked-account view. A parent or admin can link a Minecraft username to your profile.' }}</p>
                                <p>Once linked, your profile will show your account stats, sessions, and moderation history here.</p>
                            @elseif($canManageAccounts)
                                <p>You can view linked Minecraft accounts and manage existing ownership from this page.</p>
                                <p>Adding new Minecraft usernames is restricted to parents or admins with Minecraft access.</p>
                            @else
                            <p>This page is available for inspection only. You can review the linked accounts and their stats, but management actions stay with the parent or admin account.</p>
                            <p>If you need a Minecraft username linked, ask a parent or admin to update it from their account.</p>
                        @endif
                    </div>
                </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Need help?</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-600">If an account is not linking correctly, or if you need help with whitelist access, get in touch and include the username you are trying to use. Player stats now live on the leaderboard page.</p>
                        <div class="mt-5 flex flex-col gap-3">
                            <x-ui.button href="{{ route('stemcraft.leaderboards') }}" color="primary-outline">Player leaderboards</x-ui.button>
                            <x-ui.button href="{{ route('contact') }}">Contact support</x-ui.button>
                        <x-ui.button href="{{ route('account.show') }}" color="primary-outline">Back to account settings</x-ui.button>
                    </div>
                </section>
            </div>
        </div>

        <template x-teleport="body">
            <div
                x-show="ownerDialogOpen"
                x-cloak
                class="fixed inset-0 z-280 flex items-end justify-center bg-slate-950/55 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="minecraft-owner-dialog-title"
                @click.self="closeOwnerDialog()"
                @keydown.escape.window="if (ownerDialogOpen) { closeOwnerDialog() }"
            >
                <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="border-b border-gray-200 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 id="minecraft-owner-dialog-title" class="text-xl font-bold text-gray-900" x-text="ownerDialog.title || 'Update linked profile'"></h2>
                                <p class="mt-2 text-sm leading-6 text-gray-600" x-text="ownerDialog.description || 'Choose which profile should own this Minecraft account.'"></p>
                            </div>
                            <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeOwnerDialog()" aria-label="Close ownership dialog">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <form method="POST" :action="ownerDialog.action" class="overflow-y-auto px-6 py-5">
                        @csrf
                        @method('PATCH')
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Minecraft account</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900" x-text="ownerDialog.accountLabel"></div>
                            <div class="mt-2 text-xs text-gray-500">
                                Currently linked to <span class="font-semibold text-gray-700" x-text="ownerDialog.currentOwnerLabel"></span>
                            </div>
                        </div>

                        <div class="mt-5">
                            <x-ui.select name="user_id" label="Assign to profile" x-ref="ownerPicker" x-model="ownerDialog.currentOwnerId">
                                @foreach($ownerOptions as $ownerOption)
                                    <option value="{{ $ownerOption['id'] }}">{{ $ownerOption['label'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" color="secondary" x-on:click.prevent="closeOwnerDialog()">Cancel</x-ui.button>
                            <x-ui.button type="submit">Update owner</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        @if($canCreateAccounts)
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
                            @if($canCreateAccounts)
                                <x-ui.select name="user_id" label="Assign to profile">
                                    <option value="{{ $currentUserId }}" {{ (string) old('user_id', $currentUserId) === $currentUserId ? 'selected' : '' }}>You</option>
                                    @foreach($ownerOptions->filter(fn ($ownerOption) => (string) $ownerOption['id'] !== $currentUserId) as $ownerOption)
                                        <option value="{{ $ownerOption['id'] }}" {{ (string) old('user_id') === (string) $ownerOption['id'] ? 'selected' : '' }}>
                                            {{ $ownerOption['label'] }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            @endif
                        </div>
                        <div class="mt-5 flex justify-end gap-3">
                            <x-ui.button type="button" color="outline" x-on:click="addAccountOpen = false">Cancel</x-ui.button>
                            <x-ui.button type="submit">Add account</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-container>
</x-layout>
