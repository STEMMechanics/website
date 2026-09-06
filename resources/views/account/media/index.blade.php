<x-layout title="My Media">
    <x-mast>My Media</x-mast>

    <x-container>
        <x-ui.dynamic-list name="account-media-index">

        <div class="my-4 flex flex-wrap items-center gap-3">

                <div class="my-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                    <i class="fa-solid fa-warning mr-2"></i>All media, even private, should be treated as publicly visible. Do not upload sensitive information or photographs.
                </div>




        </div>
        <x-ui.collection-controls class="my-5" />

        @if($media->isEmpty())
            <x-none-found item="media" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Title" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Size" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Uploaded" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                        <div class="md:hidden text-xs text-gray-500"><x-ui.date-time>{{ \Carbon\Carbon::parse($medium->created_at)->format('j/m/Y') }}</x-ui.date-time> - <x-ui.nonbreaking>{{ \App\Helpers::bytesToString($medium->size) }}</x-ui.nonbreaking></div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-center!">{{ $medium->file_type }}</td>
                            <td class="hidden md:table-cell text-center!"><x-ui.nonbreaking>{{ \App\Helpers::bytesToString($medium->size) }}</x-ui.nonbreaking></td>
                            <td class="hidden md:table-cell text-center!"><x-ui.date-time>{{ \Carbon\Carbon::parse($medium->created_at)->format('M j Y, g:i a') }}</x-ui.date-time></td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Copy media link" icon="fa-solid fa-link" tone="neutral" x-data x-on:click.prevent="SM.copyToClipboard('{{ $medium->url }}')" />
                                    <x-ui.row-action label="Download media" icon="fa-solid fa-download" tone="neutral" href="{{ $medium->url }}?download" />
                                    <x-ui.row-action label="Delete media item" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this media? This action cannot be undone', '{{ route('account.media.destroy', $medium) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$media" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
