@php
    /** @var \App\Models\MinecraftAccount $account */
    $selectedUserId = (string) old('user_id', $account->user_id ?? '');
@endphp

<x-layout>
    <x-mast
        backRoute="admin.stemcraft.index"
        backTitle="STEMCraft Accounts"
        :tabs="[
            ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
            ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
            ['title' => 'Messaging', 'route' => route('admin.stemcraft.messages.index')],
            ['title' => 'Webhook Logs', 'route' => route('admin.stemcraft.webhook-logs.index')],
            ['title' => 'Management', 'route' => route('admin.stemcraft.management.index')],
        ]"
    >{{ $editing ? 'STEMCraft Account' : 'Add Minecraft Account' }}</x-mast>

    <x-container>
        <form method="POST" action="{{ $editing ? route('admin.stemcraft.update', $account) : route('admin.stemcraft.store') }}">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-4 mb-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $editing ? 'Account Details' : 'New Account Details' }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $editing ? 'Update how this player record is linked, whitelisted, and managed.' : 'Create a linked or admin-managed STEMCraft player record.' }}</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-admin.user-selector-inline
                        :users="$minecraftUsers ?? collect()"
                        :selected-user-id="$selectedUserId"
                        field-name="user_id"
                        lookup-name="stemcraft_linked_user_lookup"
                        label="Website User"
                        info="Optional. Leave this blank for an admin-managed account that is not linked to a website user yet."
                    />
                    <x-ui.select name="platform" label="Platform">
                        <option value="java" {{ old('platform', $account->platform) === 'java' ? 'selected' : '' }}>Java</option>
                        <option value="bedrock" {{ old('platform', $account->platform) === 'bedrock' ? 'selected' : '' }}>Bedrock</option>
                    </x-ui.select>
                    <x-ui.input name="username" label="Username" value="{{ old('username', $account->username) }}" />
                </div>
                <input type="hidden" name="is_whitelisted" value="0" />
                <x-ui.checkbox label="Whitelisted" name="is_whitelisted" value="1" :checked="(bool) old('is_whitelisted', $account->is_whitelisted)" />
                <x-ui.input
                    type="textarea"
                    name="admin_notes"
                    label="Private admin notes"
                    value="{{ old('admin_notes', $account->admin_notes) }}"
                    info="Internal only. Use this for contact context, moderation notes, or anything that should not be shown to players."
                />
                <div class="text-sm text-gray-600 mt-2">UUID: <span class="font-mono break-all">{{ $account->uuid ?: 'Pending first login' }}</span></div>
                <div class="text-xs text-gray-500 mt-1">If whitelist access is off, the player remains in your records but should not be able to join unless they are later re-enabled.</div>
                <div class="flex justify-end">
                    <x-ui.button type="submit">Save</x-ui.button>
                </div>
            </div>
        </form>

        @if($editing)
            @php($status = $account->statusSummary())
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="font-bold mb-2">Status</h2>
                    <div class="{{ $status['class'] }} font-semibold">{{ $status['label'] }}</div>
                    <div class="text-sm text-gray-600 mt-2">UUID: <span class="font-mono break-all">{{ $account->uuid ?: 'Pending first login' }}</span></div>
                    <div class="text-sm text-gray-600 mt-2">Last seen: {{ $account->last_seen_at?->format('j M Y g:i a') ?? 'Never' }}</div>
                    <div class="text-sm text-gray-600">Last login: {{ $account->last_login_at?->format('j M Y g:i a') ?? 'Never' }}</div>
                    <div class="text-sm text-gray-600">Last logout: {{ $account->last_logout_at?->format('j M Y g:i a') ?? 'Never' }}</div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
                    <h2 class="font-bold mb-2">Recent Sessions</h2>
                    @if($recentSessions->isEmpty())
                        <p class="text-sm text-gray-500">No sessions recorded.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                <tr class="text-left text-gray-500 border-b border-gray-200">
                                    <th class="py-2 pr-4">Login</th>
                                    <th class="py-2 pr-4">Logout</th>
                                    <th class="py-2 pr-4">Duration</th>
                                    <th class="py-2 pr-4">Server</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($recentSessions as $session)
                                    <tr class="border-b border-gray-100 align-top">
                                        <td class="py-2 pr-4">{{ $session->logged_in_at?->format('j M Y g:i a') ?? '-' }}</td>
                                        <td class="py-2 pr-4">{{ $session->logged_out_at?->format('j M Y g:i a') ?? '-' }}</td>
                                        <td class="py-2 pr-4">{{ $session->formattedDuration() ?? '-' }}</td>
                                        <td class="py-2 pr-4">{{ $session->server_name ?: '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
                    <h2 class="font-bold mb-2">Penalties</h2>
                    @if($recentPenalties->isEmpty())
                        <p class="text-sm text-gray-500">No penalties recorded.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($recentPenalties as $penalty)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="font-semibold uppercase">{{ $penalty->type }}</span>
                                        <span class="text-sm text-gray-600">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</span>
                                        @if($penalty->is_permanent)
                                            <span class="text-sm text-red-700">Permanent</span>
                                        @elseif($penalty->ends_at)
                                            <span class="text-sm text-gray-600">Until {{ $penalty->ends_at->format('j M Y g:i a') }}</span>
                                        @endif
                                        @if($penalty->lifted_at)
                                            <span class="text-sm text-green-700">Lifted {{ $penalty->lifted_at->format('j M Y g:i a') }}</span>
                                        @endif
                                    </div>
                                    @if($penalty->reason)
                                        <div class="text-sm text-gray-700 mt-1">{{ $penalty->reason }}</div>
                                    @endif
                                    @if($penalty->by_username)
                                        <div class="text-xs text-gray-500 mt-1">By {{ $penalty->by_username }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="font-bold mb-2">Ban History</h2>
                    @if($recentBlacklistEntries->isEmpty())
                        <p class="text-sm text-gray-500">No bans recorded.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($recentBlacklistEntries as $entry)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <div class="text-sm font-semibold {{ $entry->isActive() ? 'text-red-700' : 'text-gray-700' }}">{{ $entry->isActive() ? 'Active' : 'Inactive' }}</div>
                                    <div class="text-sm text-gray-600 mt-1">{{ $entry->starts_at?->format('j M Y g:i a') ?? '-' }}</div>
                                    @if($entry->reason)
                                        <div class="text-sm text-gray-700 mt-1">{{ $entry->reason }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="font-bold mb-2">Private Admin Notes</h2>
                    @if($account->admin_notes)
                        <div class="text-sm text-gray-700 whitespace-pre-line">{{ $account->admin_notes }}</div>
                    @else
                        <p class="text-sm text-gray-500">No private notes saved for this account.</p>
                    @endif
                </div>
            </div>
        @endif
    </x-container>
</x-layout>
