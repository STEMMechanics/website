<x-layout>
    <x-mast>Expenses</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.expense.create') }}">Record</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <form method="GET" action="{{ url()->current() }}" class="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <x-ui.checkbox
                        name="no_attachment"
                        value="1"
                        label="No attachment"
                        :checked="request()->boolean('no_attachment')"
                        :noWrapper="true"
                        :inline="true"
                        onchange="this.form.submit()"
                    />
                    <div class="flex">
                        <input
                            class="bg-white grow px-2.5 py-2.5 text-sm text-gray-900 rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300"
                            autocomplete="off"
                            type="text"
                            name="search"
                            placeholder="Search"
                            value="{{ request('search', '') }}"
                        />
                        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                    </div>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($expenses->isEmpty())
            <x-none-found item="expenses" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($expenses as $expense)
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                <div class="mt-1 text-xs text-gray-600">{{ $expense->supplier ?: '-' }}</div>
                                <div class="text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-950">${{ number_format((float) $expense->total_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                            </div>
                        </div>

                        @if(trim((string) $expense->description) !== '')
                            <div class="mt-3 text-sm text-gray-700">{{ $expense->description }}</div>
                        @endif

                        @if(! $expense->receipt_document_path)
                            <div class="mt-2 text-xs font-medium text-red-600">No attached invoice</div>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if($expense->receipt_document_path)
                                <a href="{{ route('admin.expense.document.view', $expense) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" target="_blank" title="View attachment">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span class="sr-only">View attachment</span>
                                </a>
                            @else
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-300" title="No attachment">
                                    <i class="fa-solid fa-paperclip"></i>
                                </span>
                            @endif
                            <a href="{{ route('admin.expense.edit', $expense) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Edit expense">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span class="sr-only">Edit expense</span>
                            </a>
                            <form method="POST" action="{{ route('admin.expense.destroy', $expense) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', $el)">
                                @method('DELETE')
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600" title="Delete expense">
                                    <i class="fa-solid fa-trash"></i>
                                    <span class="sr-only">Delete expense</span>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table>
                    <x-slot:header>
                        <th>Expense</th>
                        <th class="hidden md:table-cell">Supplier</th>
                        <th class="hidden md:table-cell">Invoice ID</th>
                        <th class="hidden lg:table-cell">Description</th>
                        <th>Amount <span class="font-normal text-xs">(incl GST)</span></th>
                        <th>Actions</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                    <div class="md:hidden text-xs text-gray-600 mt-1">{{ $expense->supplier ?: '-' }}</div>
                                    <div class="md:hidden text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                    <div class="lg:hidden text-xs text-gray-600">{{ $expense->description ?: '-' }}</div>
                                    @if(! $expense->receipt_document_path)
                                        <div class="md:hidden mt-0.5 text-xs text-red-600">(No attached invoice)</div>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">{{ $expense->supplier ?: '-' }}</td>
                                <td class="hidden md:table-cell">
                                    <div>{{ $expense->invoice_id ?: '-' }}</div>
                                    @if(! $expense->receipt_document_path)
                                        <div class="mt-0.5 text-xs text-red-600">(No attached invoice)</div>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell">{{ $expense->description ?: '-' }}</td>
                                <td>
                                    <div>${{ number_format((float) $expense->total_amount, 2) }}</div>
                                    <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                                </td>

                                <td>
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        @if($expense->receipt_document_path)
                                            <a href="{{ route('admin.expense.document.view', $expense) }}" class="hover:text-primary-color" target="_blank" title="View Attachment"><i class="fa-solid fa-paperclip"></i></a>
                                        @else
                                            <span class="text-gray-300" title="No Attachment"><i class="fa-solid fa-paperclip"></i></span>
                                        @endif
                                        <a href="{{ route('admin.expense.edit', $expense) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" action="{{ route('admin.expense.destroy', $expense) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', $el)">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            {{ $expenses->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
