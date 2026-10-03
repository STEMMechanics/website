<x-layout>
    <x-admin.sponsorship-mast title="Payment history" description="Payment history is shown within each sponsor’s details." />
    <x-container class="py-5 sm:py-8">
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <x-ui.input class="mb-0 w-72" name="search" label="Search" :value="request('search')" placeholder="Sponsor, invoice or Square ID" />
            <x-ui.select class="mb-0 min-w-44" name="status" label="Status">
                <option value="">All statuses</option>
                @foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
            </x-ui.select>
            <x-ui.button type="submit">Filter</x-ui.button>
        </form>
        <div class="space-y-4">
            @forelse($payments as $payment)
                <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h2 class="font-semibold text-gray-900">{{ $payment->sponsorship?->project?->name }} · {{ $payment->invoice_number ?: 'Invoice pending' }}</h2><p class="text-sm text-gray-500">{{ $payment->sponsorship?->sponsor?->contact_name }} · {{ $payment->sponsorship?->sponsor?->email }} · {{ $payment->paid_at?->format('j M Y, g:i a') ?? 'Not paid' }}</p><p class="mt-1 text-xs text-gray-500">Paid by {{ $payment->payment?->payment_method ? \App\Models\Payment::paymentMethodLabel($payment->payment->payment_method) : 'Unknown' }}</p></div>
                        <div class="text-right"><p class="font-semibold text-gray-900">{{ $payment->sponsorship?->currency }} {{ number_format((float) $payment->total_amount, 2) }}</p><p class="text-sm text-gray-500">{{ ucfirst($payment->status) }} · {{ str_replace('_', ' ', $payment->tax_treatment) }}</p></div>
                    </div>
                    <dl class="mt-4 grid gap-x-6 gap-y-2 border-t border-gray-100 pt-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-gray-500">Subtotal / GST</dt><dd class="mt-0.5 text-gray-800">{{ number_format((float) $payment->subtotal, 2) }} / {{ number_format((float) $payment->gst_amount, 2) }} ({{ number_format((float) $payment->tax_rate * 100, 2) }}%)</dd></div>
                        <div><dt class="text-gray-500">Square payment</dt><dd class="mt-0.5 break-all font-mono text-gray-800">{{ $payment->square_payment_id ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Square order / invoice</dt><dd class="mt-0.5 break-all font-mono text-gray-800">{{ $payment->square_order_id ?: '—' }}<br>{{ $payment->square_invoice_id ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Sponsor country snapshot</dt><dd class="mt-0.5 text-gray-800">{{ $payment->sponsor_country }} · {{ $payment->tax_code }}</dd></div>
                    </dl>
                    @if($payment->status === \App\Models\SponsorshipPayment::STATUS_COMPLETED && $payment->invoice_id)
                        <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                            <a class="text-sm font-medium text-primary-color hover:underline" href="{{ route('admin.sponsorship.payment.invoice', $payment) }}" target="_blank">Download invoice</a>
                            <form method="POST" action="{{ route('admin.sponsorship.payment.resend', $payment) }}">@csrf<x-ui.button type="submit" variant="plain" class="px-0 py-0 text-sm font-medium text-primary-color shadow-none hover:underline">Resend invoice</x-ui.button></form>
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-xl bg-white p-8 text-center text-gray-500">No sponsorship payments found.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $payments->links() }}</div>
    </x-container>
</x-layout>
