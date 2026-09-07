@props(['budget' => null])
@php
    $categories = \Illuminate\Support\Facades\DB::table('finance_categories')->orderBy('priority')->get();
@endphp
<x-ui.collapsible-section title="Estimated cost centre allocation" variant="panel">
    <x-slot:summary>
        <span x-text="'$' + (breakdown.total / 100).toFixed(2) + ' ex GST'"></span>
    </x-slot:summary>
    <x-ui.table variant="plain" table-class="w-full text-sm">
        <thead>
            <tr>
                <th class="pb-3 text-left">Cost centre</th>
                <th class="px-2 pb-3 text-right"><span class="block">Pricing</span><span class="font-normal" x-text="breakdown.participants + ' people'"></span></th>
                <th class="pb-3 text-right"><span class="block">Maximum</span><span class="font-normal" x-text="maxBreakdown.participants + ' people'"></span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $category)
                <tr x-show="(breakdown.categories['{{ $category->id }}'] || maxBreakdown.categories['{{ $category->id }}'] || 0) > 0">
                    <th class="py-1 text-left font-normal">{{ $category->name }}</th>
                    <td class="whitespace-nowrap px-2 py-1 text-right tabular-nums" x-text="'$' + ((breakdown.categories['{{ $category->id }}'] || 0) / 100).toFixed(2)"></td>
                    <td class="whitespace-nowrap py-1 text-right tabular-nums" x-text="'$' + ((maxBreakdown.categories['{{ $category->id }}'] || 0) / 100).toFixed(2)"></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-200 font-semibold">
                <th class="pt-2 text-left">Total <span class="whitespace-nowrap">(ex GST)</span></th>
                <td class="whitespace-nowrap px-2 pt-2 text-right tabular-nums" x-text="'$' + (breakdown.total / 100).toFixed(2)"></td>
                <td class="whitespace-nowrap pt-2 text-right tabular-nums" x-text="'$' + (maxBreakdown.total / 100).toFixed(2)"></td>
            </tr>
        </tfoot>
    </x-ui.table>
    <p class="mt-3 text-xs text-slate-500">Pricing attendance / full capacity. Actual allocations use purchased tickets and payments received.</p>

@if($budget)
    @php
        $report = app(\App\Services\Finance\FinancePlanner::class)->budgetReport($budget);
    @endphp
    <section class="mt-4 border-t border-gray-200 pt-4">
        <h3 class="mb-3 font-semibold text-gray-900">Saved cost centre allocation</h3>
        <dl class="space-y-2 text-sm">
            @foreach($categories as $category)
                @if(($report['targets'][$category->id] ?? 0) || ($report['funding']['categories'][$category->id] ?? 0))
                    <div class="flex justify-between gap-3">
                        <dt>{{ $category->name }}</dt>
                        <dd class="whitespace-nowrap tabular-nums">{{ money(($report['funding']['categories'][$category->id] ?? 0) / 100) }} <span class="text-slate-500">/ {{ money(($report['targets'][$category->id] ?? 0) / 100) }}</span></dd>
                    </div>
                @endif
            @endforeach
        </dl>
        <p class="mt-3 text-xs text-slate-500">Funded / target, excluding GST. Shared across the workshop’s ticket invoices.{{ $budget->manual ? ' Manual override.' : '' }}</p>
    </section>
@endif
</x-ui.collapsible-section>
