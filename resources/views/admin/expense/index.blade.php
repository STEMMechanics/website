<x-layout>
    @if(isset($selectedSupplier))
        <x-mast backRoute="admin.supplier.index" backTitle="Suppliers" :title="$selectedSupplier->name" description="Expenses received from this supplier.">
            <x-slot:actions>
                <x-ui.button color="mast" data-record-editor href="{{ route('admin.supplier.edit', $selectedSupplier) }}">Edit supplier</x-ui.button>
                <x-ui.button color="mast" href="{{ route('admin.expense.create', ['supplier' => $selectedSupplier->name]) }}">Record expense</x-ui.button>
            </x-slot:actions>
        </x-mast>
    @else
        <x-mast>Expenses
            <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.expense.create') }}">Record</x-ui.button></x-slot:actions>
        </x-mast>
    @endif

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-expense-index">
        <x-finance.attention-notice kind="expenses" />
        @if(isset($selectedSupplier))
            <p class="mt-4 text-sm">Default cost centre: <x-ui.badge :color="$supplierCostCentre ? 'slate' : 'amber'">{{ $supplierCostCentre ? $supplierCostCentre : 'Choose cost centre' }}</x-ui.badge></p>
        @endif

        @php($hasAdvancedFilters = collect(['supplier', 'description', 'invoice_id', 'attachment', 'paid_from', 'paid_to', 'no_attachment'])->contains(fn ($field) => request()->filled($field)))
        <div
            x-data="{ advancedOpen: {{ \Illuminate\Support\Js::from($hasAdvancedFilters) }} }"
            x-on:toggle-advanced-search.window="advancedOpen = !advancedOpen"
            x-on:clear-advanced-search.window="advancedOpen = false"
        >
        <x-ui.collection-controls class="my-5" />

        </div>

        @if($expenses->isEmpty())
            @if($hasAdvancedFilters && ! request()->filled('search'))
                <x-none-found item="expenses" message="We couldn't find any expenses matching the advanced filters." />
            @else
                <x-none-found item="expenses" search="{{ request()->get('search') }}" />
            @endif
        @else
            <div data-list-results class="space-y-4 md:hidden">
                <x-ui.checkbox label="Select visible expenses / clear selection" inputClass="admin-expense-select-page" />
                @foreach ($expenses as $expense)
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <x-ui.checkbox value="{{ $expense->id }}" label="Select expense {{ $expense->id }}" :labelHidden="true" :noWrapper="true" inputClass="admin-expense-select-item mt-1" />
                                <div class="min-w-0 break-words">
                                <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</a>
                                <div class="mt-1 text-xs text-gray-600">{{ $expense->supplier ?: '-' }}</div>
                                <div class="text-xs text-gray-600">{{ $expense->invoice_id ?: 'No invoice ID' }}</div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="font-semibold text-gray-950">${{ number_format((float) $expense->total_amount, 2) }}</div>
                                <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                            </div>
                        </div>

                        @if(trim((string) $expense->description) !== '')
                            <div class="mt-3 break-words text-sm text-gray-700">{{ $expense->description }}</div>
                        @endif

                        @if(! $expense->receipt_document_exists)
                            <div class="mt-2 text-xs font-medium text-red-600">{{ $expense->receipt_document_path ? 'Attachment missing' : 'No attached invoice' }}</div>
                        @endif

                        <x-ui.row-actions class="mt-4">
                            @if($expense->receipt_document_exists)
                                <x-ui.row-action label="View attachment" icon="fa-solid fa-paperclip" tone="neutral" href="{{ route('admin.expense.document.view', $expense) }}" target="_blank" />
                            @else
                                <x-ui.row-action label="No attachment" icon="fa-paperclip" disabled />
                            @endif
                            <x-ui.row-action label="Edit expense" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.expense.edit', $expense) }}" />
                            <form method="POST" action="{{ route('admin.expense.destroy', $expense) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', $el)">
                                @method('DELETE')
                                @csrf
                                <x-ui.row-action label="Delete expense" icon="fa-solid fa-trash" tone="danger" type="submit" />
                            </form>
                        </x-ui.row-actions>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <th class="w-10 text-center border-r-0!">
                            <x-ui.checkbox id="admin-expense-select-page" aria-label="Select visible expenses or clear all selected expenses" :noWrapper="true" inputClass="admin-expense-select-page mx-auto" />
                        </th>
                        <x-ui.list-heading field="description" class="border-l-0! pl-1!" label="Expense" />
                        <x-ui.list-heading class="hidden md:table-cell" label="Supplier" />
                        <x-ui.list-heading class="hidden md:table-cell" label="Invoice ID" />
                        <x-ui.list-heading class="hidden lg:table-cell" label="Description" />
                        <th class="text-center!">Amount <span class="whitespace-nowrap font-normal text-xs">(incl GST)</span></th>
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td class="text-center border-r-0!">
                                    <x-ui.checkbox value="{{ $expense->id }}" label="Select expense {{ $expense->id }}" :labelHidden="true" :noWrapper="true" inputClass="admin-expense-select-item" />
                                </td>
                                <td class="border-l-0! pl-1!">
                                    <a href="{{ route('admin.expense.edit', $expense) }}" class="font-semibold text-gray-900 hover:text-primary-color"><x-ui.date-time>{{ $expense->paid_on?->format('M j, Y') ?? '-' }}</x-ui.date-time></a>
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
                                <td class="text-center!">
                                    <div>${{ number_format((float) $expense->total_amount, 2) }}</div>
                                    <div class="text-xs text-gray-600">GST: ${{ number_format((float) $expense->gst_amount, 2) }}</div>
                                </td>

                                <td class="text-center!">
                                    <x-ui.row-actions class="whitespace-nowrap">
                                        @if($expense->receipt_document_exists)
                                            <x-ui.row-action label="View Attachment" icon="fa-solid fa-paperclip" tone="neutral" href="{{ route('admin.expense.document.view', $expense) }}" target="_blank" />
                                        @else
                                            <span class="text-gray-300" title="No Attachment"><i class="fa-solid fa-paperclip"></i></span>
                                        @endif
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.expense.edit', $expense) }}" />
                                        <form method="POST" action="{{ route('admin.expense.destroy', $expense) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', $el)">
                                            @method('DELETE')
                                            @csrf
                                            <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" type="submit" />
                                        </form>
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            <x-ui.list-pagination :paginator="$expenses"><x-slot:actions>
        <x-ui.selection-toolbar id="admin-expense-export-controls" hint="Select expenses to edit or export their attachments.">
            <form id="admin-expense-bulk-form" method="POST" action="{{ route('admin.allocation-overrides.edit', ['kind' => 'expenses']) }}" data-bulk-open="finance-bulk-editor">
                @csrf
                <div data-bulk-inputs></div>
                <x-ui.bulk-edit-button type="submit" id="admin-expense-allocate" :count="0" disabled />
            </form>
            <form id="admin-expense-export-form" method="POST" action="{{ route('admin.expense.export.zip') }}">
                @csrf
                <div class="admin-expense-export-inputs"></div>
                <x-ui.button type="submit" disabled>Export 0 items</x-ui.button>
            </form>
        </x-ui.selection-toolbar>
