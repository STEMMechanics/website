<x-layout title="Quote Requested">
    <x-mast :backRoute="'shop.index'" :backTitle="'Store'">
        Quote Requested
    </x-mast>

    <x-container class="max-w-3xl py-10">
        <div class="rounded-3xl border border-sky-200 bg-white p-8 shadow-sm">
            <div class="text-sm uppercase tracking-[0.18em] text-sky-700">Store Quote</div>
            <h1 class="mt-2 text-3xl font-bold text-gray-900">We’ve received your shipping quote request</h1>
            <p class="mt-4 text-sm text-gray-700">
                We’ll review the items and email your quote once the shipping options are ready.
            </p>
            @if($quoteNumber !== '')
                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    Quote reference: <span class="font-semibold text-gray-900">{{ $quoteNumber }}</span>
                </div>
            @endif
        </div>
    </x-container>
</x-layout>
