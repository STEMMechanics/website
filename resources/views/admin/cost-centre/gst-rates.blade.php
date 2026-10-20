<x-layout>
    <x-mast title="GST rates" backRoute="admin.cost-centre.gst" backTitle="GST" description="Schedule the standard GST rate used for new sales and pricing." />
    <x-container class="space-y-6 py-5 sm:py-8">
        <x-finance.panel title="Current GST rate">
            <p class="text-2xl font-semibold">{{ number_format($currentRate * 100, 2) }}%</p>
            <p class="mt-2 text-sm text-slate-600">Saved quotes and invoices keep their original rates and amounts. Refunds use the original sale's tax details. Each scheduled change specifies how existing product and ticket prices are adjusted.</p>
        </x-finance.panel>
        <x-finance.panel title="Rate history and scheduled changes">
            <x-ui.table variant="listing">
                <thead><tr><th>Effective from</th><th class="text-right">GST rate</th><th>Existing prices</th><th>Status</th><th class="w-px whitespace-nowrap">Actions</th></tr></thead>
                <tbody>
                    @foreach($rates as $entry)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($entry['effective_from'])->format('d M Y') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($entry['rate'] * 100, 2) }}%</td>
                            <td>{{ $entry['price_basis'] === 'exclusive' ? 'Keep ex-GST prices' : 'Keep inc-GST prices' }}</td>
                            <td>{{ $entry['effective_from'] > today()->toDateString() ? 'Scheduled' : 'Recorded' }}</td>
                            <td class="text-right">
                                @if($entry['effective_from'] > today()->toDateString())
                                    <form method="POST" action="{{ route('admin.finance.gst-rates.destroy', $entry['id']) }}">@csrf @method('DELETE')<x-ui.row-action type="submit" label="Remove scheduled rate" icon="fa-trash" tone="danger" /></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-finance.panel>
        <x-finance.panel title="Schedule a GST rate">
            <form method="POST" action="{{ route('admin.finance.gst-rates.store') }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="GST rate (%)" name="percentage" type="number" min="0.01" max="100" step="0.01" :value="old('percentage')" required />
                    <x-ui.input label="Effective from" name="effective_from" type="date" :min="today()->addDay()->toDateString()" :value="old('effective_from')" required />
                </div>
                <x-ui.select label="Existing product and ticket prices" name="price_basis" required>
                    <option value="exclusive" @selected(old('price_basis', 'exclusive') === 'exclusive')>Keep ex-GST prices; adjust inc-GST prices</option>
                    <option value="inclusive" @selected(old('price_basis') === 'inclusive')>Keep inc-GST prices; change the ex-GST portion</option>
                </x-ui.select>
                <p class="mb-4 text-sm text-slate-600">The rate changes from midnight on this date in {{ config('app.timezone') }}. Only schedule a confirmed change. Existing records are not repriced automatically.</p>
                <div class="flex justify-end"><x-ui.button type="submit">Schedule rate</x-ui.button></div>
            </form>
        </x-finance.panel>
    </x-container>
</x-layout>
