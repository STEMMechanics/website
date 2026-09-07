@props(['categories', 'prefix' => 'splits', 'idPrefix' => 'allocation', 'exact' => true, 'columns' => 2])
<div class="grid gap-x-10 gap-y-4 {{ $columns === 2 ? 'lg:grid-cols-2' : '' }}">
    @foreach($categories->values()->split($columns) as $column)
        <div class="space-y-3">
            @foreach($column as $category)
                <div class="grid grid-cols-[minmax(0,1fr)_9rem] items-center gap-4">
                    <label for="{{ $idPrefix }}-{{ $category->id }}" class="text-sm text-slate-700">{{ $category->name }}:</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-2 text-slate-500" aria-hidden="true">$</span>
                        <x-ui.input-control type="number" :id="$idPrefix.'-'.$category->id" :name="$prefix.'['.$category->id.']'" class="pl-7! text-right tabular-nums" min="0" max="10000000" step="0.01" x-model="values['{{ $category->id }}']" x-on:blur="format('{{ $category->id }}')" x-bind:disabled="!enabled" required />
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
    <dl class="{{ $columns === 2 ? 'lg:col-start-2' : '' }} space-y-2 border-t border-slate-200 pt-4 text-sm" aria-live="polite">
        <div class="flex justify-between gap-4"><dt>{{ $exact ? 'Expense excluding GST' : 'Invoice total excluding GST' }}</dt><dd class="font-semibold tabular-nums" x-text="money(total)"></dd></div>
        <div class="flex justify-between gap-4"><dt>{{ $exact ? 'Allocated' : 'Allocation targets' }}</dt><dd class="font-semibold tabular-nums" x-text="money(allocated)"></dd></div>
        <div class="flex justify-between gap-4" :class="remaining === 0 ? 'text-emerald-700' : 'text-amber-700'"><dt>Remaining</dt><dd class="font-semibold tabular-nums" x-text="money(remaining)"></dd></div>
    </dl>
</div>
