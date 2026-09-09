@php($fieldPrefix = request()->header('X-SM-Fragment') === 'record' ? 'gst-popup-' : 'gst-')
<form data-record-form method="POST" action="{{ route('admin.finance.settlement') }}">
    @csrf
    @if($settlement ?? null)<input type="hidden" name="settlement_id" value="{{ $settlement->id }}">@endif
    <p class="mb-4 text-sm text-slate-600">Enter the GST component paid to the ATO. Use a negative amount for a GST refund. Historical payments before the opening date are retained for reference and do not reduce current cash again.</p>
    <div class="grid gap-x-4 sm:grid-cols-2">
        <x-ui.input name="period" :id="$fieldPrefix.'period'" label="BAS month" type="month" :value="$month->format('Y-m')" :readonly="(bool) ($settlement ?? null) || request()->header('X-SM-Fragment') === 'record'" required />
        <x-ui.input name="paid_on" :id="$fieldPrefix.'paid_on'" label="Payment / refund date" type="date" :value="$settlement->paid_on ?? now()->toDateString()" required />
        <x-ui.input name="amount" :id="$fieldPrefix.'amount'" label="GST settled (negative for refund)" type="number" step="0.01" :value="isset($settlement) ? number_format($settlement->cents / 100, 2, '.', '') : ''" required />
        <x-ui.input name="reference" :id="$fieldPrefix.'reference'" label="Receipt / note (optional)" :value="$settlement->reference ?? ''" info="For your records only, e.g. a myGov receipt number or Paid via myGov by credit card." />
    </div>
    <x-finance.save>{{ ($settlement ?? null) ? 'Save settlement' : 'Record settlement' }}</x-finance.save>
</form>
