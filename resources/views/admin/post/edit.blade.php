<x-layout>
    <x-mast backRoute="admin.post.index" backTitle="Posts">{{ isset($post) ? 'Edit' : 'Create' }} Post</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.post.' . (isset($post) ? 'update' : 'store'), $post ?? []) }}">
            @isset($post)
                @method('PUT')
            @endisset
            @csrf
            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{!! isset($post) ? $post->title : '' !!}" />
            </div>
            <div class="mb-4">
                <x-ui.media label="Image" name="hero_media_name" value="{{ $post->hero_media_name ?? '' }}" allow_uploads="true" />
            </div>
            <div class="flex flex-col sm:flex-row sm:gap-8">
                <div class="flex-1">
                    <x-ui.select label="Status" id="status" name="status" value="{{ $post->status ?? '' }}">
                        <option value="draft" {{ ($post->status ?? '') == 'draft' ? 'selected' :'' }}>Draft</option>
                        <option value="scheduled" {{ ($post->status ?? '') == 'scheduled' ? 'selected' :'' }}>Scheduled</option>
                        <option value="published" {{ ($post->status ?? '') == 'published' ? 'selected' :'' }}>Published</option>
                    </x-ui.select>
                </div>
                <div class="flex-1">
                    <div class="flex-1">
                        <x-ui.input type="datetime-local" label="Publish Date" name="published_at" id="published_at" value="{{ \App\Helpers::timestampNoSeconds($post->published_at ?? now()) }}" />
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <x-ui.editor
                    name="content"
                    value="{!! isset($post) ? $post->content : '' !!}"
                ></x-ui.editor>
            </div>
            <div class="mb-4">
                <x-ui.gallery
                    label="Gallery"
                    name="gallery"
                    value="{{ isset($post) ? \App\Helpers::arrayToString($post->files('gallery')->orderBy('mediables.created_at')->pluck('name')->toArray()) : '' }}"
                    editor="true"
                ></x-ui.gallery>
            </div>

            <div class="mb-4">
                <x-ui.filelist
                    label="Videos"
                    name="videos"
                    value="{!! isset($post) ? $post->files('videos')->orderBy('name')->get() : '' !!}"
                    editor="true"
                ></x-ui.filelist>
            </div>

            <div class="mb-4">
                <x-ui.filelist
                    label="Files"
                    name="files"
                    value="{!! isset($post) ? $post->files()->orderBy('name')->get() : '' !!}"
                    editor="true"
                ></x-ui.filelist>
            </div>
            <div class="flex justify-end gap-4 mt-8">
                @isset($post)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete post?', 'Are you sure you want to delete this post? This action cannot be undone', '{{ route('admin.post.destroy', $post) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    function updateStatus(event) {
        const statusElement = document.getElementById('status');

        if(event.target.value >= new Date().toISOString().slice(0, 16)) {
            statusElement.value = 'scheduled';
        } else {
            if(statusElement.value === 'scheduled') {
                statusElement.value = 'published';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const publishedAt = document.getElementById('published_at');
        if(publishedAt) {
            publishedAt.addEventListener('change', updateStatus);
        }
    });
</script>
