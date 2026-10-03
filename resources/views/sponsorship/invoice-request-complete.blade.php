<x-layout title="Sponsorship confirmed — STEMMechanics">
    <x-mast title="Sponsorship confirmed" description="Your sponsorship invoice has been created and emailed." />
    <x-container class="mx-auto max-w-2xl py-8 sm:py-12">
        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-xl font-semibold text-gray-900">Your sponsorship invoice has been created</h1>
            <p class="mt-3 leading-7 text-gray-700">We’ve emailed invoice {{ $invoice->invoice_number }} to {{ $invoice->billing_email }}. It includes the payment instructions and due date.</p>
            <div class="mt-6"><x-ui.button href="{{ route('sponsor.index') }}">Back to sponsorship</x-ui.button></div>
        </section>
    </x-container>
</x-layout>
