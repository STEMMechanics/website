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
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden md:table-cell">Size</th>
                    <th class="hidden md:table-cell">Uploaded</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($media as $medium)
                        <tr>
                            <td class="flex items-center">
                                <img src="{{ $medium->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 mr-3 inline rounded" alt="{{ $medium->title }}" />
                                <div>
                                    <div class="whitespace-normal">{{ $medium->title }}{!! $medium->password !== null ? '<i class="fa-solid fa-lock text-xs text-gray-400 ml-0.5 -translate-y-1.5 scale-75"></i>': '' !!}</div>
                                    <div class="md:hidden text-xs text-gray-500">{{ $medium->file_type }}</div>
                                    <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($medium->created_at)->format('j/m/Y') }} - {{ \App\Helpers::bytesToString($medium->size) }}</div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">{{ $medium->file_type }}</td>
                            <td class="hidden md:table-cell">{{ \App\Helpers::bytesToString($medium->size) }}</td>
                            <td class="hidden md:table-cell">{{ \Carbon\Carbon::parse($medium->created_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.media.edit', $medium) }}" title="Edit media item" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" class="hover:text-primary-color" title="Copy media link" x-data x-on:click.prevent="SM.copyToClipboard('{{ $medium->url }}')"><i class="fa-solid fa-link"></i></a>
                                    <a href="{{ $medium->url }}?download" class="hover:text-primary-color" title="Download media"><i class="fa-solid fa-download"></i></a>
                                    <a href="#" class="hover:text-red-600" title="Delete media item" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this media? This action cannot be undone', '{{ route('admin.media.destroy', $medium) }}')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $media->appends(request()->query())->links() }}
        @endif

    </x-container>
</x-layout>
