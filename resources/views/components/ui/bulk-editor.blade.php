@props(['id', 'title', 'loaderId', 'list' => null, 'selectionKey' => null, 'selectionField' => null])
<template id="{{ $loaderId }}"><div class="flex min-h-48 items-center justify-center" role="status"><x-ui.loading-indicator class="text-6xl" /><span class="sr-only">Loading bulk editor…</span></div></template>
<x-ui.list-dialog :id="$id" :title="$title" kind="bulk">
    <div data-bulk-editor-content data-bulk-list="{{ $list }}" data-bulk-selection-field="{{ $selectionField }}" data-bulk-selection-key="{{ $selectionKey }}" data-bulk-loader="{{ $loaderId }}"></div>
</x-ui.list-dialog>
