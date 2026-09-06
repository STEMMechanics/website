<x-layout>
    <x-mast>Posts</x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-post">
        <div class="my-4 flex flex-wrap items-center gap-3">

                <x-ui.button href="{{ route('admin.post.create') }}">Create Post</x-ui.button>




        </div>
        <x-ui.collection-controls class="my-5" />

        @if($posts->isEmpty())
            <x-none-found item="posts" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Title" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Created" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Author" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach ($posts as $post)
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $post->title }}</div>
                                <div class="md:hidden text-xs text-gray-500 whitespace-normal">{{ ucwords($post->status) }} - <x-ui.date-time>{{ \Carbon\Carbon::parse($post->created_at)->format('M j Y, g:i a') }}</x-ui.date-time></div>
                            </td>
                            <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ \Carbon\Carbon::parse($post->created_at)->format('M j Y, g:i a') }}</x-ui.date-time></td>
                            <td class="hidden md:table-cell text-center!">{{ ucwords($post->status) }}</td>
                            <td class="hidden lg:table-cell">{{ $post->author->getName() }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.post.edit', $post) }}" />
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete post?', 'Are you sure you want to delete this post? This action cannot be undone', '{{ route('admin.post.destroy', $post) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$posts" />
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
