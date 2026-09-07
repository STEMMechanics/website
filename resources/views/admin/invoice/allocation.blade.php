<x-layout><x-mast title="Cost centre allocation" backRoute="admin.invoice.index" backTitle="Invoices" /><x-container class="py-5">
    @include('admin.invoice.allocation-form', ['inline' => request()->boolean('inline')])
</x-container></x-layout>
