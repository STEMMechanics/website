@php
    $isPast = request()->routeIs('workshop.past.index');
@endphp

<x-layout
    :title="$isPast ? 'Past Workshops' : 'Workshops'"
    :description="$isPast
        ? 'Explore past STEMMechanics workshops and previous program sessions.'
        : 'Browse upcoming STEMMechanics workshops, event details, and registration options.'"
    :canonical="$isPast ? route('workshop.past.index') : route('workshop.index')"
>
    <x-mast title="Workshops" :tabs="[
        ['title' => 'Upcoming', 'route' => route('workshop.index')],
        ['title' => 'Past', 'route' => route('workshop.past.index')],
    ]"/>
    <section class="bg-gray-100">
        @if($workshops->isEmpty())
            <x-container class="mt-8">
                <x-none-found item="workshops" />
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
