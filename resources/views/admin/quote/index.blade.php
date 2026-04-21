<x-layout>
    <x-mast>Quotes</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.quote.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($quotes->isEmpty())
            <x-none-found item="quotes" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($quotes as $quote)
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('admin.quote.edit', $quote) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $quote->quote_number }}</a>
                                @if(trim((string) ($quote->title ?? '')) !== '')
                                    <div class="mt-1 text-xs text-gray-600">{{ $quote->title }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-600">{{ $quote->user?->getName() ?? '-' }}</div>
                            </div>
                            <x-ui.badge :color="$quote->statusBadgeTone()" size="xxs">{{ $quote->statusLabel() }}</x-ui.badge>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-gray-600">
                            <span>Quote date</span>
                            <span>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</span>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-gray-950">${{ number_format((float) $quote->total_amount, 2) }}</div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.quote.edit', $quote) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Edit quote">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span class="sr-only">Edit quote</span>
                            </a>
                            <form method="POST" action="{{ route('admin.quote.duplicate', $quote) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Duplicate Quote">
                                    <i class="fa-solid fa-copy"></i>
                                    <span class="sr-only">Duplicate quote</span>
                                </button>
                            </form>
                            <a href="{{ route('admin.quote.pdf', $quote) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" target="_blank" title="Open PDF">
                                <i class="fa-regular fa-file-pdf"></i>
                                <span class="sr-only">Open PDF</span>
                            </a>
                            <form method="POST" action="{{ route('admin.quote.email', $quote) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Email Quote PDF">
                                    <i class="fa-regular fa-envelope"></i>
                                    <span class="sr-only">Email Quote PDF</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Create Invoice From Quote">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                    <span class="sr-only">Create Invoice From Quote</span>
                                </button>
                            </form>
                            <a href="#" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')" title="Delete quote">
                                <i class="fa-solid fa-trash"></i>
                                <span class="sr-only">Delete quote</span>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table>
                    <x-slot:header>
                        <th>Quote #</th>
                        <th class="hidden md:table-cell">User</th>
                        <th class="hidden md:table-cell">Status</th>
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
                                    <div class="md:hidden mt-1">
                                        <x-ui.badge :color="$quote->statusBadgeTone()" size="xxs">{{ $quote->statusLabel() }}</x-ui.badge>
                                    </div>
                                    <div class="md:hidden text-xs text-gray-600">{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</div>
                                </td>
                                <td class="hidden md:table-cell text-center">{{ $quote->user?->getName() ?? '-' }}</td>
                                <td class="hidden md:table-cell text-center">
                                    <x-ui.badge :color="$quote->statusBadgeTone()">{{ $quote->statusLabel() }}</x-ui.badge>
                                </td>
                                <td class="hidden md:table-cell text-center">{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</td>
                                <td class="text-right">${{ number_format((float) $quote->total_amount, 2) }}</td>
                                <td>
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        <a href="{{ route('admin.quote.edit', $quote) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" action="{{ route('admin.quote.duplicate', $quote) }}">
                                            @csrf
                                            <button type="submit" class="hover:text-primary-color" title="Duplicate Quote">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.quote.pdf', $quote) }}" class="hover:text-primary-color" target="_blank" title="Open PDF"><i class="fa-regular fa-file-pdf"></i></a>
                                        <form method="POST" action="{{ route('admin.quote.email', $quote) }}">
                                            @csrf
                                            <button type="submit" class="hover:text-primary-color" title="Email Quote PDF"><i class="fa-regular fa-envelope"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                                            @csrf
                                            <button type="submit" class="hover:text-primary-color" title="Create Invoice From Quote"><i class="fa-solid fa-file-invoice-dollar"></i></button>
                                        </form>
                                        <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            {{ $quotes->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
