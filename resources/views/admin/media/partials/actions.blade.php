<x-ui.action-menu :id="'media-actions-'.md5($medium->name)" :title="$medium->title">
    <x-ui.button variant="plain" data-edit-media="{{ json_encode(['title' => $medium->title, 'caption' => $medium->caption, 'tags' => $medium->tags, 'visibility' => in_array($medium->visibility, ['public', 'protected']) ? $medium->visibility : 'private', 'url' => route('admin.media.quick-update', $medium)]) }}"><i class="fa-solid fa-pen"></i>Edit details</x-ui.button>
    <a href="{{ route('admin.media.edit', $medium) }}"><i class="fa-solid fa-sliders"></i>Full editor</a>
    <x-ui.button variant="plain" data-copy-media="{{ $medium->url }}"><i class="fa-solid fa-link"></i>Copy link</x-ui.button>
    <a href="{{ $medium->url }}?download" download><i class="fa-solid fa-download"></i>Download</a>
    <x-ui.button variant="plain" class="text-red-600!" data-delete-media="{{ route('admin.media.destroy', $medium) }}" data-media-name="{{ $medium->name }}"><i class="fa-solid fa-trash"></i>Delete</x-ui.button>
</x-ui.action-menu>
