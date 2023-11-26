<x-layout>
    @include('partials.search')

    <div class="mx-4 justify-start gap-4 space-y-4 sm:grid sm:grid-cols-2 sm:space-y-0">

        @unless (count($workshops) == 0)

            @foreach ($workshops as $workshop)
                <x-workshop-card :workshop="$workshop" />
            @endforeach
        @else
            <p>No workshops found</p>
        @endunless

    </div>

    <div class="mt-6 p-4">
        {{ $workshops->links() }}
    </div>
</x-layout>
