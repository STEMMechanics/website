<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Messaging', 'route' => route('admin.stemcraft.messages.index')],
            ['title' => 'Webhook Logs', 'route' => route('admin.stemcraft.webhook-logs.index')],
        ['title' => 'Management', 'route' => route('admin.stemcraft.management.index')],
    ]" />

    <x-container x-data="{ addAccountOpen: {{ $errors->has('platform') || $errors->has('username') || $errors->has('user_id') || $errors->has('is_whitelisted') || $errors->has('admin_notes') ? 'true' : 'false' }} }">
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="button" x-on:click="addAccountOpen = true">Add Account</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <x-ui.checkbox name="only_linked" value="1" label="Only linked" :checked="request()->boolean('only_linked')" :noWrapper="true" :inline="true" onchange="this.form.submit()" />
                    <div class="flex">
                        <input class="bg-white grow px-2.5 py-2.5 text-sm text-gray-900 rounded-l-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-indigo-300" type="text" name="search" placeholder="Search" value="{{ request('search', '') }}" />
                        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                    </div>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        <div
            x-show="addAccountOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-on:keydown.escape.window="addAccountOpen = false"
        >
            <div class="w-full max-w-3xl rounded-3xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Add Minecraft account</h2>
                    </div>
                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="addAccountOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.stemcraft.store') }}" class="mt-6">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <x-admin.user-selector-inline
                            :users="$minecraftUsers ?? collect()"
                            :selected-user-id="(string) old('user_id', '')"
                            field-name="user_id"
                            lookup-name="stemcraft_add_user_lookup"
                            label="Website User"
                            info="Optional. Leave blank for an admin-managed player record."
                            :allow-create="false"
                        />
                        <x-ui.select name="platform" label="Platform">
                            <option value="java" {{ old('platform', 'java') === 'java' ? 'selected' : '' }}>Java</option>
                            <option value="bedrock" {{ old('platform') === 'bedrock' ? 'selected' : '' }}>Bedrock</option>
                        </x-ui.select>
                        <x-ui.input name="username" label="Minecraft Username" value="{{ old('username') }}" />
                    </div>

                    <input type="hidden" name="is_whitelisted" value="0" />
                    <x-ui.checkbox label="Whitelisted" name="is_whitelisted" value="1" :checked="(bool) old('is_whitelisted', true)" />
                    <x-ui.input
                        type="textarea"
                        name="admin_notes"
                        label="Private admin notes"
                        value="{{ old('admin_notes') }}"
                        info="Internal only. Useful for contact details, context, or moderation notes."
                    />

                    <div class="mt-5 flex justify-end gap-3">
                        <x-ui.button type="button" color="outline" x-on:click="addAccountOpen = false">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save account</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        @if($accounts->isEmpty())
            <x-none-found item="stemcraft accounts" search="{{ request('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Player</th>
                    <th class="hidden md:table-cell">Website User</th>
                    <th class="hidden md:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Last Seen</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($accounts as $account)
                        @php($status = $account->statusSummary())
                        <tr class="{{ (string) request('highlight') === (string) $account->id ? 'bg-amber-50/80' : '' }}">
                            <td>
                                <div>{{ $account->username }}</div>
                                <div class="text-xs text-gray-600 uppercase">{{ $account->platform }}</div>
                                <div class="text-xs text-gray-500 mt-1">UUID: <span class="font-mono">{{ $account->uuid ?: 'Pending first login' }}</span></div>
                                <div class="md:hidden text-xs mt-1 {{ $status['class'] }}">{{ $status['label'] }}</div>
                                @if($account->admin_notes)
                                    <div class="mt-2 text-xs text-gray-500 whitespace-nowrap">Private note saved</div>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">
                                @if($account->user)
                                    <a href="{{ route('admin.user.edit', $account->user) }}" class="text-primary-color hover:underline">{{ $account->user->getName() ?: $account->user->email }}</a>
                                    <div class="text-xs text-gray-500">{{ $account->user->email }}</div>
                                @else
                                    <span class="text-gray-500">Not connected</span>
                                @endif
                            </td>
                            <td class="hidden md:table-cell"><span class="{{ $status['class'] }}">{{ $status['label'] }}</span></td>
                            <td class="hidden lg:table-cell">{{ $account->last_seen_at?->format('j M Y g:i a') ?? 'Never' }}</td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    <a href="{{ route('admin.stemcraft.edit', $account) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $accounts->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
