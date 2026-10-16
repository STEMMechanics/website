@props(['name', 'categories', 'allowDefault' => false, 'showCashSummary' => true, 'disabledExpression' => 'false'])

<div class="space-y-3">
    <input type="hidden" name="{{ $name }}" x-bind:value="remainderJson()" x-bind:disabled="{{ $disabledExpression }}">
    <x-ui.select :id="$name.'-choice'" label="Remaining funds" x-model="remainderMode" x-bind:disabled="{{ $disabledExpression }}" info="After purchase costs, Operational and Owner remuneration, excluding GST.">
        @if($allowDefault)<option value="default">Use store default</option>@endif
        <option value="cash">Keep as available business cash</option>
        @foreach($categories as $category)<option value="{{ $category->id }}">All to {{ $category->name }}</option>@endforeach
        <option value="split">Split by percentage</option>
    </x-ui.select>
    <div class="grid grid-cols-2 gap-x-5 gap-y-3 sm:grid-cols-3" x-show="remainderMode === 'split'" x-cloak>
        @foreach($categories as $category)
            <x-ui.input :id="$name.'-percent-'.$category->id" :label="$category->name" labelInfo="(%)" type="number" min="0" max="100" step="0.01" placeholder="0" x-bind:disabled="({{ $disabledExpression }}) || remainderMode !== 'split'" x-model="remainderPercent[{{ $category->id }}]" />
        @endforeach
    </div>
    <p class="text-xs text-red-700" x-show="remainderCashPercent() === null" x-cloak>Percentages must total no more than 100%.</p>
    @if($showCashSummary)
        <p class="text-xs text-slate-600" x-show="remainderCashPercent() > 0" x-cloak><span x-text="remainderCashPercent()"></span>% of the remainder stays as available business cash.</p>
    @endif
    @error($name)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</div>
