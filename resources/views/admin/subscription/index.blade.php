<x-layout>
    <x-mast>Email Subscriptions</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.subscription.create') }}">Register</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Email</th>
                    <th>Registered On</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $subscription->email }}</div>
                            </td>
                            <td>
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
