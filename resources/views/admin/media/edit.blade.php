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

            <x-ui.file name="file" onchange="updateTitle" file-name="{{ $medium->name ?? '' }}" file-type="{{ $medium->mime_type ?? '' }}" file-size="{{ $medium->size ?? '' }}" file-url="{{ $medium?->url('sm') ?? '' }}" readonly="{{ isset($medium) }}" />

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
    function updateTitle(file, name) {
        const elem = document.querySelector('input[name="title"]');
        if(elem) {
            if (elem.value === '') {
                elem.value = SM.toTitleCase(name);
            }
        }
    }
</script>