</x-slot:actions></x-ui.list-pagination>
        @endif


        </x-ui.dynamic-list>
    </x-container>
<x-ui.record-dialog />
<x-ui.bulk-editor id="finance-bulk-editor" title="Bulk edit expenses" loader-id="finance-bulk-loader" list="admin-expense-index" selection-key="admin-expense-export-selection" selection-field="ids[]" />
</x-layout>

<script>
    SM.onDynamicList('admin-expense-index', () => {
        const storageKey = 'admin-expense-export-selection';
        const itemCheckboxes = Array.from(document.querySelectorAll('.admin-expense-select-item'));
        const pageCheckboxes = Array.from(document.querySelectorAll('.admin-expense-select-page'));
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
            pageCheckboxes.forEach((checkbox) => {
                checkbox.checked = pageIds.length > 0 && selectedOnPage === pageIds.length;
                checkbox.indeterminate = selected.length > 0 && !checkbox.checked;
            });
            const controls = document.getElementById('admin-expense-export-controls');
            if (controls) controls.dataset.selected = String(selected.length > 0);
            const bulkForm = document.getElementById('admin-expense-bulk-form');
            if (bulkForm) {
                const allocate = bulkForm.querySelector('button[type="submit"]');
                allocate.disabled = !selected.length || selected.length > 200;
                allocate.textContent = selected.length > 200 ? 'Select up to 200 to edit' : 'Edit ' + selected.length + ' items';
                bulkForm.querySelector('[data-bulk-inputs]').replaceChildren(...selected.map(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
                    return input;
                }));
            }
            if (form) {
                const button = form.querySelector('button[type="submit"]');
                button.disabled = selected.length === 0;
                button.textContent = 'Export ' + selected.length + (selected.length === 1 ? ' item' : ' items');
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
        pageCheckboxes.forEach((selectPage) => selectPage.addEventListener('change', () => {
            const pageIds = [...new Set(itemCheckboxes.map((checkbox) => checkbox.value))];
            selected = selectPage.checked
                ? [...new Set([...selected, ...pageIds])]
                : [];
            render();
        }));
        render();
    });
</script>
