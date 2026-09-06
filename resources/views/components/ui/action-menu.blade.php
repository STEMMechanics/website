@props(['id', 'title', 'color' => null])
<x-ui.button :variant="$color ? 'default' : 'plain'" :color="$color ?? 'primary'" data-open-dialog="{{ $id }}" aria-haspopup="dialog" aria-controls="{{ $id }}" aria-label="Actions for {{ $title }}" :class="$color ? 'px-0! w-8.5 shrink-0' : 'sm-action-trigger'"><span class="inline-flex h-6 items-center justify-center"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></span></x-ui.button>
<x-ui.list-dialog :id="$id" :title="$title" kind="actions">
    <div class="sm-action-items">{{ $slot }}</div>
    <div class="p-3 md:hidden"><x-ui.button color="outline" data-close-dialog class="w-full">Cancel</x-ui.button></div>
</x-ui.list-dialog>
