@php
$password = '';
if(isset($medium) && ($medium->password !== null && $medium->password !== '')) {
    $password = 'yes';
}
$originalFileInfo = collect($mediaFilesInfo ?? [])->firstWhere('variant', '');
$variantFilesInfo = collect($mediaFilesInfo ?? [])->filter(fn ($info) => ($info['variant'] ?? '') !== '')->values();
@endphp

<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">{{ isset($medium) ? 'Edit' : 'Create' }} Media</x-mast>
    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.media.' . ( isset($medium) ? 'update' : 'store'), $medium ?? []) }}" enctype="multipart/form-data">
            @isset($medium)
                @method('PUT')
            @endisset
            @csrf
            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{{ $medium->title ?? '' }}"/>
            </div>

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="text-base font-semibold mb-3">File Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Original Name"
                                name="original_name"
                                value="{{ (string) (($medium->name ?? '') !== '' ? $medium->name : '-') }}"
                                disabled />
                        </div>
                        <x-ui.input label="Type" name="type" value="{{ $medium->file_type }}" disabled />
                        <x-ui.input
                            label="MIME Type"
                            name="mime_type"
                            value="{{ (string) (($medium->mime_type ?? '') !== '' ? $medium->mime_type : '-') }}"
                            disabled />
                        <x-ui.input
                            label="Dimensions"
                            name="dimensions"
                            value="{{ (string) (($originalFileInfo['dimensions'] ?? '') !== '' ? $originalFileInfo['dimensions'] : '-') }}"
                            disabled />
                        <x-ui.input
                            label="File Size"
                            name="file_size"
                            value="{{ (string) (($originalFileInfo['size_human'] ?? '') !== '' ? $originalFileInfo['size_human'] : (isset($medium->size) ? \App\Helpers::bytesToString((int) $medium->size) : '-')) }}"
                            disabled />
                        <div class="md:col-span-2">
                            <x-ui.input label="URL" name="url" value="{{ $medium->url }}" disabled />
                        </div>
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Storage Key"
                                name="storage_key"
                                value="{{ (string) (($originalFileInfo['storage_key'] ?? '') !== '' ? $originalFileInfo['storage_key'] : ($medium->hash ?? '-')) }}"
                                disabled />
                        </div>
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Storage Path"
                                name="storage_path"
                                value="{{ (string) (($originalFileInfo['path'] ?? '') !== '' ? $originalFileInfo['path'] : ($medium->path() ?? '-')) }}"
                                disabled />
                        </div>
                    </div>
                </div>

                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold">Stored Variants</h3>
                        <x-ui.button type="button" color="outline" x-data x-on:click.prevent="confirmRegenerateVariants()">Regenerate Variants</x-ui.button>
                    </div>
                    @if($variantFilesInfo->isNotEmpty())
                        <x-ui.table>
                            <x-slot:header>
                                <th>Variant</th>
                                <th class="text-center">Format</th>
                                <th class="text-center">Dimensions</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Preview</th>
                            </x-slot:header>
                            <x-slot:body>
                                @foreach($variantFilesInfo as $fileInfo)
                                    <tr>
                                        <td class="font-semibold">{{ $fileInfo['label'] }}</td>
                                        <td class="text-center">{{ $fileInfo['format'] ?? '-' }}</td>
                                        <td class="text-center">{{ $fileInfo['dimensions'] ?? '-' }}</td>
                                        <td class="text-center">{{ $fileInfo['size_human'] ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xxs font-semibold {{ $fileInfo['exists'] ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' }}">
                                                {{ $fileInfo['exists'] ? 'Exists' : 'Missing' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if(($fileInfo['url'] ?? '-') !== '-' && ($fileInfo['exists'] ?? false))
                                                <a href="{{ $fileInfo['url'] }}" class="link" target="_blank" rel="noopener noreferrer">Open</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </x-slot:body>
                        </x-ui.table>
                    @else
                        <p class="text-sm text-gray-600">No variant files available.</p>
                    @endif
                </div>
            @endisset

            <div class="mb-4">
                <x-ui.password label="Password" name="password" value="{{ $password }}"/>
            </div>

            <x-ui.file name="file" onchange="updateTitle" value="{{ $medium->name ?? '' }}" readonly="{{ isset($medium) }}" />

            <div class="flex justify-end gap-4 mt-8">
                @isset($medium)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this file? This action cannot be undone', '{{ route('admin.media.destroy', $medium) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    const regenerateVariantsAction = @json(isset($medium) ? route('admin.media.regenerate-variants', $medium) : null);
    const regenerateVariantsCsrf = @json(csrf_token());

    function updateTitle(file, name) {
        const elem = document.querySelector('input[name="title"]');
        if(elem) {
            if (elem.value === '') {
                elem.value = SM.toTitleCase(name);
            }
        }
    }

    function confirmRegenerateVariants() {
        if (!regenerateVariantsAction || !regenerateVariantsCsrf) {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitRegenerateVariants(regenerateVariantsAction, regenerateVariantsCsrf);
            return;
        }

        window.SM.confirm(
            'Regenerate variants',
            'Delete existing variants and regenerate them now? This may take a few minutes.',
            'Regenerate',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitRegenerateVariants(regenerateVariantsAction, regenerateVariantsCsrf);
            }
        );
    }

    function submitRegenerateVariants(action, csrf) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;

        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    }
</script>
