<x-layout>
    <x-mast>Expenses</x-mast>

    <x-container>
        @php($hasAdvancedFilters = collect(['supplier', 'description', 'invoice_id', 'attachment', 'paid_from', 'paid_to', 'no_attachment'])->contains(fn ($field) => request()->filled($field)))
        <div
            x-data="{ advancedOpen: {{ \Illuminate\Support\Js::from($hasAdvancedFilters) }} }"
            x-on:toggle-advanced-search.window="advancedOpen = !advancedOpen"
            x-on:clear-advanced-search.window="advancedOpen = false"
        >
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.expense.create') }}">Record</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search
                    name="search"
                    label="Search expenses"
                    class="w-full sm:min-w-[34rem]"
                    :advancedFields="['supplier', 'description', 'invoice_id', 'attachment', 'paid_from', 'paid_to', 'no_attachment']"
                    :advancedExternal="true"
                    :advancedActive="$hasAdvancedFilters"
                />
            </x-slot:right>
        </x-ui.toolbar>
        <form method="GET" action="{{ route('admin.expense.index') }}" x-show="advancedOpen" x-cloak class="mb-4 w-full rounded-lg border border-gray-200 bg-gray-50 p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.input name="invoice_id" label="Invoice ID" value="{{ request('invoice_id', '') }}" />
                <x-ui.input name="supplier" label="Supplier" value="{{ request('supplier', '') }}" />
                <x-ui.input name="description" label="Description" value="{{ request('description', '') }}" />
                <x-ui.input name="attachment" label="Attachment text or filename" value="{{ request('attachment', '') }}" />
                <x-ui.input type="date" name="paid_from" label="Paid from" value="{{ request('paid_from', '') }}" />
                <x-ui.input type="date" name="paid_to" label="Paid to" value="{{ request('paid_to', '') }}" />
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <x-ui.checkbox name="no_attachment" value="1" label="No attachment" :checked="request()->boolean('no_attachment')" :noWrapper="true" :inline="true" />
                <div class="flex gap-2">
                    <x-ui.button href="{{ route('admin.expense.index') }}" color="outline">Clear</x-ui.button>
                    <x-ui.button type="submit">Apply filters</x-ui.button>
                </div>
            </div>
        </form>
        </div>

        @if($expenses->isEmpty())
            @if($hasAdvancedFilters && ! request()->filled('search'))
                <x-none-found item="expenses" message="We couldn't find any expenses matching the advanced filters." />
            @else
                <x-none-found item="expenses" search="{{ request()->get('search') }}" />
            @endif
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($expenses as $expense)
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <x-ui.checkbox value="{{ $expense->id }}" label="Select expense {{ $expense->id }}" :labelHidden="true" :noWrapper="true" inputClass="admin-expense-select-item mt-1" />
                                <div>
                                <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                <div class="mt-1 text-xs text-gray-600">{{ $expense->supplier ?: '-' }}</div>
                                <div class="text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-950">${{ number_format((float) $expense->total_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                            </div>
                        </div>

                        @if(trim((string) $expense->description) !== '')
                            <div class="mt-3 text-sm text-gray-700">{{ $expense->description }}</div>
                        @endif

                        @if(! $expense->receipt_document_exists)
                            <div class="mt-2 text-xs font-medium text-red-600">{{ $expense->receipt_document_path ? 'Attachment missing' : 'No attached invoice' }}</div>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if($expense->receipt_document_exists)
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
                        <th class="w-10 text-center !border-r-0">
                            <x-ui.checkbox id="admin-expense-select-page" aria-label="Select all expenses on this page" :noWrapper="true" inputClass="mx-auto" />
                        </th>
                        <th class="!border-l-0 !pl-1">Expense</th>
                        <th class="hidden md:table-cell">Supplier</th>
                        <th class="hidden md:table-cell">Invoice ID</th>
                        <th class="hidden lg:table-cell">Description</th>
                        <th>Amount <span class="whitespace-nowrap font-normal text-xs">(incl GST)</span></th>
                        <th>Actions</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td class="text-center !border-r-0">
                                    <x-ui.checkbox value="{{ $expense->id }}" label="Select expense {{ $expense->id }}" :labelHidden="true" :noWrapper="true" inputClass="admin-expense-select-item" />
                                </td>
                                <td class="!border-l-0 !pl-1">
                                    <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                    <div class="md:hidden text-xs text-gray-600 mt-1">{{ $expense->supplier ?: '-' }}</div>
                                    <div class="md:hidden text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                    <div class="lg:hidden text-xs text-gray-600">{{ $expense->description ?: '-' }}</div>
                                    @if(! $expense->receipt_document_exists)
                                        <div class="md:hidden mt-0.5 text-xs text-red-600">({{ $expense->receipt_document_path ? 'Attachment missing' : 'No attached invoice' }})</div>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">{{ $expense->supplier ?: '-' }}</td>
                                <td class="hidden md:table-cell">
                                    <div>{{ $expense->invoice_id ?: '-' }}</div>
                                    @if(! $expense->receipt_document_exists)
                                        <div class="mt-0.5 text-xs text-red-600">({{ $expense->receipt_document_path ? 'Attachment missing' : 'No attached invoice' }})</div>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell">{{ $expense->description ?: '-' }}</td>
                                <td>
                                    <div>${{ number_format((float) $expense->total_amount, 2) }}</div>
                                    <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                                </td>

                                <td>
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        @if($expense->receipt_document_exists)
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

        <div id="admin-expense-export-controls" class="mt-4 flex flex-wrap items-center gap-3">
            <form id="admin-expense-export-form" method="POST" action="{{ route('admin.expense.export.zip') }}">
                @csrf
                <div class="admin-expense-export-inputs"></div>
                <x-ui.button type="submit" disabled>Export</x-ui.button>
            </form>
            <x-ui.button type="button" id="admin-expense-clear-selection" color="outline" disabled>Clear</x-ui.button>
            <div class="text-sm"><span id="admin-expense-selected-count">0</span> selected</div>
        </div>
    </x-container>
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const storageKey = 'admin-expense-export-selection';
        const itemCheckboxes = Array.from(document.querySelectorAll('.admin-expense-select-item'));
        const selectPage = document.getElementById('admin-expense-select-page');
        const count = document.getElementById('admin-expense-selected-count');
        const clear = document.getElementById('admin-expense-clear-selection');
        const form = document.getElementById('admin-expense-export-form');
        let selected = [];

        try {
            selected = JSON.parse(sessionStorage.getItem(storageKey) || '[]').map(String);
        } catch (error) {
            selected = [];
        }
        selected = [...new Set(selected)];

        const render = () => {
            sessionStorage.setItem(storageKey, JSON.stringify(selected));
            itemCheckboxes.forEach((checkbox) => checkbox.checked = selected.includes(checkbox.value));
            const pageIds = [...new Set(itemCheckboxes.map((checkbox) => checkbox.value))];
            const selectedOnPage = pageIds.filter((id) => selected.includes(id)).length;
            if (selectPage) {
                selectPage.checked = pageIds.length > 0 && selectedOnPage === pageIds.length;
                selectPage.indeterminate = selectedOnPage > 0 && selectedOnPage < pageIds.length;
            }
            if (count) count.textContent = String(selected.length);
            if (clear) clear.disabled = selected.length === 0;
            if (form) {
                form.querySelector('button[type="submit"]').disabled = selected.length === 0;
                const container = form.querySelector('.admin-expense-export-inputs');
                container.replaceChildren(...selected.map((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'expense_ids[]';
                    input.value = id;
                    return input;
                }));
            }
        };

        itemCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
            selected = checkbox.checked
                ? [...new Set([...selected, checkbox.value])]
                : selected.filter((id) => id !== checkbox.value);
            render();
        }));
        selectPage?.addEventListener('change', () => {
            const pageIds = [...new Set(itemCheckboxes.map((checkbox) => checkbox.value))];
            selected = selectPage.checked
                ? [...new Set([...selected, ...pageIds])]
                : selected.filter((id) => !pageIds.includes(id));
            render();
        });
        clear?.addEventListener('click', () => {
            selected = [];
            render();
        });
        render();
    });
</script>
