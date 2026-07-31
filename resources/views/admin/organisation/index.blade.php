<x-layout>
    <x-mast title="Organisations" />

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.organisation.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <div class="flex gap-2">
                    <x-ui.button href="{{ route('admin.workshop.history') }}" color="outline">Workshop history</x-ui.button>
                    <x-ui.search name="search" label="Search" />
                </div>
            </x-slot:right>
        </x-ui.toolbar>

        @if($organisations->isEmpty())
            <x-none-found item="organisations" search="{{ $search }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Name</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden lg:table-cell">Parent</th>
                    <th>Contacts</th>
                    <th>Workshops</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($organisations as $organisation)
                        <tr>
                            <td>{{ $organisation->name }}</td>
                            <td class="hidden md:table-cell text-center">{{ $organisation->typeLabel() }}</td>
                            <td class="hidden lg:table-cell text-center">{{ $organisation->parent?->name ?? '-' }}</td>
                            <td class="text-center">{{ $organisation->contacts_count }}</td>
                            <td class="text-center">
                                <a class="text-primary-color hover:underline" href="{{ route('admin.workshop.history', ['organisation_id' => $organisation->id, 'include_children' => 1]) }}">
                                    {{ $organisation->workshops_count }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.organisation.edit', $organisation) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            {{ $organisations->links() }}
        @endif
    </x-container>
</x-layout>
