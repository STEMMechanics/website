<div x-data="SM.drawingTypePicker(@js($purpose), @js($drawingTotals))" class="grid items-start gap-5 lg:grid-cols-[minmax(18rem,22rem)_minmax(0,1fr)]">
<div class="space-y-4">
<x-finance.panel title="Record drawing">
    <x-ui.select name="drawing_type" label="Drawing type" :value="$purpose" x-model="purpose" x-on:change="changeType()"><option value="time">Owner remuneration</option><option value="contribution">Return of contributions</option></x-ui.select>
    <dl class="mb-4 space-y-2 text-sm">
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Outstanding to pay</dt><dd class="font-semibold tabular-nums" x-text="money(totals[purpose].outstanding)">{{ money($drawingTotals[$purpose]['outstanding'] / 100) }}</dd></div>
        <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Available to pay</dt><dd class="font-semibold tabular-nums" x-text="money(totals[purpose].available)">{{ money($drawingTotals[$purpose]['available'] / 100) }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('admin.finance.drawing') }}">@csrf<input type="hidden" name="purpose" value="{{ $purpose }}" x-bind:value="purpose"><input type="hidden" name="token" value="{{ \Illuminate\Support\Str::uuid() }}"><x-ui.input name="amount" label="Amount to transfer" type="number" min="0.01" step="0.01" required /><x-finance.save>Record drawing</x-finance.save></form>
</x-finance.panel>
<x-finance.panel title="Remuneration transferred">
    <p class="mb-3">Pay forgone to fund cost centres: {{ money($forgone / 100) }}</p>
    <x-ui.button color="outline" href="{{ route('admin.cost-centre.transfer.edit', ['from' => 'remuneration']) }}">Transfer remuneration</x-ui.button>
    @foreach($remunerationTransfers as $transfer)
        <p class="mt-3 text-sm">{{ $transfer->created_at }} · {{ money($transfer->cents / 100) }} to <a href="{{ route('admin.cost-centre.show', $transfer->category_id) }}">{{ $transfer->centre_name }}</a>@if($transfer->reason)<br>{{ $transfer->reason }}@endif</p>
    @endforeach
</x-finance.panel>
</div>
<x-finance.panel title="Drawing history" x-bind:aria-busy="loading">
    <div x-show="loading" x-cloak class="flex min-h-40 items-center justify-center" role="status" aria-label="Loading drawing history"><x-ui.loading-indicator class="text-6xl" /></div>
    <div x-ref="history" data-drawing-history x-show="!loading">
    @forelse($drawings as $drawing)<div class="mb-4 rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap items-center justify-between gap-3"><p><span class="text-sm text-slate-500">{{ $drawing->purpose === 'contribution' ? 'Return of contributions' : 'Owner remuneration' }}</span><br>{{ money($drawing->cents / 100) }} · {{ $drawing->created_at }} @if($drawing->reference) · {{ $drawing->reference }}@endif</p><x-ui.badge :color="$drawing->status === 'paid' ? 'success' : 'slate'">{{ ucfirst($drawing->status) }}</x-ui.badge></div>
        @if($drawing->status === 'pending')
            <form id="drawing-payment-{{ $drawing->id }}" method="POST" action="{{ route('admin.finance.drawingStatus', $drawing->id) }}" class="mt-4">
                @csrf
                <input type="hidden" name="status" value="paid">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-ui.input name="paid_on" label="Transfer date" type="date" :value="now()->toDateString()" required />
                    <x-ui.input name="reference" label="Bank reference (optional)" maxlength="255" />
                </div>
            </form>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <form method="POST" action="{{ route('admin.finance.drawingStatus', $drawing->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <x-ui.button type="submit" color="danger">Cancel drawing</x-ui.button>
                </form>
                <x-ui.button type="submit" form="drawing-payment-{{ $drawing->id }}">Mark transferred</x-ui.button>
            </div>
        @endif
    </div>@empty<p>No drawings recorded.</p>@endforelse
    <x-ui.list-pagination :paginator="$drawings" label="drawings" />
    </div>
</x-finance.panel>

</div>
