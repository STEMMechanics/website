<x-layout><x-mast title="Record contribution" /><x-container>
    <form method="POST" action="{{ route('admin.timesheet.contribution.store') }}" data-record-form x-data="SM.allocationTally(@js(['values' => $categories->mapWithKeys(fn ($category) => [$category->id => '0.00'])->all(), 'total' => 0, 'totalInput' => 'contribution-amount', 'exact' => true, 'enabled' => true, 'message' => 'Allocate the full contribution to cost centres.']))">@csrf
        <input type="hidden" name="token" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="grid gap-x-5 sm:grid-cols-2">
            <x-ui.input name="date" label="Date" type="date" :value="today()->toDateString()" required />
            <x-ui.input id="contribution-amount" name="amount" label="Contribution ($)" type="number" step="0.01" min="0.01" required x-on:input="refreshTotal()" />
            <x-ui.input class="sm:col-span-2" name="reference" label="Reference" maxlength="255" required />
        </div>
        <div class="space-y-3">
            @foreach($categories as $category)
                <div class="grid grid-cols-[minmax(0,1fr)_9rem] items-center gap-4">
                    <label for="contribution-{{ $category->id }}" class="flex min-w-0 flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <span>{{ $category->name }}</span>
                        <span class="whitespace-nowrap text-xs tabular-nums {{ ($balances[$category->id] ?? 0) < 0 ? 'text-red-600' : 'text-slate-500' }}"><span class="sr-only">Current balance: </span>{{ money(($balances[$category->id] ?? 0) / 100) }}</span>
                    </label>
                    <div class="relative"><span class="absolute left-3 top-2 text-slate-500">$</span><x-ui.input-control :id="'contribution-'.$category->id" :name="'splits['.$category->id.']'" class="pl-7! text-right" type="number" min="0" step="0.01" x-model="values['{{ $category->id }}']" x-on:blur="format('{{ $category->id }}')" required /></div>
                </div>
            @endforeach
        </div>
        <dl class="mt-4 space-y-2 border-t border-slate-200 pt-3 text-sm" aria-live="polite"><div class="flex justify-between"><dt>Allocated</dt><dd x-text="money(allocated)"></dd></div><div class="flex justify-between"><dt>Remaining</dt><dd x-text="money(remaining)"></dd></div></dl>
        <x-finance.save>Record contribution</x-finance.save>
    </form>
</x-container></x-layout>
