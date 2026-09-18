@php
    $invoiceAllocation = app(\App\Services\Finance\InvoiceAllocation::class)->context($invoice);
@endphp
<x-finance.panel title="Cost centre allocation">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            @if(! $invoiceAllocation['warning'])
                <x-finance.allocation-calculator-button :invoice="$invoice" />
            @endif
            @if($invoice->lines->contains('kind', 'product'))
                <form method="POST" action="{{ route('admin.product-allocation.apply', $invoice) }}" class="flex" x-data x-on:submit.prevent="window.SM.confirm('Apply current defaults', 'Replace this invoice allocation, including any manual override, with the current product and pricing defaults?', 'Apply defaults', confirmed => { if (confirmed) $el.submit() })">
                    @csrf
                    <x-ui.button type="submit" color="outline" class="size-10 p-0!" aria-label="Apply current product allocations" title="Apply current product allocations"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></x-ui.button>
                </form>
            @endif
        </div>
    </x-slot:actions>
    <div id="invoice-cost-centres" data-record-refresh>
        @include('admin.invoice.allocation-form', ['inline' => true, 'allocation' => $invoiceAllocation])
    </div>
</x-finance.panel>
