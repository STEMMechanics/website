<x-layout>
    <x-mast title="GST" backRoute="admin.cost-centre.index" backTitle="Cost centres" description="Protected tax reserve for monthly BAS." />
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="cost-centre-gst" :showPresets="false">
            <x-finance.panel title="GST balance"><p class="text-2xl font-semibold">{{ money($cash['gst'] / 100) }}</p></x-finance.panel>
            <x-ui.preset-views class="my-5" :items="collect(['summary' => 'Summary & settlements', 'income' => 'Collected GST', 'expenses' => 'Purchase credits'])->map(fn ($title, $key) => ['title' => $title, 'active' => $tab === $key, 'route' => request()->fullUrlWithQuery(['tab' => $key, 'page' => null])])->values()->all()" label="GST records" />
            @if($tab === 'summary')
                <div class="space-y-5">@include('admin.cost-centre.gst-content')</div>
            @else
                <form method="GET" class="mb-5 flex items-end gap-3"><input type="hidden" name="tab" value="{{ $tab }}"><x-ui.input name="month" label="Month" type="month" :value="$month->format('Y-m')" class="mb-0" /><x-ui.button type="submit">Show</x-ui.button></form>
                <x-ui.collection-controls label="Search GST records" />
                <x-ui.table variant="listing">
                    <thead><tr><x-ui.list-heading field="date" label="Date" class="text-center" /><x-ui.list-heading field="description" label="Description" /><x-ui.list-heading field="amount_display" label="GST" class="text-center" /><th class="text-center">Related records</th></tr></thead>
                    <tbody data-list-results>@forelse($records as $record)<tr><td class="text-center whitespace-nowrap">{{ $record['date'] }}</td><td>{{ $record['description'] }}</td><td class="text-center whitespace-nowrap">{{ money($record['amount'] / 100) }}</td><td class="text-center">@foreach($record['links'] as $link)<a class="inline-block whitespace-nowrap text-primary-color underline mr-3" href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</td></tr>@empty<tr><td colspan="4">No GST records in this month.</td></tr>@endforelse</tbody>
                </x-ui.table>
                <x-ui.list-pagination :paginator="$records" label="records" />
            @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
