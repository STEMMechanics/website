<x-finance.panel title="Cost centre allocation">
    @if($allocation['budget'] || $allocation['targets'])
        <p class="mb-3 text-xs text-slate-500">{{ ($allocation['budget']->manual ?? false) ? 'Manual override' : 'Pricing defaults' }} · {{ $allocation['version']->name }}</p>
        <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            @foreach($allocation['categories'] as $category)
                @if(($allocation['targets'][$category->id] ?? 0) || ($allocation['funding']['categories'][$category->id] ?? 0))
                    <div class="flex justify-between gap-3"><dt>{{ $category->name }}</dt><dd class="text-right whitespace-nowrap">{{ money(($allocation['funding']['categories'][$category->id] ?? 0) / 100) }} <span class="text-slate-500">/ {{ money(($allocation['targets'][$category->id] ?? 0) / 100) }}</span></dd></div>
                @endif
            @endforeach
        </dl>
        <p class="mt-3 text-xs text-slate-500">Funded / target, excluding GST. @if($allocation['workshopId'])Shared across {{ count($allocation['ids']) }} workshop invoices.@endif</p>
    @else
        <p class="text-sm text-slate-600">No cost centre allocation has been set. Open the editor to apply pricing defaults or enter an override.</p>
    @endif
    <x-ui.button class="mt-4" data-record-editor href="{{ route('admin.invoice.allocation.edit', $invoice) }}">{{ $allocation['budget'] ? 'Edit allocations' : 'Allocate to cost centres' }}</x-ui.button>
<p class="mt-2 text-xs text-slate-500">Based on saved invoice details. Save line changes first. In the editor, untick “Use pricing defaults” to enter a manual override.</p>
</x-finance.panel>
