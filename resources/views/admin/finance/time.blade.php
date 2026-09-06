<x-finance.panel title="Record my time">
    <p class="mb-4 text-sm text-slate-600">Record actual time, including travel and preparation. The hourly rate is an internal owner remuneration target, separate from customer billing and employee payroll.</p>
    <form method="POST" action="{{ route('admin.finance.time') }}">@csrf
        <div class="grid gap-x-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input name="date" label="Date" type="date" :value="now()->toDateString()" required />
            <x-ui.select name="activity" label="Activity">@foreach(['Delivery', 'Preparation', 'Pack down', 'Travel', 'Administration', 'Development'] as $activity)<option>{{ $activity }}</option>@endforeach</x-ui.select>
            <x-ui.input name="minutes" label="Minutes worked" type="number" min="1" max="1440" required />
            <x-ui.input name="rate" label="Hourly target" type="number" step="0.01" min="0" value="60" required />
            <x-ui.select name="workshop_id" label="Workshop (optional)"><option value="">General business time</option>@foreach($workshops as $workshop)<option value="{{ $workshop->id }}">{{ $workshop->starts_at?->format('j M Y') }} · {{ $workshop->title }}</option>@endforeach</x-ui.select>
            <x-ui.input name="notes" label="Notes" maxlength="1000" />
        </div><x-finance.save>Record time</x-finance.save>
    </form>
</x-finance.panel>
<x-finance.panel title="Fortnightly timesheet">
    <form method="GET" action="{{ route('admin.finance.index') }}" class="mb-4 flex items-end gap-3"><input type="hidden" name="tab" value="time"><x-ui.input name="fortnight" label="Fortnight starting" type="date" :value="$fortnight->toDateString()" class="mb-0" /><x-ui.button type="submit">Show</x-ui.button></form>
    <p class="mb-4">{{ $fortnight->format('j M Y') }}–{{ $fortnight->copy()->addDays(13)->format('j M Y') }} · {{ round($timeEntries->sum('minutes') / 60, 2) }} hours · Target {{ money($timeEntries->sum(fn ($row) => round($row->minutes * $row->rate_cents / 60)) / 100) }}</p>
    @forelse($timeEntries as $entry)<details class="mb-3 rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer">{{ $entry->date }} · {{ $entry->activity }} · {{ $entry->minutes }} minutes · {{ money(round($entry->minutes * $entry->rate_cents / 60) / 100) }}</summary>
        <form method="POST" action="{{ route('admin.finance.time') }}" class="mt-4">@csrf<input type="hidden" name="id" value="{{ $entry->id }}"><input type="hidden" name="workshop_id" value="{{ $entry->workshop_id }}"><input type="hidden" name="activity" value="{{ $entry->activity }}"><input type="hidden" name="notes" value="{{ $entry->notes }}"><div class="grid gap-x-4 sm:grid-cols-3"><x-ui.input name="date" label="Date" type="date" :value="$entry->date" required /><x-ui.input name="minutes" label="Minutes" type="number" min="1" max="1440" :value="$entry->minutes" required /><x-ui.input name="rate" label="Hourly target" type="number" step="0.01" min="0" :value="$entry->rate_cents / 100" required /></div><x-finance.save>Save correction</x-finance.save></form>
    </details>@empty<p>No time recorded in this fortnight.</p>@endforelse
</x-finance.panel>
