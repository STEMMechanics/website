<x-container class="bg-primary-color-light text-white py-10">
    <h1 class="font-bold text-4xl">{{ $slot }}</h1>
    @if(isset($backRoute) && isset($backTitle))
        <a href="{{ route($backRoute) }}" class="text-lg hover:text-gray-300"><i class="fa-solid fa-angle-left mr-3"></i>{{ $backTitle }}</a>
    @endif
</x-container>
