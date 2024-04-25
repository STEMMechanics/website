<x-layout>
    <x-mast>Posts</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.post.create') }}">Create Post</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($posts->isEmpty())
            <x-none-found item="posts" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($posts as $post)
                        <tr>
                            <td>{{ $post->title }}</td>
                            <td>{{ $post->created_at }}</td>
                            <td>{{ ucwords($post->status) }}</td>
                            <td>{{ $post->author->getName() }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.post.edit', $post) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete post?', 'Are you sure you want to delete this post? This action cannot be undone', '{{ route('admin.post.destroy', $post) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $posts->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
