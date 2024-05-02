<x-layout>
    <x-mast>Locations</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.location.create') }}">Create Location</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($locations->isEmpty())
            <x-none-found item="locations" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Name</th>
                    <th class="hidden md:table-cell">Address</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($locations as $location)
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $location->name }}</div>
                                <div class="md:hidden text-xs text-gray-500 whitespace-normal">{{ $location->address }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ $location->address }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.location.edit', $location) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete location?', 'Are you sure you want to delete this location? This action cannot be undone', '{{ route('admin.location.destroy', $location) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $locations->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
