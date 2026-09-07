@props(['entries'])
<div class="space-y-4" data-timesheet-calendar data-list-results>
    <div class="hidden grid-cols-7 gap-2 lg:grid" aria-hidden="true">
        @foreach($entries->take(7) as $entry)
            <div class="px-3 text-sm font-medium text-slate-500">{{ $entry->date->format('l') }}</div>
        @endforeach
    </div>
    @foreach($entries->chunk(7) as $week)
        <section aria-label="Week beginning {{ $week->first()->date->format('j F') }}">
            <h3 class="mb-2 text-sm font-medium text-slate-500 lg:hidden">{{ $week->first()->date->format('j M') }}–{{ $week->last()->date->format('j M') }}</h3>
            <ol class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-7">
                @foreach($week as $entry)
                    @php
                        $future = $entry->date->gt(today());
                        $today = $entry->date->isToday();
                        $label = 'Time for '.$entry->date->format('l j F Y');
                    @endphp
                    <li class="min-w-0">
                        <x-ui.button variant="plain" :href="$future ? null : route('admin.timesheet.edit', ['date' => $entry->date->toDateString()])" :disabled="$future" data-record-editor :data-record-title="$label" :aria-label="$label.', '.round($entry->minutes / 60, 2).' hours'" :aria-current="$today ? 'date' : null"
                            class="flex h-full w-full flex-col rounded-xl border p-3 text-left transition-colors lg:min-h-36 {{ $today ? 'border-primary-color ring-1 ring-primary-color' : 'border-slate-200' }} {{ $entry->date->isWeekend() ? 'bg-sky-50' : 'bg-white' }} {{ $future ? 'opacity-50' : 'hover:border-primary-color hover:bg-sky-50' }}">
                            <span class="flex w-full items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-700"><span class="lg:hidden">{{ $entry->date->format('D') }} </span>{{ $entry->date->format('j M') }}</span>
                                @if($today)<span class="text-xs font-medium text-primary-color">Today</span>@endif
                            </span>
                            <span class="mt-2 text-lg font-semibold tabular-nums {{ $entry->minutes ? 'text-primary-color' : 'text-slate-400' }}">{{ round($entry->minutes / 60, 2) }} <span class="text-xs font-normal">hours</span></span>
                            @if($entry->notes)<span class="mt-1 line-clamp-2 w-full whitespace-normal text-xs text-slate-500 wrap-anywhere">{{ $entry->notes }}</span>@endif
                        </x-ui.button>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>
