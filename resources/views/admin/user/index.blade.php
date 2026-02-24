<x-layout>
    <x-mast>Users</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.user.create') }}">Create User</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <form method="GET" action="{{ url()->current() }}">
                    <x-ui.checkbox
                        name="show_ghost"
                        value="1"
                        label="Show ghost users"
                        :checked="!empty($showGhostUsers)"
                        :noWrapper="true"
                        :inline="true"
                        inputClass="h-4 w-4 rounded mt-0"
                        labelClass="text-sm pt-0"
                        onchange="this.form.submit()" />
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <div class="flex relative flex-1">
                            <input
                                class="bg-white flex-grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300"
                                autocomplete="off"
                                placeholder="Search"
                                type="text"
                                name="search"
                                value="{{ request()->get('search', '') }}" />
                            <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                        </div>
                    </div>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if(session('status'))
        <div class="mb-4 text-green-600">
            {{ session('status') }}
        </div>
        @endif

        @if($users->isEmpty())
        <x-none-found item="posts" search="{{ request()->get('search') }}" />
        @else
        <x-ui.table>
            <x-slot:header>
                <th>Name</th>
                <th class="hidden md:table-cell">Email</th>
                <th class="hidden md:table-cell">Groups</th>
                <th>Actions</th>
            </x-slot:header>
            <x-slot:body>
                @foreach ($users as $user)
                @php
                $groupSlugs = $user->groupSlugs();
                $isGhostUser = is_null($user->email_verified_at);
                @endphp
                <tr class="{{ $isGhostUser ? 'italic text-gray-700' : '' }}">
                    <td>
                        <div>{{ trim(($user->firstname ?? '').' '.($user->surname ?? '')) ?: '-' }}</div>
                        <div class="md:hidden text-xs text-gray-600 mt-1">{{ $user->email ?: '-' }}</div>
                        @if($groupSlugs === [])
                        <div class="md:hidden text-xs text-gray-400 mt-1">No groups</div>
                        @else
                        <div class="md:hidden flex flex-wrap gap-1 mt-1">
                            @foreach($groupSlugs as $groupSlug)
                            <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $groupSlug }}</span>
                            @endforeach
                        </div>
                        @endif
                    </td>
                    <td class="hidden md:table-cell">{{ $user->email ?: '-' }}</td>
                    <td class="hidden md:table-cell">
                        @if($groupSlugs === [])
                        <span class="text-gray-400">-</span>
                        @else
                        <div class="flex flex-wrap gap-1">
                            @foreach($groupSlugs as $groupSlug)
                            <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $groupSlug }}</span>
                            @endforeach
                        </div>
                        @endif
                    </td>
                    <td>
                        <div class="flex justify-center gap-3 whitespace-nowrap">
                            <a href="{{ route('admin.user.edit', $user) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            @if($user->id !== '1')
                            <form method="POST" action="{{ route('admin.user.destroy', $user) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete this account? This action cannot be undone', $el)">
                                @method('DELETE')
                                @csrf
                                <button type="submit" class="hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>

        {{ $users->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
