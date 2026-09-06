<x-layout>
    <x-mast title="Organisations" >
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.organisation.create') }}">Create</x-ui.button>
<x-ui.button color="mast" href="{{ route('admin.workshop.history') }}">Workshop history</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-organisation">
        <x-ui.collection-controls class="my-5" />

        @if($organisations->isEmpty())
            <x-none-found item="organisations" search="{{ $search }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Name" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Parent" />
                    <x-ui.list-heading label="Contacts" />
                    <x-ui.list-heading label="Workshops" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($organisations as $organisation)
                        <tr>
                            <td>{{ $organisation->name }}</td>
                            <td class="hidden md:table-cell text-center!">{{ $organisation->typeLabel() }}</td>
                            <td class="hidden lg:table-cell text-center">{{ $organisation->parent?->name ?? '-' }}</td>
                            <td class="text-center">{{ $organisation->contacts_count }}</td>
                            <td class="text-center">
                                <a class="text-primary-color hover:underline" href="{{ route('admin.workshop.history', ['organisation_id' => $organisation->id, 'include_children' => 1]) }}">
                                    {{ $organisation->workshops_count }}
                                </a>
                            </td>
                            <td class="text-center!">
                                <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.organisation.edit', $organisation) }}" />
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$organisations" />
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
