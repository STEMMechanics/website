<x-layout>
    <x-mast title="Allocate invoices" />
    <form method="POST" action="{{ route('admin.invoice.bulk-allocation.apply') }}" data-record-form data-record-wide
        x-data="{ planId: @js((string) $version->id), selected: @js($preview ? collect($preview['rows'])->filter(fn ($row) => !$row['warning'])->keys()->map(fn ($key) => (string) $key)->values()->all() : []) }">
        @csrf
        <p class="mb-4 text-sm">{{ count($invoiceIds) }} invoices selected. Existing allocations and manual overrides are preserved.</p>
        <x-ui.select label="Allocation plan" x-model="planId">
            @foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
        </x-ui.select>
        <x-ui.button color="outline" data-record-editor
            href="{{ route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => $invoiceIds, 'version_id' => $version->id, 'review' => 1]) }}"
            x-bind:href="@js(route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => $invoiceIds, 'review' => 1])) + '&version_id=' + planId">Preview allocations</x-ui.button>
        @if($preview)
            <input type="hidden" name="token" value="{{ $preview['token'] }}">
            <p class="my-4 text-sm text-slate-600">Preview using <strong>{{ $version->name }}</strong>. Figures exclude GST. Workshop allocations include every linked ticket invoice, including invoices outside your selection.</p>
            <x-ui.table variant="listing" class="mb-5">
                <thead><tr><th class="w-10"></th><x-ui.list-heading label="Invoice / workshop" /><x-ui.list-heading label="Received" class="text-center" /><x-ui.list-heading label="Target" class="text-center" /><x-ui.list-heading label="Shortfall / surplus" class="text-center" /></tr></thead>
                <tbody>
                    @foreach($preview['rows'] as $key => $row)
                        @php($target = array_sum($row['targets']))
                        <tr>
                            <td><x-ui.checkbox bare small name="selected[]" :value="(string) $key" x-model="selected" :disabled="(bool) $row['warning']" :aria-label="'Allocate '.$row['name']" /></td>
                            <td>
                                <div class="font-semibold">{{ $row['name'] }}</div><p class="text-xs text-slate-500">{{ $row['date'] }}</p>
                                @if($row['workshop_id'])<p class="text-xs text-slate-500">{{ count($row['invoice_ids']) }} linked invoices · {{ $row['selected_invoice_count'] }} selected</p>@endif
                                @if($row['warning'])
                                    <p class="mt-1 text-xs text-amber-800">{{ $row['warning'] }}</p>
                                    <a class="text-xs text-primary-color underline" href="{{ route('admin.invoice.edit', $row['invoice_ids'][0]) }}" target="_blank" rel="noopener">Review invoice</a>
                                @else
                                    <x-ui.badge color="green">Ready</x-ui.badge>
                                    <details class="mt-2 text-xs"><summary class="cursor-pointer text-primary-color">Cost-centre breakdown (funded / target)</summary>
                                        <dl class="mt-2 space-y-1">@foreach($categories as $category)@if(($row['targets'][$category->id] ?? 0) || ($row['funding']['categories'][$category->id] ?? 0))<div class="flex justify-between gap-4"><dt>{{ $category->name }}</dt><dd class="whitespace-nowrap">{{ money(($row['funding']['categories'][$category->id] ?? 0) / 100) }} / {{ money(($row['targets'][$category->id] ?? 0) / 100) }}</dd></div>@endif@endforeach</dl>
                                    </details>
                                @endif
                            </td>
                            <td class="text-center whitespace-nowrap">{{ money($row['income']['net'] / 100) }}</td>
                            <td class="text-center whitespace-nowrap">{{ money($target / 100) }}</td>
                            <td @class(['text-center whitespace-nowrap', 'text-red-600' => $target > $row['income']['net']])>{{ money(($row['funding']['shortfall'] ?: $row['funding']['surplus']) / 100) }}<span class="block text-xs">{{ $target > $row['income']['net'] ? 'Shortfall' : 'Surplus' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <p class="mb-4 text-xs text-slate-500">Missing hours, seats or line types must be corrected on the invoice before it can use automatic allocations. Preview again after corrections. This changes cost-centre allocations, not invoice prices or payments.</p>
            <div class="flex justify-end"><x-ui.button type="submit" x-bind:disabled="!selected.length || planId !== @js((string) $version->id)" x-text="'Apply to ' + selected.length + ' records'">Apply allocations</x-ui.button></div>
        @else
            <x-ui.button type="submit" class="hidden" disabled>Apply allocations</x-ui.button>
        @endif
    </form>
</x-layout>
