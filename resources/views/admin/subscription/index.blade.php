<x-layout>
    <x-mast>Email Subscriptions</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.subscription.create') }}">Create Subscription</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Email</th>
                    <th class="hidden md:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Confirmed</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $subscription->email }}</div>
                                <div class="md:hidden text-xs text-gray-500">
                                    {{ $subscription->confirmed ? 'Confirmed' : 'Unconfirmed' }}
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                {{ $subscription->confirmed ? 'Confirmed' : 'Unconfirmed' }}
                            </td>
                            <td class="hidden lg:table-cell">
                                {{ $subscription->confirmed ? \Carbon\Carbon::parse($subscription->confirmed)->format('M j Y, g:i a') : '-' }}
                            </td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.subscription.edit', $subscription) }}" class="hover:text-primary-color" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $subscriptions->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
