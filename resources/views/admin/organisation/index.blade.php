<x-layout>
    <x-mast title="Organisations" description="Manage organisation profiles, contacts, and shared details.">
        <x-slot:actions>
            <x-ui.button color="mast" href="{{ route('admin.organisation.create') }}">Create organisation</x-ui.button>
            <x-ui.button color="mast" href="{{ route('admin.workshop.history') }}">Workshop history</x-ui.button>
        </x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-organisation">
            <x-ui.collection-controls class="my-5" />

            @if($organisations->isEmpty())
                <x-none-found item="organisations" :search="$search" />
            @else
                <x-ui.table variant="listing" mobileCards>
                    <thead>
                        <tr>
                            <x-ui.list-heading label="Name" field="name" />
                            <x-ui.list-heading label="Type" field="type" />
                            <x-ui.list-heading class="hidden lg:table-cell" label="Parent" />
                            <x-ui.list-heading label="Contacts" class="text-center" />
                            <x-ui.list-heading label="Workshops" class="text-center" />
                            <th class="text-center whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-list-results>
                        @foreach($organisations as $organisation)
                            <tr>
                                <td data-mobile-primary>
                                    <a href="{{ route('admin.organisation.edit', $organisation) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                                        {{ $organisation->name }}
                                    </a>
                                </td>
                                <td data-label="Type" class="text-center">
                                    <x-ui.badge color="slate">{{ $organisation->typeLabel() }}</x-ui.badge>
                                </td>
                                <td data-label="Parent" class="hidden lg:table-cell">
                                    @if($organisation->parent)
                                        <a href="{{ route('admin.organisation.edit', $organisation->parent) }}" class="text-primary-color hover:underline">{{ $organisation->parent->name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Contacts" class="text-center tabular-nums">{{ number_format($organisation->contacts_count) }}</td>
                                <td data-label="Workshops" class="text-center tabular-nums">
                                    <a class="text-primary-color hover:underline" href="{{ route('admin.workshop.history', ['organisation_id' => $organisation->id, 'include_children' => 1]) }}">
                                        {{ number_format($organisation->workshops_count) }}
                                    </a>
                                </td>
                                <td data-mobile-actions class="text-center whitespace-nowrap">
                                    <x-ui.row-actions :menu="false">
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.organisation.edit', $organisation) }}" />
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <x-ui.list-pagination :paginator="$organisations" label="organisations" />
            @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
