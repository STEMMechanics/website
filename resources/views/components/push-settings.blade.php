@props(['embedded' => false])

<section class="{{ $embedded ? 'rounded-2xl bg-gray-50 p-4' : 'my-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm' }}" data-push-settings>
    <div class="flex flex-wrap items-start justify-between gap-2">
        @if($embedded)
            <h3 class="text-sm font-semibold text-gray-900">Browser notifications</h3>
        @else
            <h2 class="text-lg font-semibold text-gray-900">Browser notifications</h2>
        @endif
        <x-ui.badge color="gray" uppercase data-push-count hidden></x-ui.badge>
    </div>
    <p class="mt-3 text-sm text-gray-600" data-push-status role="status">Loading notification settings…</p>
    <x-ui.button type="button" class="mt-3" data-push-enable disabled>Enable on this device</x-ui.button>
    <p data-push-empty class="mt-4 rounded-2xl border border-dashed border-gray-300 p-4 text-sm text-gray-500" hidden>No devices are receiving notifications.</p>
    <ul data-push-devices class="mt-4 space-y-3"></ul>
    <template data-push-device-template>
        <li class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span data-push-device-name class="wrap-break-word text-sm font-semibold text-gray-900"></span>
                        <x-ui.badge color="success" data-push-current hidden>This device</x-ui.badge>
                    </div>
                    <p data-push-device-status class="mt-1 text-xs text-gray-600"></p>
                </div>
                <div class="grid w-full grid-cols-2 gap-2">
                    <x-ui.button type="button" color="primary-outline" class="w-full justify-center px-4! py-1.5!" data-push-test>Test</x-ui.button>
                    <x-ui.button type="button" color="danger-outline" class="w-full justify-center px-4! py-1.5!" data-push-remove>Remove</x-ui.button>
                </div>
            </div>
        </li>
    </template>
</section>
