<x-layout>
    <x-mast>Workshop Templates
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.workshop-template.create') }}">Create Template</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-pick-list-template-index">

        <x-ui.collection-controls class="my-5" />

        @if($templates->isEmpty())
            <x-none-found item="templates" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Name" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Contents" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($templates as $template)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $template->name }}</div>
                                <div class="text-xs text-gray-600 md:hidden">{{ (int) ($template->tasks_count ?? 0) }} tasks · {{ (int) ($template->items_count ?? 0) }} pick-list items</div>
                                @if(trim((string) ($template->description ?? '')) !== '')
                                    <div class="text-xs text-gray-500 mt-1">Notes: {{ $template->description }}</div>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ (int) ($template->tasks_count ?? 0) }} tasks</div>
                                <div class="text-xs text-gray-500">{{ (int) ($template->items_count ?? 0) }} pick-list items · {{ (int) ($template->attachments_count ?? 0) }} attachments</div>
                            </td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.workshop-template.edit', $template) }}" />
                                    <form method="POST" action="{{ route('admin.workshop-template.duplicate', $template) }}" class="inline">
                                        @csrf
                                        <x-ui.row-action label="Duplicate" icon="fa-regular fa-copy" tone="neutral" type="submit" />
                                    </form>
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete template?', 'Are you sure you want to delete this workshop template?', '{{ route('admin.workshop-template.destroy', $template) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$templates" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
