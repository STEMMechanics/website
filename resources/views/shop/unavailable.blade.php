<x-layout title="Store Temporarily Unavailable">
    <x-mast>Store Unavailable</x-mast>

    <x-container class="mt-8">
        <div class="mx-auto max-w-3xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-gradient-to-r from-sky-50 via-white to-amber-50 px-6 py-10 sm:px-10">
                <div class="max-w-2xl">
                    <h1 class="mt-5 text-3xl font-bold text-gray-900 sm:text-4xl">The store is temporarily unavailable.</h1>
                    <p class="mt-4 text-base text-gray-600 sm:text-lg">
                        {{ $reason }}
                    </p>
                    <p class="mt-3 text-sm text-gray-500">
                        If you need help with a existing product or an order, please contact us.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3 justify-between">
                        <x-ui.button color="outline" href="{{ route('workshop.index') }}">Browse Workshops</x-ui.button>
                        <x-ui.button href="{{ route('contact') }}">Contact Us</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
