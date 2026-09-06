@props(['name', 'showPresets' => true])
<section data-dynamic-list="{{ $name }}" aria-busy="false" class="relative min-w-0">
    <p data-list-status role="status" aria-live="polite" class="sr-only"></p>
    <div data-list-loader class="sm-list-loader" aria-hidden="true">
        <x-ui.loading-indicator class="sm-list-spinner text-6xl" />
    </div>
    <div data-list-content tabindex="-1">
        @php($collectionPresets = $showPresets ? app(\App\Services\SiteListControls::class)->presets() : [])
        @if($collectionPresets)
            @isset($presetActions)
                <div class="mt-4 flex min-w-0 flex-col gap-3 lg:flex-row lg:items-stretch lg:justify-between lg:gap-0 lg:border-b lg:border-slate-300">
                    <x-ui.preset-views :items="$collectionPresets" class="min-w-0 lg:flex-1 lg:border-b-0!" />
                    <div class="shrink-0 lg:flex lg:items-center lg:pl-6">{{ $presetActions }}</div>
                </div>
            @else
                <x-ui.preset-views :items="$collectionPresets" class="mt-4" />
            @endisset
        @endif
        {{ $slot }}
    </div>
</section>
