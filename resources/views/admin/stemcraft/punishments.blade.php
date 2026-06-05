<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Messaging', 'route' => route('admin.stemcraft.messages.index')],
            ['title' => 'Webhook Logs', 'route' => route('admin.stemcraft.webhook-logs.index')],
        ['title' => 'Management', 'route' => route('admin.stemcraft.management.index')],
    ]" />

    <x-container class="mt-8" inner-class="flex flex-col gap-8">
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-900">Apply punishment</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Use this page for bans, mutes, warnings, and other moderation actions. Active bans and mutes can also be lifted from here.</p>
            </div>

            <form method="POST" action="{{ route('admin.stemcraft.punishments.store') }}" class="mt-6">
                @csrf
                <div
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    x-data="{
                        selectedUsername: @js((string) old('username')),
                        selectedAccountId: @js((string) old('minecraft_account_id')),
                        options: @js($savedAccounts ?? []),
                        optionIndex: {},
                        init() {
                            this.optionIndex = {};
                            for (const option of this.options) {
                                const key = String(option.label ?? '').trim().toLowerCase();
                                if (key !== '') {
                                    this.optionIndex[key] = option;
                                }
                            }
                            this.syncSelectedAccount();
                            this.$watch('selectedUsername', () => this.syncSelectedAccount());
                        },
                        syncSelectedAccount() {
                            const key = String(this.selectedUsername ?? '').trim().toLowerCase();
                            const matched = key !== '' ? (this.optionIndex[key] ?? null) : null;
                            this.selectedAccountId = matched ? String(matched.id ?? '') : '';
                        }
                    }"
                >
                    <input type="hidden" name="minecraft_account_id" x-model="selectedAccountId" />
                    <x-ui.input
                        name="username"
                        label="Minecraft Username"
                        value="{{ old('username') }}"
                        x-model="selectedUsername"
                        :suggestions="$savedUsernames ?? []"
                        info="Pick a suggestion like username (java) or username (bedrock), or type a new username manually."
                    />
                    <x-ui.select name="type" label="Type">
                        @foreach(\App\Models\MinecraftPenalty::TYPES as $type)
                            <option value="{{ $type }}" {{ old('type', \App\Models\MinecraftPenalty::TYPE_BAN) === $type ? 'selected' : '' }}>{{ \App\Models\MinecraftPenalty::typeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input
                        name="ends_at"
                        label="Until"
                        type="datetime-local"
                        value="{{ old('ends_at') }}"
                        info="Leave blank for a permanent ban or mute. Kicks are always immediate."
                    />
                    <x-ui.input name="reason" label="Reason" value="{{ old('reason') }}" />
                </div>
                <div class="mt-4 text-xs text-gray-500">If the player already has a STEMCraft account record, the punishment will be linked to it automatically. Leave the end date blank for a permanent ban, mute, or warning. Active bans and mutes can be lifted below.</div>
                <div class="mt-5 flex justify-end">
                    <x-ui.button type="submit" color="danger">Apply punishment</x-ui.button>
                </div>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-gray-900">Active bans and mutes</h2>
            @if($activePenalties->isEmpty() && $legacyActiveBans->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No active bans or mutes.</p>
            @else
                <div class="mt-6 space-y-4">
                    @foreach($activePenalties as $penalty)
                        <div class="rounded-2xl border {{ $penalty->type === \App\Models\MinecraftPenalty::TYPE_BAN ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="font-semibold text-gray-900">{{ $penalty->username }}</div>
                                        <x-ui.badge :color="$penalty->type === \App\Models\MinecraftPenalty::TYPE_BAN ? 'danger' : 'warning'" size="xs" uppercase="true">{{ \App\Models\MinecraftPenalty::typeLabel((string) $penalty->type) }}</x-ui.badge>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">UUID: <span class="font-mono">{{ $penalty->uuid ?: 'Pending resolution' }}</span></div>
                                    @if($penalty->reason)
                                        <div class="mt-2 text-sm text-gray-700">{{ $penalty->reason }}</div>
                                    @endif
                                    <div class="mt-2 text-xs text-gray-600">
                                        Started {{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}
                                        @if($penalty->is_permanent)
                                            | Permanent
                                        @elseif($penalty->ends_at)
                                            | Until {{ $penalty->ends_at->format('j M Y g:i a') }}
                                        @endif
                                        @php
                                            $appliedByName = $penalty->byUser?->getName() ?: $penalty->by_username;
                                        @endphp
                                        @if($appliedByName)
                                            | By {{ $appliedByName }}{{ $penalty->by_user_id ? ' (website user)' : '' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full lg:max-w-sm">
                                    <form method="POST" action="{{ route('admin.stemcraft.punishments.destroy', $penalty) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Lift punishment?', 'This will notify the STEMCraft server to lift the active punishment.', $el, 'Lift')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.input name="lift_reason" label="Lift reason" value="" />
                                        <div class="flex justify-end">
                                            <x-ui.button type="submit" color="danger-outline">Lift {{ \App\Models\MinecraftPenalty::typeLabel((string) $penalty->type) }}</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @foreach($legacyActiveBans as $entry)
                        <div class="rounded-2xl border border-gray-300 bg-gray-50 p-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="font-semibold text-gray-900">{{ $entry->username }}</div>
                                        <x-ui.badge color="danger" size="xs" uppercase="true">Legacy ban</x-ui.badge>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">UUID: <span class="font-mono">{{ $entry->uuid ?: 'Pending resolution' }}</span></div>
                                    @if($entry->reason)
                                        <div class="mt-2 text-sm text-gray-700">{{ $entry->reason }}</div>
                                    @endif
                                    <div class="mt-2 text-xs text-gray-600">
                                        Started {{ $entry->starts_at?->format('j M Y g:i a') ?? '-' }}
                                        @if($entry->is_permanent)
                                            | Permanent
                                        @elseif($entry->ends_at)
                                            | Until {{ $entry->ends_at->format('j M Y g:i a') }}
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.stemcraft.blacklist.destroy', $entry) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Lift legacy ban?', 'This will notify the STEMCraft server to lift the active legacy ban.', $el, 'Lift')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" color="danger-outline">Lift legacy ban</x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-gray-900">Recent punishment history</h2>
            @if($recentPenalties->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No punishments recorded yet.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500">
                            <th class="py-2 pr-4">Player</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Reason</th>
                            <th class="py-2 pr-4">Started</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($recentPenalties as $penalty)
                            @php
                                $platform = strtolower(trim((string) ($penalty->account?->platform ?? '')));
                                $statusLabel = 'Expired';
                                if ($penalty->lifted_at) {
                                    $statusLabel = 'Lifted';
                                } elseif ($penalty->type === \App\Models\MinecraftPenalty::TYPE_KICK) {
                                    $statusLabel = 'Recorded';
                                } elseif ($penalty->type === \App\Models\MinecraftPenalty::TYPE_WARN) {
                                    $statusLabel = 'Recorded';
                                } elseif ($penalty->isActiveRestriction()) {
                                    $statusLabel = 'Active';
                                } elseif ($penalty->is_permanent) {
                                    $statusLabel = 'Permanent';
                                }
                            @endphp
                            <tr class="border-b border-gray-100 align-top">
                                <td class="py-3 pr-4">
                                    <div class="font-semibold text-gray-900">
                                        {{ $penalty->username }}
                                        @if($platform !== '')
                                            <span class="ml-1 text-xs font-normal text-gray-500">({{ $platform }})</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $penalty->uuid ?: 'Pending resolution' }}</div>
                                </td>
                                <td class="py-3 pr-4 uppercase">{{ $penalty->type }}</td>
                                <td class="py-3 pr-4">{{ $penalty->reason ?: '-' }}</td>
                                <td class="py-3 pr-4">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</td>
                                <td class="py-3 pr-4">
                                    <div>{{ $statusLabel }}</div>
                                    @php
                                        $appliedByName = $penalty->byUser?->getName() ?: $penalty->by_username;
                                        $liftedByName = $penalty->liftedByUser?->getName() ?: $penalty->lifted_by_username;
                                    @endphp
                                    @if($appliedByName)
                                        <div class="text-xs text-gray-500">Applied by {{ $appliedByName }}{{ $penalty->by_user_id ? ' (website user)' : '' }}</div>
                                    @endif
                                    @if($penalty->lifted_at)
                                        <div class="text-xs text-gray-500">
                                            Lifted {{ $penalty->lifted_at->format('j M Y g:i a') }}
                                            @if($liftedByName)
                                                by {{ $liftedByName }}{{ $penalty->lifted_by_user_id ? ' (website user)' : '' }}
                                            @endif
                                        </div>
                                        @if($penalty->lift_reason)
                                            <div class="text-xs text-gray-500">Lift reason: {{ $penalty->lift_reason }}</div>
                                        @endif
                                    @elseif($penalty->ends_at)
                                        <div class="text-xs text-gray-500">Until {{ $penalty->ends_at->format('j M Y g:i a') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $recentPenalties->appends(request()->query())->links() }}
                </div>
            @endif
        </section>
    </x-container>
</x-layout>
