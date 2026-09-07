<x-layout>
    <x-mast title="Owner finances" :tabs="[
        ['title' => 'Timesheet', 'route' => route('admin.timesheet.index', ['tab' => 'time']), 'active' => $tab === 'time'],
        ['title' => 'Contributions', 'route' => route('admin.timesheet.index', ['tab' => 'contributions']), 'active' => $tab === 'contributions'],
        ['title' => 'Drawings', 'route' => route('admin.timesheet.index', ['tab' => 'drawings']), 'active' => $tab === 'drawings'],
    ]">
        <x-slot:actions>@if($tab === 'contributions')<x-ui.button color="mast" data-record-editor href="{{ route('admin.timesheet.contribution.edit') }}">Record contribution</x-ui.button>@else<x-ui.button color="mast" data-record-editor href="{{ route('admin.timesheet.edit') }}">Record time</x-ui.button>@endif</x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="my-timesheet" :showPresets="false">
            @if($tab === 'contributions')
                @include('admin.timesheet.contributions')
            @elseif($tab === 'drawings')
                <div class="mt-6 space-y-6">@include('admin.timesheet.drawings')</div>
            @else
            <div class="mb-4 flex items-center gap-3">
                <x-ui.row-action label="Previous fortnight" icon="fa-chevron-left" tone="primary" data-dynamic-link href="{{ request()->fullUrlWithQuery(['fortnight' => $fortnight->copy()->subDays(14)->toDateString(), 'page' => null]) }}" />
            <h2 class="text-center text-lg font-semibold text-slate-700">{{ $fortnight->format('j M') }}–{{ $fortnight->copy()->addDays(13)->format('j M Y') }}</h2>
                <x-ui.row-action label="Next fortnight" icon="fa-chevron-right" tone="primary" data-dynamic-link href="{{ request()->fullUrlWithQuery(['fortnight' => $fortnight->copy()->addDays(14)->toDateString(), 'page' => null]) }}" />
            </div>
            <x-finance.timesheet-calendar :entries="$entries" />
            <dl class="mt-5 flex flex-wrap justify-end gap-x-8 gap-y-3 text-sm">
                <div class="flex items-baseline gap-2"><dt class="text-slate-500">Fortnight hours</dt><dd class="text-lg font-semibold tabular-nums">{{ round($hours, 2) }}</dd></div>
                <div class="flex items-baseline gap-2"><dt class="text-slate-500">Calculated amount</dt><dd class="text-lg font-semibold tabular-nums">{{ money($earned / 100) }}</dd></div>
            </dl>
            @endif
        </x-ui.dynamic-list>
    </x-container>
    <x-ui.record-dialog />
</x-layout>
