<x-layout title="My Media">
    <x-mast>My Media</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="my-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                    <i class="fa-solid fa-warning mr-2"></i>All media, even private, should be treated as publicly visible. Do not upload sensitive information or photographs.
                </div>
            </x-slot:left>
            <x-slot:right class="flex-0">
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

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
                            <td>
                                <div class="flex items-center">
                                    <div class="relative mr-3 shrink-0">
                                        <img src="{{ $medium->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 inline rounded" alt="{{ $medium->title }}" {{ in_array($medium->status, ['processing', 'queued'], true) ? 'data-thumbnail=' . $medium->name : '' }} />
                                    </div>
                                    <div>
                                        <div class="whitespace-normal">{{ $medium->title }}{!! $medium->password !== null ? '<i class="fa-solid fa-lock text-xs text-gray-400 ml-0.5 -translate-y-1.5 scale-75"></i>': '' !!}</div>
                                        <div class="md:hidden text-xs text-gray-500">{{ $medium->file_type }}</div>
                                        <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($medium->created_at)->format('j/m/Y') }} - {{ \App\Helpers::bytesToString($medium->size) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">{{ $medium->file_type }}</td>
                            <td class="hidden md:table-cell">{{ \App\Helpers::bytesToString($medium->size) }}</td>
                            <td class="hidden md:table-cell">{{ \Carbon\Carbon::parse($medium->created_at)->format('M j Y, g:i a') }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="#" class="hover:text-primary-color" title="Copy media link" x-data x-on:click.prevent="SM.copyToClipboard('{{ $medium->url }}')"><i class="fa-solid fa-link"></i></a>
                                    <a href="{{ $medium->url }}?download" class="hover:text-primary-color" title="Download media"><i class="fa-solid fa-download"></i></a>
                                    <a href="#" class="hover:text-red-600" title="Delete media item" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this media? This action cannot be undone', '{{ route('account.media.destroy', $medium) }}')"><i class="fa-solid fa-trash"></i></a>
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
