<x-layout>
    <x-mast>Locations
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.location.create') }}">Create location</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-location">
        <x-ui.collection-controls class="my-5" />

        @if($locations->isEmpty())
            <x-none-found item="locations" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Name" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Address" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach ($locations as $location)
                        <tr>
                            <td>
                                <a href="{{ route('admin.location.edit', $location) }}" class="whitespace-normal font-semibold text-primary-color hover:text-primary-color-dark hover:underline">{{ $location->name }}</a>
                                <div class="md:hidden text-xs text-gray-500 whitespace-normal">{{ $location->formattedAddress() }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ $location->formattedAddress() }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.location.edit', $location) }}" />
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete location?', 'Are you sure you want to delete this location? This action cannot be undone', '{{ route('admin.location.destroy', $location) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$locations" />
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
