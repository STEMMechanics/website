@props(['workshops'])

<x-layout>
    @include('partials.hero')

    <h2>Upcoming Workshops</h2>
    <div class="mx-4 justify-start gap-4 space-y-4 sm:grid sm:grid-cols-2 sm:space-y-0">
        @unless (count($workshops) == 0)
            @foreach ($workshops as $workshop)
                <x-workshop-card :workshop="$workshop" />
            @endforeach
        @else
            <p>No workshops found</p>
        @endunless
    </div>


</x-layout>
