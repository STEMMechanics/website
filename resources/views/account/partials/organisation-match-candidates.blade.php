@foreach($candidates as $candidate)
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
        <div class="min-w-0">
            <div class="font-medium text-slate-900">{{ $candidate['name'] }}</div>
            <div class="text-xs text-slate-500">Similar name</div>
        </div>
        <x-ui.button type="button" variant="soft" color="secondary" size="small" data-organisation-match-use data-organisation-name="{{ $candidate['name'] }}">Use this</x-ui.button>
    </div>
@endforeach
