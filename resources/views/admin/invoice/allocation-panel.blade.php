@php
    $invoiceAllocation = app(\App\Services\Finance\InvoiceAllocation::class)->context($invoice);
@endphp
<x-finance.panel title="Cost centre allocation">
    @if(! $invoiceAllocation['warning'])
        <x-slot:actions><x-finance.allocation-calculator-button :invoice="$invoice" /></x-slot:actions>
    @endif
    <div id="invoice-cost-centres" data-record-refresh>
        @include('admin.invoice.allocation-form', ['inline' => true, 'allocation' => $invoiceAllocation])
    </div>
    @if($invoice->lines->contains('kind', 'product'))
        <form method="POST" action="{{ route('admin.product-allocation.apply', $invoice) }}" class="mt-4 flex justify-end" x-data x-on:submit.prevent="window.SM.confirm('Apply current defaults', 'Replace this invoice allocation, including any manual override, with the current product and pricing defaults?', 'Apply defaults', confirmed => { if (confirmed) $el.submit() })">
            @csrf
            <x-ui.button type="submit" color="outline">Apply current product allocations</x-ui.button>
        </form>
    @endif
</x-finance.panel>
