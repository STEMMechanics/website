<x-layout>
    <x-mast>Users</x-mast>

    <x-container>
        <div class="flex flex-col my-4 items-center justify-between gap-4 md:flex-row">
            <x-ui.button class="w-full md:w-auto" href="{{ route('admin.user.create') }}">Create User</x-ui.button>
            <form method="GET" action="{{ url()->current() }}" class="flex gap-4 flex-col w-full md:flex-row justify-end">
                <x-ui.checkbox
                    name="show_ghost"
                    value="1"
                    label="Show ghost users"
                    :checked="!empty($showGhostUsers)"
                    :noWrapper="true"
                    :inline="true"
                    onchange="this.form.submit()" />
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <div class="flex relative flex-1">
                        <input
                            class="grow rounded-l-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 appearance-none focus:outline-none focus:border-indigo-300 focus:ring-0"
                            autocomplete="off"
                            placeholder="Search"
                            type="text"
                            name="search"
                            value="{{ request()->get('search', '') }}" />
                        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                    </div>
                </div>
            </form>
        </div>

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
                    <th class="hidden md:table-cell">Username</th>
                    <th>Email</th>
                    <th>User data</th>
                    <th>Actions</th>
                </x-slot:header>
            <x-slot:body>
                @foreach ($users as $user)
                @php
                    $groupSlugs = $user->groupSlugs();
                    $isGhostUser = is_null($user->email_verified_at) && ! $user->isChildAccount() && ! $user->isAnonymized();
                    $accountCredit = (float) ($user->account_credit_amount ?? 0);
                @endphp
                <tr class="{{ $isGhostUser ? 'italic text-gray-700' : '' }}">
                    <td>
                        <div class="{{ $user->isChildAccount() ? 'pl-5 border-l-2 border-amber-300' : '' }}">
                            <a href="{{ route('admin.user.edit', $user) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                            {{ trim(($user->firstname ?? '').' '.($user->surname ?? '')) ?: '-' }}
                            </a>
                            @if($user->isChildAccount())
                                <div class="mt-1 text-xs text-amber-700">Child account</div>
                                <div class="text-xs text-gray-500">
                                    Parent:
                                    @if($user->parent)
                                        <a href="{{ route('admin.user.edit', $user->parent) }}" class="hover:text-primary-color">{{ $user->parent->getName() ?: ('#'.$user->parent->id) }}</a>
                                    @else
                                        -
                                    @endif
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="hidden md:table-cell">{{ $user->username ?: '-' }}</td>
                    <td>
                        <div class="text-sm text-gray-700">{{ $user->email ?: '-' }}</div>
                    </td>
                    <td>
                        <div class="flex flex-col space-y-1">
                            @php
                                $mediaCount = (int) ($user->media_count ?? 0);
                                $mediaSize = (int) ($user->media_sum_size ?? 0);
                            @endphp
                            <a href="{{ route('admin.media.index', ['user_id' => $user->id]) }}" class="text-xs text-gray-600 hover:text-primary-color" title="View user media">
                                <i class="fa-solid fa-photo-film mr-2"></i>{{ number_format($mediaCount) }} {{ \Illuminate\Support\Str::plural('file', $mediaCount) }}{{ $mediaCount > 0 ? ' - '.\App\Helpers::bytesToString($mediaSize) : '' }}
                            </a>
                            @if($accountCredit > 0.0001)
                                <a href="{{ route('admin.user.payments', $user) }}" class="text-xs text-gray-600 hover:text-primary-color" title="View user media">
                                    <i class="fa-solid fa-money-bill mr-2"></i>Credit - {{ money($accountCredit) }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400"><i class="fa-solid fa-money-bill mr-2"></i>No credit</span>
                            @endif

                            @if($groupSlugs !== [])
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($groupSlugs as $groupSlug)
                                        <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-xxs font-semibold text-gray-700">{{ $groupSlug }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex justify-center gap-3 whitespace-nowrap">
                            <a href="{{ route('admin.user.edit', $user) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            @if($accountCredit > 0.0001)
                                <a href="{{ route('admin.user.payments', $user) }}" class="hover:text-primary-color" title="View financials"><i class="fa-solid fa-coins"></i></a>
                            @endif
                            @if(($user->media_count ?? 0) > 0)
                                <a href="{{ route('admin.media.index', ['user_id' => $user->id]) }}" class="hover:text-primary-color" title="View user media"><i class="fa-solid fa-photo-film"></i></a>
                            @endif
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
