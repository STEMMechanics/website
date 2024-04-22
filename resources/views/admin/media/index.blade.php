<x-layout>
    <x-mast>Media</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.media.create') }}">Create</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($media->isEmpty())
            <x-none-found item="media" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Title</th>
                    <th>Size</th>
                    <th>Type</th>
                    <th>Uploaded</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($media as $medium)
                        <tr>
                            <td>{{ $medium->title }}</td>
                            <td>{{ \App\Helpers::bytesToString($medium->size) }}</td>
                            <td>{{ $medium->mime_type }}</td>
                            <td>{{ \Carbon\Carbon::parse($medium->created_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.media.edit', $medium) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this media? This action cannot be undone', '{{ route('admin.media.destroy', $medium) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $media->links() }}
        @endif

    </x-container>
</x-layout>
