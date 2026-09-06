<x-layout>
    <x-mast>My Quotes</x-mast>

    <x-container>
        <x-ui.dynamic-list name="account-quotes">

        <div class="flex my-4 items-center gap-4">
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($quotes->isEmpty())
            <x-none-found item="quotes" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="quote_number" label="Quote #" />
                    <x-ui.list-heading class="text-center!" label="Status" />
                    <x-ui.list-heading field="quote_date" class="text-center!" label="Quote Date" />
                    <x-ui.list-heading class="text-center!" label="Total" field="total_amount" suffix="(incl GST)" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach ($quotes as $quote)
                        <tr>
                            <td><a href="{{ route('account.quote.show', $quote) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $quote->quote_number }}</a></td>
                            <td class="text-center!">
                                <x-ui.badge :color="$quote->statusBadgeTone()">{{ $quote->statusLabel() }}</x-ui.badge>
                            </td>
                            <td class="text-center!"><x-ui.date-time>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                            <td class="text-center!">${{ number_format((float) $quote->total_amount, 2) }}</td>
                            <td><x-ui.row-actions>
                                <x-ui.row-action label="View Quote" icon="fa-regular fa-eye" tone="neutral" href="{{ route('account.quote.show', $quote) }}" />
                                <x-ui.row-action label="Open PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('account.quote.pdf', $quote) }}" target="_blank" />
                            </x-ui.row-actions></td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$quotes" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
