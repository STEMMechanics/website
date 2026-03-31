<x-layout>
    <x-mast>Custom Pages</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.custom-page.create') }}">Create Custom Page</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <form method="GET" action="{{ url()->current() }}" class="flex">
                    <input class="bg-white grow px-2.5 py-2.5 text-sm text-gray-900 rounded-l-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-indigo-300" type="text" name="search" placeholder="Search" value="{{ request('search', '') }}" />
                    <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($pages->isEmpty())
            <x-none-found item="custom pages" search="{{ request('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th class="hidden md:table-cell">Path</th>
                    <th class="hidden md:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Updated</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($pages as $page)
                        <tr>
                            <td>
                                <a href="{{ route('admin.custom-page.edit', $page) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $page->title }}</a>
                            </td>
                            <td class="hidden md:table-cell"><a href="{{ url($page->path) }}" class="font-mono text-primary-color hover:underline">{{ $page->path }}</a></td>
                            <td class="hidden md:table-cell">{{ $page->is_published ? 'Published' : 'Draft' }}</td>
                            <td class="hidden lg:table-cell">{{ $page->updated_at?->format('j M Y g:i a') ?? '-' }}</td>
                            <td>
                                <div class="flex justify-center gap-3 whitespace-nowrap">
                                    <a href="{{ url($page->path) }}" target="_blank" class="hover:text-primary-color" title="View page">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <a href="{{ route('admin.custom-page.edit', $page) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a
                                        href="#"
                                        class="hover:text-red-600"
                                        x-data
                                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete page?', 'Are you sure you want to delete this custom page? This action cannot be undone', '{{ route('admin.custom-page.destroy', $page) }}')"
                                        title="Delete page"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $pages->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
