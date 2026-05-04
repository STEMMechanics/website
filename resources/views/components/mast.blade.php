@php
    $resolvedBackUrl = null;
    if (isset($backUrl) && is_string($backUrl) && trim($backUrl) !== '') {
        $resolvedBackUrl = $backUrl;
    } elseif (isset($backRoute) && is_string($backRoute) && trim($backRoute) !== '') {
        $params = [];
        if (isset($backRouteParams) && is_array($backRouteParams)) {
            $params = $backRouteParams;
        }
        $resolvedBackUrl = route($backRoute, $params);
    }
@endphp

@php
    $currentPath = '/'.trim(request()->path(), '/');
@endphp

<x-container class="bg-primary-color-light text-white py-10">
    <h1 class="font-bold text-4xl">
        @isset($image)
            <img src="{{ $image }}" class="inline w-14 h-auto" alt="" />
        @endisset
        {{ $title ?? $slot }}
    </h1>
    @if(isset($description))
        <div class="text-lg">{{ $description }}</div>
    @endif
    @if(isset($backTitle) && $resolvedBackUrl)
        <a href="{{ $resolvedBackUrl }}" class="text-lg hover:text-gray-300"><i class="fa-solid fa-angle-left mr-3"></i>{{ $backTitle }}</a>
    @endif
    @isset($tabs)
        <div class="mt-4 -mb-10 overflow-x-auto">
            <div class="flex min-w-max justify-start sm:w-full sm:min-w-0 sm:justify-end">
                @foreach($tabs as $tab)
                    @php
                        $tabPath = parse_url((string) ($tab['route'] ?? ''), PHP_URL_PATH) ?: '';
                        $tabPath = '/'.trim($tabPath, '/');
                        $tabMatch = (string) ($tab['match'] ?? 'exact');
                        $isActive = array_key_exists('active', $tab)
                            ? (bool) $tab['active']
                            : ($tabMatch === 'starts_with'
                                ? ($tabPath !== '/' && str_starts_with($currentPath, rtrim($tabPath, '/').'/')) || $currentPath === $tabPath
                                : $currentPath === $tabPath);
                    @endphp
                    <a href="{{ $tab['route'] }}" class="shrink-0 rounded-t-md px-4 py-2 {{ $isActive ? 'bg-gray-100 text-primary-color-dark' : 'text-white hover:bg-primary-color-dark' }} transition-colors">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ $tab['title'] }}</span>
                            @if(isset($tab['badge']) && (int) $tab['badge'] > 0)
                                <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-green-700" aria-label="{{ (int) $tab['badge'] }} unread discussions">{{ number_format((int) $tab['badge']) }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endisset
</x-container>
