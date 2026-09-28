<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Create Workshop</x-mast>

    <x-container class="py-5 sm:py-8">
        <div class="mb-6 max-w-3xl">
            <h1 class="text-2xl font-semibold text-gray-900">How would you like to start?</h1>
            <p class="mt-2 text-sm text-gray-600">Start with a blank workshop or choose a blueprint to copy its description, hero image, run sheet, and tasks.</p>
        </div>

        <x-ui.grid class="gap-4 md:grid-cols-2 xl:grid-cols-3">
            <a href="{{ route('admin.workshop.create', ['blank' => 1]) }}" class="group flex min-h-52 flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color">
                <span class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-sky-100 text-xl text-sky-700"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                <span class="text-lg font-semibold text-gray-900">Blank workshop</span>
                <span class="mt-2 text-sm text-gray-600">Build the workshop details and run sheet from scratch.</span>
            </a>

            @forelse($blueprints as $blueprint)
                <a href="{{ route('admin.workshop.create', ['blueprint_id' => $blueprint->id]) }}" class="group flex min-h-52 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color">
                    @if($blueprint->hero?->thumbnail)
                        <img src="{{ $blueprint->hero->thumbnail }}" alt="" class="h-36 w-full object-cover">
                    @else
                        <div class="flex h-36 items-center justify-center bg-gradient-to-br from-sky-100 to-indigo-100 text-4xl text-indigo-500"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></div>
                    @endif
                    <div class="flex flex-1 flex-col p-5">
                        <span class="text-lg font-semibold text-gray-900">{{ $blueprint->name }}</span>
                        @if(filled($blueprint->default_workshop_title) && $blueprint->default_workshop_title !== $blueprint->name)
                            <span class="mt-1 text-sm font-medium text-gray-700">{{ $blueprint->default_workshop_title }}</span>
                        @endif
                        <span class="mt-2 text-sm text-gray-600">{{ $blueprint->duration ?: 'Duration not set' }} · {{ (int) ($blueprint->tasks_count ?? 0) }} tasks · {{ (int) ($blueprint->items_count ?? 0) }} materials</span>
                    </div>
                </a>
            @empty
                <div class="flex min-h-52 flex-col justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600">
                    <p>No blueprints yet.</p>
                    <a href="{{ route('admin.workshop-blueprint.create') }}" class="mt-3 font-semibold text-primary-color hover:underline">Create a workshop blueprint</a>
                </div>
            @endforelse
        </x-ui.grid>
    </x-container>
</x-layout>
