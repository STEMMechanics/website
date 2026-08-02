<x-layout>
    <x-mast>Workshop Templates</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.workshop-template.create') }}">Create Template</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($templates->isEmpty())
            <x-none-found item="templates" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Name</th>
                    <th class="hidden md:table-cell">Contents</th>
                    <th>Actions</th>
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
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.workshop-template.edit', $template) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <form method="POST" action="{{ route('admin.workshop-template.duplicate', $template) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="hover:text-primary-color" title="Duplicate"><i class="fa-regular fa-copy"></i></button>
                                    </form>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete template?', 'Are you sure you want to delete this workshop template?', '{{ route('admin.workshop-template.destroy', $template) }}')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $templates->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
