<x-container class="bg-primary-color-light text-white py-10">
    <h1 class="font-bold text-4xl">{{ $title ?? $slot }}</h1>
    @if(isset($description))
        <div class="text-lg">{{ $description }}</div>
    @endif
    @if(isset($backRoute) && isset($backTitle))
        <a href="{{ route($backRoute) }}" class="text-lg hover:text-gray-300"><i class="fa-solid fa-angle-left mr-3"></i>{{ $backTitle }}</a>
    @endif
    @isset($tabs)
        <div class="mt-4 -mb-10 flex justify-end">
            @foreach($tabs as $tab)
                <a href="{{ $tab['route'] }}" class="rounded-t-md px-4 py-2 {{ ('/' . request()->path() === parse_url($tab['route'], PHP_URL_PATH) ? 'bg-gray-100 text-primary-color-dark' : 'text-white hover:bg-primary-color-dark') }} transition-colors">{{ $tab['title'] }}</a>
            @endforeach
        </div>
    @endisset
</x-container>
