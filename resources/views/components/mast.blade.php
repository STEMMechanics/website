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
    $listDefinition = app(\App\Services\SiteListControls::class)->definition();
    $collectionMast = $listDefinition !== [] || request()->routeIs('admin.analytics.index', 'admin.bas.index');
    $showBreadcrumbs = $collectionMast || $resolvedBackUrl || (request()->routeIs('admin.*') && !request()->routeIs('admin.dashboard'));
    $breadcrumbRootUrl = request()->routeIs('admin.*') ? route('admin.dashboard') : (request()->routeIs('account.*', 'tickets.*') ? route('account.show') : url('/'));
    $breadcrumbRootLabel = request()->routeIs('admin.*') ? 'Dashboard' : (request()->routeIs('account.*', 'tickets.*') ? 'My account' : 'Home');
    $mastLabel = $title ?? trim(strip_tags((string) $slot));
    if (!isset($description) && $collectionMast) {
        $description = $listDefinition['description'] ?? ('Browse, filter and manage '.\Illuminate\Support\Str::lower($mastLabel).'.');
    }

@endphp

<x-container class="bg-primary-color-light text-white pt-10 {{ isset($actions) ? 'pb-5 sm:pb-10' : 'pb-10' }}">
    @isset($breadcrumbs)
        <nav aria-label="Breadcrumb" class="-mt-5 mb-3 text-sm text-white/90">{{ $breadcrumbs }}</nav>
    @else
        @if($showBreadcrumbs)
            <nav aria-label="Breadcrumb" class="-mt-5 mb-3 text-sm text-white/90">
                <a href="{{ $breadcrumbRootUrl }}" class="hover:underline">{{ $breadcrumbRootLabel }}</a>
                @if($resolvedBackUrl && isset($backTitle) && $resolvedBackUrl !== $breadcrumbRootUrl)
                    <span class="mx-2" aria-hidden="true">›</span><a href="{{ $resolvedBackUrl }}" class="hover:underline">{{ $backTitle }}</a>
                @endif
                @isset($backTitleExtra)<span class="ml-2">{!! $backTitleExtra !!}</span>@endisset
            </nav>
        @endif
    @endisset
    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
    <h1 class="font-bold text-4xl">
        @isset($image)
            <img src="{{ $image }}" class="inline w-14 h-auto" alt="" />
        @endisset
        {{ $title ?? $slot }}
    </h1>
    @if(isset($description))
        <div class="text-lg">{{ $description }}</div>
    @endif
    @if(isset($backTitle) && $resolvedBackUrl && !$showBreadcrumbs)
        <div class="flex text-lg">
            <a href="{{ $resolvedBackUrl }}" class="text-lg hover:text-gray-300"><i class="fa-solid fa-angle-left mr-3"></i>{{ $backTitle }}</a>
            @isset($backTitleExtra)
                <div class="pl-2">{!! $backTitleExtra !!}</div>
            @endisset
        </div>
    @endif
    </div>
    @isset($actions)
        <div class="sm:mb-0 sm:-mt-5 sm-mast-actions flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
    </div>
    @isset($tabs)
        <div class="mt-4 {{ isset($actions) ? '-mb-5 sm:-mb-10' : '-mb-10' }} overflow-x-auto">
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
                    <a href="{{ $tab['route'] }}"
                       @if(!empty($tab['external'])) target="_blank" rel="noopener noreferrer" @endif
                       class="shrink-0 rounded-t-md px-4 py-2 {{ $isActive ? 'bg-gray-100 text-primary-color-dark' : 'text-white hover:bg-primary-color-dark' }} transition-colors">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ $tab['title'] }}</span>
                            @if(!empty($tab['external']))
                                <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                                <span class="sr-only">(opens in a new tab)</span>
                            @endif
                            @if(!empty($tab['attention']))
                                <i class="fa-solid fa-circle-exclamation text-amber-600" role="img" aria-label="Allocation needs review"></i>
                            @endif
                            @if(isset($tab['badge']) && (int) $tab['badge'] > 0)
                                <x-ui.badge color="success" aria-label="{{ (int) $tab['badge'] }} unread items" class="min-w-5 justify-center leading-none">{{ number_format((int) $tab['badge']) }}</x-ui.badge>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endisset
</x-container>
