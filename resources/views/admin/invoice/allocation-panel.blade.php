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
</x-finance.panel>
