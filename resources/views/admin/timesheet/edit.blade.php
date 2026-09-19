<x-layout><x-mast title="Record time" backRoute="admin.timesheet.index" backTitle="Timesheet" /><x-container class="py-5">

    <form method="POST" action="{{ route('admin.timesheet.store') }}" data-record-form>@csrf
@if($dayEntries->count() > 1)
        <div class="mb-4 space-y-2">
            <p class="text-sm text-slate-600">This day has multiple existing entries. Choose an entry to edit; the calendar shows their combined hours.</p>
            <div class="flex flex-wrap gap-2">
                @foreach($dayEntries as $dayEntry)
                    <x-ui.button variant="secondary" data-record-editor :href="route('admin.timesheet.edit', ['id' => $dayEntry->id])">{{ round($dayEntry->minutes / 60, 2) }} hours{{ $dayEntry->id === $entry->id ? ' · Editing' : '' }}</x-ui.button>
                @endforeach
            </div>
        </div>
    @endif
<p class="mb-4 text-sm text-slate-600">Enter decimal hours, for example 1.5 for 90 minutes. Time is stored to the nearest minute.</p>
        <p class="mb-4 text-sm text-slate-600">Hourly rate: ${{ number_format($entry ? $entry->rate_cents / 100 : (float) \App\Models\SiteOption::value('finance.owner-hourly-rate', '40.00'), 2) }}. {{ $entry ? 'This entry keeps its saved rate.' : 'New entries use the current owner hourly rate in Site Options.' }}</p>
        @if($entry && (int) $entry->rate_cents !== (int) round((float) \App\Models\SiteOption::value('finance.owner-hourly-rate', '40.00') * 100))
            <x-ui.checkbox name="reset_rate" value="1" :checked="old('reset_rate', false)" :label="'Use current site rate ($'.number_format((float) \App\Models\SiteOption::value('finance.owner-hourly-rate', '40.00'), 2).'/hour) for this entry'" info="Only changes this entry when you save. Other saved entries keep their rates." />
        @endif
        @if($entry)<input type="hidden" name="id" value="{{ $entry->id }}">@endif
        <div class="grid gap-x-5 sm:grid-cols-2">
            <x-ui.input name="date" label="Date" type="date" :value="$date" required />
            <x-ui.input name="hours" label="Hours worked" type="number" step="0.01" min="0" max="24" :value="$entry ? round($entry->minutes / 60, 2) : 0" required />
            <x-ui.input class="sm:col-span-2" name="notes" label="Notes (optional)" type="textarea" rows="3" maxlength="1000" :value="$entry->notes ?? null" />
        </div><x-finance.save>Save time</x-finance.save>
    </form>
</x-container></x-layout>
