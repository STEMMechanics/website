<x-layout>
    <x-mast>My Quotes</x-mast>

    <x-container>
        <div class="flex my-4 items-center gap-4">
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($quotes->isEmpty())
            <x-none-found item="quotes" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Quote #</th>
                    <th>Quote Date</th>
                    <th>Total</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($quotes as $quote)
                        <tr>
                            <td>{{ $quote->quote_number }}</td>
                            <td>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</td>
                            <td>${{ number_format((float) $quote->total_amount, 2) }}</td>
                            <td class="flex justify-center gap-3">
                                <a href="{{ route('account.quote.pdf', $quote) }}" class="hover:text-primary-color" title="Open PDF" target="_blank"><i class="fa-regular fa-file-pdf"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $quotes->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
