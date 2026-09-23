@props(['categories', 'prefix' => 'splits', 'idPrefix' => 'allocation', 'exact' => true, 'columns' => 2, 'percentage' => false, 'totalLabel' => null, 'shortfall' => false, 'showTotals' => true, 'editableDefaults' => false])
<div class="grid gap-x-10 gap-y-4 {{ $columns === 2 ? 'lg:grid-cols-2' : '' }}">
    @foreach($categories->values()->split($columns) as $column)
        <div class="space-y-3">
            @foreach($column as $category)
                <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_11rem] items-center gap-2">
                    <label for="{{ $idPrefix }}-{{ $category->id }}" class="min-w-0 break-words text-sm text-slate-700">{{ $category->name }}:</label>
                    <div class="flex items-center gap-1">
                    <div class="relative min-w-0 flex-1">
                        <span class="pointer-events-none absolute left-3 top-2 text-slate-500" aria-hidden="true">{{ $percentage ? '%' : '$' }}</span>
                        <x-ui.input-control type="number" :id="$idPrefix.'-'.$category->id" :name="$prefix.'['.$category->id.']'" class="pl-7! text-right tabular-nums" min="0" :max="$percentage ? 100 : 10000000" step="0.01" x-model="values['{{ $category->id }}']" x-on:blur="format('{{ $category->id }}')" x-bind:disabled="{{ $editableDefaults ? 'false' : '!enabled' }}" x-on:input="{{ $editableDefaults ? 'enabled = true' : '' }}" required />
                    </div>
                    <button type="button" class="inline-flex size-10 shrink-0 items-center justify-center rounded border border-slate-300 text-primary-color hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40" x-on:click="@if($editableDefaults) enabled = true; @endif allocateRemaining('{{ $category->id }}')" x-bind:disabled="{{ $editableDefaults ? 'remaining === 0' : '!enabled || remaining === 0' }}" x-bind:aria-label="(remaining < 0 ? 'Reduce allocation by shortfall in ' : 'Allocate remaining {{ $percentage ? 'percentage' : 'amount' }} to ') + @js($category->name)" x-bind:title="(remaining < 0 ? 'Reduce allocation by shortfall in ' : 'Allocate remaining {{ $percentage ? 'percentage' : 'amount' }} to ') + @js($category->name)">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
    @if($percentage)
        <p class="border-t border-slate-200 pt-4 text-sm" aria-live="polite" x-bind:class="enabled && remaining !== 0 ? 'text-amber-700' : 'text-slate-700'">Total: <strong class="tabular-nums" x-text="(allocated / 100).toFixed(2) + '%'"></strong><span x-show="enabled && remaining !== 0"> — must equal 100%.</span></p>
    @elseif($showTotals)
    <dl class="{{ $columns === 2 ? 'lg:col-start-2' : '' }} space-y-2 border-t border-slate-200 pt-4 text-sm" aria-live="polite">
        <div class="flex justify-between gap-4"><dt>{{ $totalLabel ?? ($exact ? 'Expense excluding GST' : 'Invoice total excluding GST') }}</dt><dd class="font-semibold tabular-nums" x-text="money(total)"></dd></div>
        <div class="flex justify-between gap-4"><dt>{{ $exact ? 'Allocated' : 'Allocation targets' }}</dt><dd class="font-semibold tabular-nums" x-text="money(allocated)"></dd></div>
        <div class="flex justify-between gap-4" :class="remaining < 0 ? 'text-red-700' : (remaining === 0 ? 'text-emerald-700' : 'text-amber-700')"><dt @if($shortfall) x-text="remaining < 0 ? 'Shortfall' : 'Unallocated'" @endif>Remaining</dt><dd class="font-semibold tabular-nums" x-text="money({{ $shortfall ? 'Math.abs(remaining)' : 'remaining' }})"></dd></div>
    </dl>
    @endif
</div>
