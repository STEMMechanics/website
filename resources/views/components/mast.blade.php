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
                    <a href="{{ $tab['route'] }}" class="shrink-0 rounded-t-md px-4 py-2 {{ ('/' . request()->path() === parse_url($tab['route'], PHP_URL_PATH) ? 'bg-gray-100 text-primary-color-dark' : 'text-white hover:bg-primary-color-dark') }} transition-colors">{{ $tab['title'] }}</a>
                @endforeach
            </div>
        </div>
    @endisset
</x-container>
