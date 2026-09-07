<x-layout>
    <x-mast title="Suppliers" description="Manage suppliers and their default cost centres.">
        <x-slot:actions>
            <x-ui.button color="mast" data-record-editor href="{{ route('admin.supplier.create') }}">Create supplier</x-ui.button>
        </x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-suppliers">
            <x-ui.collection-controls class="my-5" />
            <x-ui.table variant="listing">
                <thead>
                    <tr>
                        <x-ui.list-heading label="Supplier" field="name" />
                        <x-ui.list-heading label="Cost centre" field="cost_centre" class="text-center" />
                        <x-ui.list-heading label="Expenses" field="expenses_count" class="text-center" />
                        <x-ui.list-heading label="Total" field="expenses_total_amount" suffix="incl GST" />
                        <th class="text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody data-list-results>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td><a class="font-semibold hover:text-primary-color" href="{{ route('admin.supplier.show', $supplier) }}">{{ $supplier->name }}</a></td>
                            <td class="text-center">
                                <x-ui.badge :color="$supplier->category_id ? 'slate' : 'amber'">{{ $categories[$supplier->category_id] ?? 'Choose cost centre' }}</x-ui.badge>
                            </td>
                            <td class="text-center whitespace-nowrap">{{ number_format($supplier->expenses_count) }}</td>
                            <td class="text-center whitespace-nowrap">{{ money((float) $supplier->expenses_sum_total_amount) }}</td>
                            <td class="text-center whitespace-nowrap">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit supplier" icon="fa-solid fa-pen-to-square" tone="primary" data-record-editor href="{{ route('admin.supplier.edit', $supplier) }}" />
                                <x-ui.row-action label="View expenses" icon="fa-solid fa-receipt" href="{{ route('admin.expense.index', ['supplier_id' => $supplier->id]) }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-none-found item="suppliers" :search="request('search')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$suppliers" label="suppliers" />
        </x-ui.dynamic-list>
    </x-container>
<x-ui.record-dialog />
</x-layout>
