<x-layout title="Confirm sponsorship invoice — STEMMechanics">
    <x-mast title="Confirm your invoice request" description="We’ll create and send the invoice after you confirm." />
    <x-container class="mx-auto max-w-2xl py-8 sm:py-12">
        @if($requestRecord && $requestRecord->used_at && $requestRecord->invoice)
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-semibold text-gray-900">This request has already been confirmed</h1>
                <p class="mt-3 leading-7 text-gray-700">Invoice <strong>{{ $requestRecord->invoice->invoice_number }}</strong> was created and sent to {{ $requestRecord->email }}.</p>
                @if(($requestRecord->payload['frequency'] ?? 'one_time') === 'monthly')
                    <p class="mt-3 leading-7 text-gray-700">We’ll email another invoice each month. You can manage or cancel monthly invoices on the <a href="{{ route('sponsor.manage.request') }}" class="font-medium text-primary-color underline hover:no-underline">Manage My Sponsorship</a> page.</p>
                @endif
                <div class="mt-6"><x-ui.button href="{{ route('sponsor.index') }}">Back to sponsorship</x-ui.button></div>
            </section>
        @elseif(!$requestRecord || $requestRecord->expires_at->isPast() || $requestRecord->used_at)
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-semibold text-gray-900">This link has expired</h1>
                <p class="mt-3 leading-7 text-gray-700">Start the invoice request again from the sponsorship page.</p>
                <div class="mt-6"><x-ui.button href="{{ route('sponsor.index') }}">Back to sponsorship</x-ui.button></div>
            </section>
        @else
            @php($requestDetails = (array) ($requestRecord->payload['details'] ?? []))
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-semibold text-gray-900">Please confirm this request</h1>
                @php($requestFrequency = ($requestRecord->payload['frequency'] ?? 'one_time') === 'monthly' ? 'monthly' : 'one-time')
                <p class="mt-3 leading-7 text-gray-700">We’ll create a {{ $requestFrequency }} sponsorship invoice for <strong>{{ $requestDetails['company_name'] ?? 'your organisation' }}</strong> for <strong>AUD ${{ number_format((float) ($requestRecord->payload['amount'] ?? 0), 2) }}{{ $requestFrequency === 'monthly' ? ' per month' : '' }}</strong>, then email it to <strong>{{ $requestRecord->email }}</strong>.</p>
                @if($requestFrequency === 'monthly')
                    <p class="mt-3 leading-7 text-gray-700">After the first invoice, we’ll email another invoice each month until the sponsorship is cancelled.</p>
                @endif
                <p class="mt-3 leading-7 text-gray-700">We’ll email the invoice after confirmation with the available payment instructions. Confirming this request does not take payment.</p>
                <form method="POST" action="{{ route('sponsor.invoice-request.confirm.submit', ['token' => $token]) }}" class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    @csrf
                    <x-ui.button color="outline" href="{{ route('sponsor.index') }}">Cancel</x-ui.button>
                    <x-ui.button type="submit">Confirm and send invoice</x-ui.button>
                </form>
            </section>
        @endif
    </x-container>
</x-layout>
