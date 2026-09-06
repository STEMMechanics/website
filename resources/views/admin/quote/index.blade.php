<x-layout>
    <x-mast>Quotes
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.quote.create') }}">Create</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-quote-index">

        <x-ui.collection-controls class="my-5" />

@if($quotes->isEmpty())
            <x-none-found item="quotes" search="{{ request()->get('search') }}" />
        @else
            @php
                $invoiceStatusTextClass = static fn (string $tone): string => match ($tone) {
                    'danger' => 'text-rose-700',
                    'success' => 'text-emerald-700',
                    'warning' => 'text-amber-700',
                    'sky' => 'text-sky-700',
                    'slate' => 'text-slate-700',
                    default => 'text-gray-700',
                };
            @endphp
            <div data-list-results class="space-y-4 md:hidden">
                @foreach ($quotes as $quote)
                    @php
                        $quoteInvoices = $quote->invoices ?? collect();
                        $quoteInvoiceCount = $quoteInvoices->count();
                        $firstLinkedInvoice = $quoteInvoices->first();
                    @endphp
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('admin.quote.edit', $quote) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $quote->quote_number }}</a>
                                @if(trim((string) ($quote->title ?? '')) !== '')
                                    <div class="mt-1 text-xs text-gray-600">{{ $quote->title }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-600">{{ $quote->user?->getName() ?? '-' }}</div>
                            </div>
                            <x-ui.badge :color="$quote->statusBadgeTone()" size="xs">{{ $quote->statusLabel() }}</x-ui.badge>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-gray-600">
                            <span>Quote date</span>
                            <span>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</span>
                        </div>
                        <div class="mt-2 flex items-start justify-between gap-3 text-xs text-gray-600">
                            <span>Linked invoices</span>
                            <div class="text-left">
                                @if($quoteInvoiceCount === 0)
                                    <span class="text-gray-400" title="No linked invoices">--</span>
                                @elseif($quoteInvoiceCount === 1)
                                    <div class="flex items-start justify-between gap-3">
                                        <a href="{{ route('admin.invoice.edit', $firstLinkedInvoice) }}" class="min-w-0 font-semibold text-gray-900 hover:text-primary-color" title="Open linked invoice {{ $firstLinkedInvoice->invoice_number }}">
                                            {{ $firstLinkedInvoice->invoice_number }}
                                        </a>
                                        <span class="shrink-0 text-[11px] font-semibold {{ $invoiceStatusTextClass($firstLinkedInvoice->displayStatusTone()) }}">
                                            {{ $firstLinkedInvoice->displayStatusLabel() }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 text-left text-[11px] text-gray-500">{{ $firstLinkedInvoice->issue_date?->format('M j, Y') ?? 'No issue date' }}</div>
                                @elseif($quoteInvoiceCount > 1)
                                    <span class="text-gray-500">{{ $quoteInvoiceCount }} invoices</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-gray-950">${{ number_format((float) $quote->total_amount, 2) }}</div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <x-ui.row-action label="Edit quote" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.quote.edit', $quote) }}" />
                            <form method="POST" action="{{ route('admin.quote.duplicate', $quote) }}">
                                @csrf
                                <x-ui.row-action label="Duplicate Quote" icon="fa-solid fa-copy" tone="neutral" type="submit" />
                            </form>
                            <x-ui.row-action label="Open PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.quote.pdf', $quote) }}" target="_blank" />
                            <form method="POST" action="{{ route('admin.quote.email', $quote) }}">
                                @csrf
                                <x-ui.row-action label="Email Quote PDF" icon="fa-regular fa-envelope" tone="neutral" type="submit" />
                            </form>
                            @if($quoteInvoiceCount === 1)
                                <x-ui.row-action label="Open linked invoice {{ $firstLinkedInvoice->invoice_number }}" icon="fa-solid fa-file-invoice" tone="neutral" href="{{ route('admin.invoice.edit', $firstLinkedInvoice) }}" />
                            @elseif($quoteInvoiceCount > 1)
                                <div class="relative" x-data="{ open: false }">
                                    <x-ui.button variant="plain"
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                        title="View linked invoices"
                                        x-on:click.prevent="open = true"
                                    >
                                        <i class="fa-solid fa-file-invoice"></i>
                                        <span class="sr-only">View linked invoices</span>
                                    </x-ui.button>
                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-on:click.self="open = false"
                                        x-on:keydown.escape.window="open = false"
                                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                                    >
                                        <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-lg">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">Linked invoices</h3>
                                                    <p class="mt-1 text-sm text-gray-500">{{ $quote->quote_number }}</p>
                                                </div>
                                                <x-ui.button variant="plain" type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click.prevent="open = false" title="Close">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </x-ui.button>
                                            </div>
                                            <div class="mt-4 text-xs text-gray-700">
                                                @foreach($quoteInvoices as $linkedInvoice)
                                                    @if(! $loop->first)
                                                        <div class="my-2 border-t border-gray-200"></div>
                                                    @endif
                                                    <a href="{{ route('admin.invoice.edit', $linkedInvoice) }}" class="block text-left transition hover:text-primary-color">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <span class="min-w-0 font-semibold text-gray-900">{{ $linkedInvoice->invoice_number }}</span>
                                                            <span class="shrink-0 text-[11px] font-semibold {{ $invoiceStatusTextClass($linkedInvoice->displayStatusTone()) }}">{{ $linkedInvoice->displayStatusLabel() }}</span>
                                                        </div>
                                                        <div class="mt-0.5 text-left text-[11px] text-gray-500">{{ $linkedInvoice->issue_date?->format('M j, Y') ?? 'No issue date' }}</div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                                @csrf
                                <x-ui.button variant="plain" type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Create Invoice From Quote">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                    <span class="sr-only">Create Invoice From Quote</span>
                                </x-ui.button>
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
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading field="quote_number" label="Quote #" />
                        <x-ui.list-heading class="hidden md:table-cell" label="User" />
                        <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                        <x-ui.list-heading field="quote_date" class="hidden md:table-cell text-center!" label="Quote Date" />
                        <x-ui.list-heading class="hidden lg:table-cell text-center" label="Linked Invoices" />
                        <x-ui.list-heading class="text-center!" label="Amount" field="total_amount" suffix="(incl GST)" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($quotes as $quote)
                            @php
                                $quoteInvoices = $quote->invoices ?? collect();
                                $quoteInvoiceCount = $quoteInvoices->count();
                                $firstLinkedInvoice = $quoteInvoices->first();
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.quote.edit', $quote) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $quote->quote_number }}</a>
                                    @if(trim((string) ($quote->title ?? '')) !== '')
                                        <div class="text-xs text-gray-600 mt-1">{{ $quote->title }}</div>
                                    @endif
                                    <div class="md:hidden text-xs text-gray-600 mt-1">{{ $quote->user?->getName() ?? '-' }}</div>
                                    <div class="md:hidden mt-1">
                                        <x-ui.badge :color="$quote->statusBadgeTone()" size="xs">{{ $quote->statusLabel() }}</x-ui.badge>
                                    </div>
                                    <div class="md:hidden text-xs text-gray-600"><x-ui.date-time>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></div>
                                </td>
                                <td class="hidden md:table-cell text-center">{{ $quote->user?->getName() ?? '-' }}</td>
                                <td class="hidden md:table-cell text-center!">
                                    <x-ui.badge :color="$quote->statusBadgeTone()">{{ $quote->statusLabel() }}</x-ui.badge>
                                </td>
                                <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ $quote->quote_date?->format('M j, Y') ?? '-' }}</x-ui.date-time></td>
                                <td class="hidden lg:table-cell text-center">
                                    @if($quoteInvoiceCount === 0)
                                        <span class="text-gray-400" title="No linked invoices">--</span>
                                    @else
                                        <div class="text-xs text-gray-700">
                                            @foreach($quoteInvoices as $linkedInvoice)
                                                @if(! $loop->first)
                                                    <div class="my-2 border-t border-gray-200"></div>
                                                @endif
                                                <a href="{{ route('admin.invoice.edit', $linkedInvoice) }}" class="block text-left transition hover:text-primary-color">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="min-w-0 font-semibold text-gray-900">{{ $linkedInvoice->invoice_number }}</span>
                                                        <span class="shrink-0 text-[11px] font-semibold {{ $invoiceStatusTextClass($linkedInvoice->displayStatusTone()) }}">{{ $linkedInvoice->displayStatusLabel() }}</span>
                                                    </div>
                                                    <div class="mt-0.5 text-left text-[11px] text-gray-500"><x-ui.date-time>{{ $linkedInvoice->issue_date?->format('M j, Y') ?? 'No issue date' }}</x-ui.date-time></div>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center!">${{ number_format((float) $quote->total_amount, 2) }}</td>
                                <td class="text-center!">
                                    <x-ui.row-actions class="whitespace-nowrap">
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.quote.edit', $quote) }}" />
                                        <form method="POST" action="{{ route('admin.quote.duplicate', $quote) }}">
                                            @csrf
                                            <x-ui.row-action label="Duplicate Quote" icon="fa-solid fa-copy" tone="neutral" type="submit" />
                                        </form>
                                        <x-ui.row-action label="Open PDF" icon="fa-regular fa-file-pdf" tone="neutral" href="{{ route('admin.quote.pdf', $quote) }}" target="_blank" />
                                        <form method="POST" action="{{ route('admin.quote.email', $quote) }}">
                                            @csrf
                                            <x-ui.row-action label="Email Quote PDF" icon="fa-regular fa-envelope" tone="neutral" type="submit" />
                                        </form>
                                        @if($quoteInvoiceCount === 1)
                                            <x-ui.row-action label="Open linked invoice {{ $firstLinkedInvoice->invoice_number }}" icon="fa-solid fa-file-invoice" tone="neutral" href="{{ route('admin.invoice.edit', $firstLinkedInvoice) }}" />
                                        @elseif($quoteInvoiceCount > 1)
                                            <div class="relative" x-data="{ open: false }">
                                                <x-ui.row-action label="View linked invoices" icon="fa-solid fa-file-invoice" tone="neutral" type="button" x-on:click.prevent="open = true" />
                                                <div
                                                    x-cloak
                                                    x-show="open"
                                                    x-on:click.self="open = false"
                                                    x-on:keydown.escape.window="open = false"
                                                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                                                >
                                                    <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-lg">
                                                        <div class="flex items-start justify-between gap-4">
                                                            <div>
                                                                <h3 class="text-lg font-semibold text-gray-900">Linked invoices</h3>
                                                                <p class="mt-1 text-sm text-gray-500">{{ $quote->quote_number }}</p>
                                                            </div>
                                                            <x-ui.row-action label="Close" icon="fa-solid fa-xmark" tone="neutral" type="button" x-on:click.prevent="open = false" />
                                                        </div>
                                                        <div class="mt-4 text-xs text-gray-700">
                                                            @foreach($quoteInvoices as $linkedInvoice)
                                                                @if(! $loop->first)
                                                                    <div class="my-2 border-t border-gray-200"></div>
                                                                @endif
                                                                <a href="{{ route('admin.invoice.edit', $linkedInvoice) }}" class="block text-left transition hover:text-primary-color">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <span class="min-w-0 font-semibold text-gray-900">{{ $linkedInvoice->invoice_number }}</span>
                                                                        <span class="shrink-0 text-[11px] font-semibold {{ $invoiceStatusTextClass($linkedInvoice->displayStatusTone()) }}">{{ $linkedInvoice->displayStatusLabel() }}</span>
                                                                    </div>
                                                                    <div class="mt-0.5 text-left text-[11px] text-gray-500"><x-ui.date-time>{{ $linkedInvoice->issue_date?->format('M j, Y') ?? 'No issue date' }}</x-ui.date-time></div>
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        <form method="POST" action="{{ route('admin.quote.create-invoice', $quote) }}">
                                            @csrf
                                            <x-ui.row-action label="Create Invoice From Quote" icon="fa-solid fa-file-invoice-dollar" tone="neutral" type="submit" />
                                        </form>
                                        <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete quote?', 'Are you sure you want to delete this quote?', '{{ route('admin.quote.destroy', $quote) }}')" />
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            <x-ui.list-pagination :paginator="$quotes" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
