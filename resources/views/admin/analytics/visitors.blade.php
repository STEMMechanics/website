<x-layout>
    <x-mast title="Online visitors" backRoute="admin.analytics.index" backTitle="Analytics">
        <x-slot:actions><x-online-visitors /></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <p data-visitor-details-error class="mb-4 text-sm text-red-700" hidden>Unable to refresh visitors. Showing the last available information.</p>
        <div data-visitor-details data-url="{{ route('admin.analytics.visitors') }}" aria-live="polite">
            @include('admin.analytics.partials.visitors')
        </div>
    </x-container>
</x-layout>
