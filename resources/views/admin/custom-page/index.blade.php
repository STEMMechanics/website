<x-layout>
    <x-mast>Custom Pages
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.custom-page.create') }}">Create Custom Page</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-custom-page">
        <x-ui.collection-controls class="my-5" />

        @if($pages->isEmpty())
            <x-none-found item="custom pages" search="{{ request('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Title" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Path" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" field="is_published" label="Status" />
                    <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Updated" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($pages as $page)
                        <tr>
                            <td>
                                <a href="{{ route('admin.custom-page.edit', $page) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $page->title }}</a>
                            </td>
                            <td class="hidden md:table-cell"><a href="{{ url($page->path) }}" class="font-mono text-primary-color hover:underline">{{ $page->path }}</a></td>
                            <td class="hidden md:table-cell text-center!">{{ $page->is_published ? 'Published' : 'Draft' }}</td>
                            <td class="hidden lg:table-cell text-center!"><x-ui.date-time>{{ $page->updated_at?->format('j M Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td class="text-center!">
                                <x-ui.row-actions class="whitespace-nowrap">
                                    <x-ui.row-action label="View page" icon="fa-solid fa-arrow-up-right-from-square" tone="neutral" href="{{ url($page->path) }}" target="_blank" />
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.custom-page.edit', $page) }}" />
                                    <x-ui.row-action label="Delete page" icon="fa-solid fa-trash" tone="danger"

                                        x-data
                                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete page?', 'Are you sure you want to delete this custom page? This action cannot be undone', '{{ route('admin.custom-page.destroy', $page) }}')"
                                     />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$pages" />
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
