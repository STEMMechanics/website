<x-layout>
    <x-mast title="Bulk allocation overrides" />
    <form method="POST" action="{{ route('admin.allocation-overrides.apply', ['kind' => $kind]) }}" data-record-form data-record-wide
        x-data="{ mode: @js($input['mode'] ?? 'single'), category: @js((string) ($input['category'] ?? $categories->first()?->id ?? '')), percentages: @js((object) ($input['percentages'] ?? [])), replace: @js(!empty($input['replace'])), selected: @js($preview ? collect($preview['rows'])->filter(fn ($row) => !$row['warning'])->keys()->map(fn ($key) => (string) $key)->values()->all() : []),
            get fingerprint() { return JSON.stringify([this.mode, this.category, this.percentages, this.replace]); }, original: null,
            init() { this.original = this.fingerprint; },
            get percentageTotal() { return Object.values(this.percentages).reduce((sum, value) => sum + Math.round(Number(value || 0) * 100), 0); },
            get previewUrl() { const url = new URL(@js(route('admin.allocation-overrides.edit', ['kind' => $kind, 'ids' => $input['ids'], 'review' => 1]))); url.searchParams.set('mode', this.mode); url.searchParams.set('category', this.category); url.searchParams.set('replace', this.replace ? '1' : '0'); Object.entries(this.percentages).forEach(([id, value]) => url.searchParams.set('percentages[' + id + ']', value || 0)); return url.href; }
        }">
        @csrf
        <p class="mb-4">{{ count($input['ids']) }} {{ $kind }} selected. Allocate each total excluding GST. These changes save manual overrides.</p>
        <x-ui.select label="Allocation method" x-model="mode">
            <option value="single">100% to one cost centre</option>
            <option value="percent">Split by percentage</option>
        </x-ui.select>
        <div x-show="mode === 'single'">
            <x-ui.select label="Cost centre" x-model="category">
                @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
            </x-ui.select>
        </div>
        <div x-show="mode === 'percent'" x-cloak class="mb-4 grid gap-3 sm:grid-cols-2">
            @foreach($categories as $category)
                <div class="flex items-center justify-between gap-3">
                    <label for="bulk-percent-{{ $category->id }}" class="text-sm">{{ $category->name }}</label>
                    <div class="flex w-28 shrink-0 items-center gap-1"><x-ui.input-control id="bulk-percent-{{ $category->id }}" type="number" min="0" max="100" step="0.01" x-model="percentages['{{ $category->id }}']" /><span>%</span></div>
                </div>
            @endforeach
            <p class="text-sm sm:col-span-2" aria-live="polite" x-text="'Total: ' + (percentageTotal / 100).toFixed(2) + '% — must equal 100%.'"></p>
        </div>
        <x-ui.checkbox label="Replace existing allocations, including manual overrides" x-model="replace" />
        <p class="my-3 text-sm text-slate-600">Preview before applying. Rounding is distributed so each split equals the record total exactly. Shared invoice groups require all linked invoices to be selected.</p>
        <x-ui.button color="outline" href="#" data-record-editor data-record-title="Bulk allocation overrides" x-bind:href="previewUrl" x-bind:aria-disabled="mode === 'percent' && percentageTotal !== 10000" x-on:click="if (mode === 'percent' && percentageTotal !== 10000) { $event.preventDefault(); $event.stopImmediatePropagation(); }">Preview allocations</x-ui.button>
        @if($preview)
            <input type="hidden" name="token" value="{{ $preview['token'] }}">
            <div class="my-5 space-y-3">
                @foreach($preview['rows'] as $index => $row)
                    <div class="rounded border border-slate-200 p-3">
                        <div class="flex items-start gap-3">
                            <x-ui.checkbox bare small name="selected[]" :value="(string) $index" x-model="selected" :disabled="(bool) $row['warning']" :aria-label="'Allocate '.$row['name']" />
                            <div class="min-w-0 flex-1"><p class="font-semibold break-words">{{ $row['name'] }}</p>
                                <p class="text-sm">{{ money($row['net'] / 100) }} excluding GST</p>
                                @if(count($row['ids']) > 1)<p class="text-xs text-slate-500">{{ count($row['ids']) }} linked invoices</p>@endif
                                @if($row['warning'])<p class="mt-2 text-sm text-amber-800">{{ $row['warning'] }}</p>
                                @else
                                    <dl class="mt-2 space-y-1 text-sm">@foreach($categories as $category)@if($row['targets'][$category->id] ?? 0)<div class="flex justify-between gap-3"><dt>{{ $category->name }}</dt><dd class="whitespace-nowrap tabular-nums">{{ money($row['targets'][$category->id] / 100) }}</dd></div>@endif@endforeach</dl>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p x-show="fingerprint !== original" x-cloak class="mb-3 text-sm text-amber-800">Settings changed. Preview again before applying.</p>
            <x-ui.button type="submit" x-bind:disabled="!selected.length || fingerprint !== original" x-text="'Apply ' + selected.length + ' overrides'">Apply overrides</x-ui.button>
        @else
            <x-ui.button type="submit" disabled class="hidden">Apply overrides</x-ui.button>
        @endif
    </form>
</x-layout>
