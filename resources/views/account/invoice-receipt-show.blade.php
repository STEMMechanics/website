<x-layout>
    <x-mast backRoute="account.invoice.receipts" :backRouteParams="['invoice' => $invoice]">Receipt {{ $receipt->id }}</x-mast>

    <x-container class="max-w-3xl mx-auto mt-8">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            @php
                $isRefund = $receipt->isRefund();
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
                    <div><strong>Receipt #:</strong> {{ $receipt->id }}</div>
                    <div><strong>Type:</strong> {{ $isRefund ? 'Refund' : 'Payment' }}</div>
                    <div><strong>Date:</strong> {{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                    <div><strong>Method:</strong> {{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</div>
                </div>
                <div class="md:text-right">
                    <div><strong>Amount:</strong> {{ money($isRefund ? -((float) $receipt->total_amount) : (float) $receipt->total_amount) }}</div>
                    <div><strong>Reference:</strong> {{ $receipt->reference ?: '-' }}</div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt]) }}" target="_blank" class="inline-flex items-center rounded-md bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark">View PDF</a>
                <a href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt, 'download' => 1]) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download PDF</a>
            </div>
        </div>
    </x-container>
</x-layout>
