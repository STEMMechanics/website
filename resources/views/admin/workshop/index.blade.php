<x-layout>
    <x-mast>Workshops</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.workshop.create') }}">Create</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($workshops->isEmpty())
            <x-none-found item="workshops" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Location</th>
                    <th class="hidden md:table-cell">Starts</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($workshops as $workshop)
                        <tr>
                            <td class="flex items-center">
                                <img src="{{ $workshop->hero->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 mr-3 inline rounded" alt="{{ $workshop->hero->title }}" />
                                <div>
                                    <div class="whitespace-normal">{{ $workshop->title }}</div>
                                    <div class="lg:hidden text-xs text-gray-500">{{ $workshop->location->name }} ({{ ucwords($workshop->status) }})</div>
                                    <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('j/m/Y g:i a') }}</div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">{{ ucwords($workshop->status) }}</td>
                            <td class="hidden lg:table-cell">{{ $workshop->location->name }}</td>
                            <td class="hidden md:table-cell">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                <a href="{{ route('admin.workshop.edit', $workshop) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $workshops->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
