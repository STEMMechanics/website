<x-layout>
    <x-slot name="title">Workshops</x-slot>
    <x-mast>Workshops</x-mast>
    <section class="bg-gray-100">
        <x-container class="my-4">
            <x-ui.search class="md:hidden" name="search" label="Search" value="{{ request()->get('search') }}" />
            <form class="hidden md:flex gap-4" method="GET" action="{{ request()->url() }}">
                <x-ui.input no-label class="my-0 flex-1" type="text" name="search" label="Keywords" value="{{ request()->get('search') }}"/>
                <x-ui.input no-label class="my-0 flex-1" type="text" name="location" label="Location" value="{{ request()->get('location') }}"/>
                <x-ui.input no-label class="my-0 flex-1" type="text" name="date" label="Date Range" value="{{ request()->get('date') }}"/>
                <x-ui.button type="submit"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
            </form>
        </x-container>

        @if($workshops->isEmpty())
            <x-container class="mt-8">
                <x-none-found item="workshops" search="{{ request()->get('search') }}" />
            </x-container>
        @else
            <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                @foreach ($workshops as $workshop)
                    <x-panel-workshop :workshop="$workshop" />
                @endforeach
            </x-container>
            <x-container>
                {{ $workshops->appends(request()->query())->links() }}
            </x-container>
        @endif
    </section>
</x-layout>
