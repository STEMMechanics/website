<x-layout>
    <x-mast>Users
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.user.create') }}">Create User</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-user">
        <x-ui.collection-controls class="my-5" />


        @if($users->isEmpty())
        <x-none-found item="users" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Name" />
                    <x-ui.list-heading label="Email" />
                    <x-ui.list-heading class="text-center!" label="Status" />
                    <x-ui.list-heading label="User data" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
            <x-slot:body>
                @foreach ($users as $user)
                @php
                    $groupSlugs = $user->groupSlugs();
                    $accountCredit = (float) ($user->account_credit_amount ?? 0);
                @endphp
                <tr>
                    <td>
                        <div>
                            <a href="{{ route('admin.user.edit', $user) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                            {{ trim(($user->firstname ?? '').' '.($user->surname ?? '')) ?: '-' }}
                            </a>
                        </div>
                        @if($user->organisations->isNotEmpty())
                            <div class="mt-1 flex flex-col gap-1">
                                @foreach($user->organisations as $organisation)
                                    <a href="{{ route('admin.organisation.edit', $organisation) }}" class="text-xs text-gray-500 hover:text-primary-color">
                                        <i class="fa-solid fa-building mr-1"></i>{{ $organisation->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="text-sm text-gray-700">{{ $user->email ?: '-' }}</div>
                    </td>
                    <td class="text-center! whitespace-nowrap">
                        @if($user->isAnonymized())
                            <x-ui.badge color="slate">Anonymised</x-ui.badge>
                        @elseif($user->email_verified_at)
                            <x-ui.badge color="success" title="Email address verified">Verified</x-ui.badge>
                        @else
                            <x-ui.badge color="warning" title="Email address not yet verified">Unverified</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-col space-y-1">
                            @php
                                $mediaCount = (int) ($user->media_count ?? 0);
                                $mediaSize = (int) ($user->media_sum_size ?? 0);
                            @endphp
                            <a href="{{ route('admin.media.index', ['user_id' => $user->id]) }}" class="text-xs text-gray-600 hover:text-primary-color" title="View user media">
                                <i class="fa-solid fa-photo-film mr-2"></i>{{ number_format($mediaCount) }} {{ \Illuminate\Support\Str::plural('file', $mediaCount) }}<x-ui.nonbreaking>{{ $mediaCount > 0 ? ' - '.\App\Helpers::bytesToString($mediaSize) : '' }}</x-ui.nonbreaking>
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
                                        <x-ui.badge color="gray" size="xs">{{ $groupSlug }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="text-center!">
                        <x-ui.row-actions :title="$user->getName()" class="whitespace-nowrap">
                            <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.user.edit', $user) }}" />
                            @if($accountCredit > 0.0001)
                                <x-ui.row-action label="View financials" icon="fa-solid fa-coins" tone="neutral" href="{{ route('admin.user.payments', $user) }}" />
                            @endif
                            @if(($user->media_count ?? 0) > 0)
                                <x-ui.row-action label="View user media" icon="fa-solid fa-photo-film" tone="neutral" href="{{ route('admin.media.index', ['user_id' => $user->id]) }}" />
                            @endif
                            @if($user->id !== '1')
                            <form method="POST" action="{{ route('admin.user.destroy', $user) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete this account? This action cannot be undone', $el)">
                                @method('DELETE')
                                @csrf
                                <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" type="submit" />
                            </form>
                            @endif
                        </x-ui.row-actions>
                    </td>
                </tr>
                @endforeach
            </x-slot:body>
        </x-ui.table>

        <x-ui.list-pagination :paginator="$users" />
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
