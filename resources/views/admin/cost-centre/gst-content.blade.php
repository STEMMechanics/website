<x-finance.panel title="Monthly GST">
    <form method="GET" action="{{ route('admin.cost-centre.gst') }}" class="mb-4 flex items-end gap-3"><x-ui.input name="month" id="month" label="Month" type="month" :value="$month->format('Y-m')" class="mb-0" /><x-ui.button type="submit">Show</x-ui.button></form>
    <div class="grid gap-4 sm:grid-cols-3">@foreach(['GST on sales' => $gst['sales'], 'Recorded purchase credits' => $gst['credits'], 'Net GST' => $gst['net']] as $label => $amount)<div class="rounded-xl bg-slate-50 p-4"><p>{{ $label }}</p><p class="text-2xl font-semibold">{{ money($amount / 100) }}</p></div>@endforeach</div>
    <p class="mt-4 text-sm text-slate-600">Uses received payments and paid expenses. Pending bank transfers, unsuccessful gateway payments and non-cash entries are excluded. Purchase GST must represent eligible credits. This tracks the GST portion only; other BAS obligations need their own reserves.</p>
</x-finance.panel>
<x-finance.panel title="Record a GST settlement">
    @include('admin.cost-centre.gst-settlement-form', ['settlement' => $history->first()['settlement'] ?? null])
</x-finance.panel>
@include('admin.cost-centre.gst-history')
