<x-layout>
    <x-mast>Quotes</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.quote.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($quotes->isEmpty())
            <x-none-found item="quotes" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Quote #</th>
                    <th class="hidden md:table-cell">User</th>
                    <th class="hidden md:table-cell">Quote Date</th>
                    <th>Amount <span class="font-normal text-xs">(incl GST)</span></th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($quotes as $quote)
                        <tr>
                            <td>
                                <a href="{{ route('admin.quote.edit', $quote) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $quote->quote_number }}</a>
                                @if(trim((string) ($quote->title ?? '')) !== '')
                                    <div class="text-xs text-gray-600 mt-1">{{ $quote->title }}</div>
                                @endif
                                <div class="md:hidden text-xs text-gray-600 mt-1">{{ $quote->user?->getName() ?? '-' }}</div>
                                <div class="md:hidden text-xs text-gray-600">{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</div>
                            </td>
                            <td class="hidden md:table-cell text-center">{{ $quote->user?->getName() ?? '-' }}</td>
                            <td class="hidden md:table-cell text-center">{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</td>
                            <td class="text-right">${{ number_format((float) $quote->total_amount, 2) }}</td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    <a href="{{ route('admin.quote.edit', $quote) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="{{ route('admin.quote.pdf', $quote) }}" class="hover:text-primary-color" target="_blank" title="Open PDF"><i class="fa-regular fa-file-pdf"></i></a>
                                    <form method="POST" action="{{ route('admin.quote.email', $quote) }}">
                                        @csrf
                                        <button type="submit" class="hover:text-primary-color" title="Email Quote PDF"><i class="fa-regular fa-envelope"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                                        @csrf
                                        <button type="submit" class="hover:text-primary-color" title="Convert to Invoice"><i class="fa-solid fa-file-invoice-dollar"></i></button>
                                    </form>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $quotes->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
