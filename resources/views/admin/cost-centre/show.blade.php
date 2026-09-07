<x-layout>
    <x-mast :title="$category->name" backRoute="admin.cost-centre.index" backTitle="Cost centres">
        <x-slot:actions>
            @if($category->kind === 'cost')<x-ui.button color="mast" data-record-editor href="{{ route('admin.cost-centre.transfer.edit', ['from' => $category->id]) }}">Transfer funds</x-ui.button>@endif
            <x-ui.button color="mast" data-record-editor href="{{ route('admin.cost-centre.edit', ['id' => $category->id]) }}">Edit cost centre</x-ui.button>
        </x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="cost-centre-details" :showPresets="false">
            <x-ui.collection-controls label="Search transactions" />
            <x-ui.table variant="listing">
                <thead><tr><x-ui.list-heading field="date" label="Date" class="text-center" /><x-ui.list-heading field="type" label="Type" class="text-center" /><x-ui.list-heading field="description" label="Description" /><x-ui.list-heading field="amount_display" label="Amount" class="text-center" /><th class="text-center whitespace-nowrap">Running balance</th><th class="text-center whitespace-nowrap">Related record</th></tr></thead>
                <tbody data-list-results>
                    @forelse($records as $record)
                        <tr>
                            <td class="text-center whitespace-nowrap">{{ substr($record['date'], 0, 10) }}</td>
                            <td class="text-center whitespace-nowrap"><x-ui.badge :color="match($record['type']) { 'invoice' => 'green', 'refund', 'expense', 'drawing' => 'orange', 'transfer' => 'blue', default => 'gray' }">{{ ['invoice' => 'Invoice', 'refund' => 'Refund', 'expense' => 'Expense', 'transfer' => 'Transfer', 'drawing' => 'Drawing', 'opening' => 'Opening balance', 'contribution' => 'Contribution'][$record['type']] }}</x-ui.badge></td>
                            <td>{{ $record['description'] }}</td>
                            <td class="text-center whitespace-nowrap {{ $record['amount'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ money($record['amount'] / 100) }}</td>
                            <td class="text-center whitespace-nowrap font-semibold {{ $record['balance'] < 0 ? 'text-red-600' : '' }}">{{ money($record['balance'] / 100) }}</td>
                            <td class="text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    @foreach($record['links'] as $link)
                                        <x-ui.row-action :label="$link['label'].' (opens in a new tab)'" icon="fa-arrow-up-right-from-square" tone="primary" :href="$link['url']" target="_blank" rel="noopener noreferrer" />
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="6">No transactions found.</td></tr>@endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$records" label="records" />
        </x-ui.dynamic-list>
    </x-container>
    <x-ui.record-dialog />
</x-layout>
