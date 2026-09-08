<x-layout>
    <x-mast title="Workshop allocations" backRoute="admin.workshop.index" backTitle="Workshops" />
    <x-container>
        @forelse($rows as $row)
            <div class="my-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-white p-4">
                <div><a class="font-semibold text-primary-color underline" href="{{ route('admin.workshop.allocation.edit', $row['workshop']) }}">{{ $row['workshop']->title }}</a><p class="text-sm text-slate-500">{{ $row['workshop']->starts_at->format('j M Y') }}</p></div>
                <x-ui.badge color="warning">{{ $row['status'] }}</x-ui.badge>
            </div>
        @empty
            <p>No workshop allocations need review.</p>
        @endforelse
    </x-container>
</x-layout>
