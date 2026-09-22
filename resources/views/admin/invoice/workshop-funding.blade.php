@php
    $fundingLines = $invoice->lines->whereIn('kind', ['workshop', 'multi_workshop']);
@endphp
@if($fundingLines->isNotEmpty())
<section class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
    <h3 class="text-lg font-semibold">Workshop funding</h3>
    <p class="mt-1 text-sm text-slate-500">Link each delivery to its workshop. Linked lines are allocated on the workshop; other lines remain on this invoice. These internal settings do not change the issued invoice.</p>
    @if($invoice->quote_id)<p class="mt-2 text-sm text-slate-500">Converted from a quote: check the workshop links below. You can leave a line manual until its workshop has been created.</p>@endif
    @foreach($fundingLines as $fundingLine)
        <div class="mt-4 border-t border-slate-100 pt-4" x-data="{ item: @js($fundingLine->toArray()) }">
            <p class="mb-3 font-medium">{{ $fundingLine->description }} <span class="text-sm font-normal text-slate-500">· ${{ number_format($fundingLine->line_total_inc_tax, 2) }} incl. GST</span></p>
            @if($fundingLine->kind === 'multi_workshop')
                <p class="mb-3 text-xs text-slate-500">Funding is split using each delivery's billed hours × seats.</p>
                @foreach($fundingLine->details_json['multi_workshop']['rows'] ?? [] as $rowIndex => $fundingRow)
                    <div class="mt-4 border-t border-slate-100 pt-4" x-data="{ item: @js($fundingRow) }">
                        <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]"><div><span class="mb-1 block text-sm">Workshop</span><x-finance.workshop-funding-fields :billing-locked="true" :name-prefix="'workshop_funding['.$fundingLine->id.'][rows]['.$rowIndex.']'" /></div><div><span class="mb-1 block text-sm">Allocation seats</span><x-finance.workshop-seats-field :billing-locked="true" /></div></div>
                    </div>
                @endforeach
            @else
                <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]"><div><span class="mb-1 block text-sm">Workshop</span><x-finance.workshop-funding-fields :billing-locked="true" :name-prefix="'workshop_funding['.$fundingLine->id.']'" /></div><div><span class="mb-1 block text-sm">Allocation seats</span><x-finance.workshop-seats-field :billing-locked="true" /></div></div>
            @endif
        </div>
    @endforeach
</section>
@endif
