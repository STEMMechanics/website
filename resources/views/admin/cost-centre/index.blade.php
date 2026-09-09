<x-layout>
    <x-finance.cost-centre-mast>
        <x-slot:actions>
            <x-ui.button color="mast" data-record-editor href="{{ route('admin.cost-centre.transfer.edit') }}">Transfer funds</x-ui.button>
            <x-ui.button color="mast" data-record-editor href="{{ route('admin.cost-centre.edit') }}">Create cost centre</x-ui.button>
        </x-slot:actions>
    </x-finance.cost-centre-mast>
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="cost-centres" :showPresets="false">
            <x-ui.preset-views :items="collect(['all' => 'All cost centres', 'active' => 'Active', 'archived' => 'Archived'])->map(fn ($title, $key) => ['title' => $title, 'count' => $counts[$key], 'active' => $state === $key, 'route' => request()->fullUrlWithQuery(['state' => $key, 'page' => null])])->values()->all()" />
            <x-ui.collection-controls label="Search cost centres" />
            <p class="mb-4 text-sm text-slate-600">@if($cash['settings']->opening_date)Balances start from {{ $cash['settings']->opening_date }}. These are allocated funds, not separate bank balances. GST is reserved for BAS.@else Balances start from zero and include all recorded payments and expenses.@endif</p>
            <x-ui.table variant="listing" mobileCards>
                <thead><tr><x-ui.list-heading field="name" label="Cost centre" /><th class="text-center">Status</th><x-ui.list-heading field="priority" label="Priority" class="text-center" /><x-ui.list-heading field="balance" label="Balance" class="text-center" /><th class="text-center whitespace-nowrap">Actions</th></tr></thead>
                <tbody data-list-results>@forelse($centres as $centre)
                    @php($historyUrl = match ($centre->kind) {
                        'gst' => route('admin.cost-centre.gst'),
                        'contributions' => route('admin.cost-centre.contributions'),
                        default => route('admin.cost-centre.show', $centre->id),
                    })
                    <tr>
                        <td data-mobile-primary><a class="font-semibold hover:text-primary-color" href="{{ $historyUrl }}">{{ $centre->name }}</a></td>
                        <td data-label="Status" class="text-center"><x-ui.badge :color="$centre->kind !== 'cost' ? 'sky' : ($centre->active ? 'success' : 'slate')">{{ $centre->kind !== 'cost' ? 'System' : ($centre->active ? 'Active' : 'Archived') }}</x-ui.badge></td>
                        <td data-label="Priority" class="text-center whitespace-nowrap">{{ in_array($centre->kind, ['gst', 'contributions']) ? '—' : $centre->priority }}</td>
                        <td data-label="Balance" @class(['text-center whitespace-nowrap tabular-nums', 'text-red-600' => $centre->balance < 0])>{{ money($centre->balance / 100) }}</td>
                        <td data-mobile-actions class="text-center whitespace-nowrap"><x-ui.row-actions :menu="false">
                            <x-ui.row-action label="View transactions" icon="fa-solid fa-receipt" href="{{ $historyUrl }}" />
                            @if(! in_array($centre->kind, ['gst', 'contributions']))<x-ui.row-action label="Edit cost centre" icon="fa-solid fa-pen-to-square" tone="primary" data-record-editor href="{{ route('admin.cost-centre.edit', ['id' => $centre->id]) }}" />@endif
                        </x-ui.row-actions></td>
                    </tr>
                @empty<tr><td colspan="5">No cost centres match this view.</td></tr>@endforelse</tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$centres" label="cost centres" />
        </x-ui.dynamic-list>
    </x-container>
    <x-ui.record-dialog />
</x-layout>
