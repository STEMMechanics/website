<x-ui.table variant="listing">
    <thead><tr><th class="text-center whitespace-nowrap">Date</th><th>Reference</th><th>Cost centres</th><th class="text-center">Amount</th></tr></thead>
    <tbody data-list-results>
        @forelse($contributions as $contribution)
            <tr><td class="text-center whitespace-nowrap">{{ $contribution->date }}</td><td>{{ $contribution->reference }}</td><td>@foreach(json_decode($contribution->splits, true) as $id => $amount)<div>{{ $categories[$id] ?? 'Cost centre' }}: {{ money($amount / 100) }}</div>@endforeach</td><td class="text-center whitespace-nowrap">{{ money($contribution->cents / 100) }}</td></tr>
        @empty<tr><td colspan="4">No contributions recorded.</td></tr>@endforelse
    </tbody>
</x-ui.table>
<x-ui.list-pagination :paginator="$contributions" label="contributions" />
