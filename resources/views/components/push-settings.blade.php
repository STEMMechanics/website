@props(['embedded' => false])

<section class="{{ $embedded ? 'rounded-2xl bg-gray-50 p-4' : 'my-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm' }}" data-push-settings>
    <div class="flex flex-wrap items-start justify-between gap-2">
        @if($embedded)
            <h3 class="text-sm font-semibold text-gray-900">Browser notifications</h3>
        @else
            <h2 class="text-lg font-semibold text-gray-900">Browser notifications</h2>
        @endif
        <span data-push-count class="whitespace-nowrap rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500" hidden></span>
    </div>
    <p class="mt-3 text-sm text-gray-600" data-push-status role="status">Loading notification settings…</p>
    <div class="mt-3 flex flex-wrap gap-3">
        <x-ui.button type="button" data-push-enable disabled>Enable on this device</x-ui.button>
    </div>
    <p data-push-empty class="mt-4 rounded-2xl border border-dashed border-gray-300 p-4 text-sm text-gray-500" hidden>No devices are receiving notifications.</p>
    <ul data-push-devices class="mt-4 space-y-3"></ul>
    <template data-push-device-template>
        <li class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span data-push-device-name class="break-words text-sm font-semibold text-gray-900"></span>
                        <span data-push-current class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800" hidden>This device</span>
                    </div>
                    <p data-push-device-status class="mt-1 text-xs text-gray-600"></p>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button type="button" color="primary-outline" class="px-4! py-1.5!" data-push-test>Test</x-ui.button>
                    <x-ui.button type="button" color="danger-outline" class="px-4! py-1.5!" data-push-remove>Remove</x-ui.button>
                </div>
            </div>
        </li>
    </template>
</section>
