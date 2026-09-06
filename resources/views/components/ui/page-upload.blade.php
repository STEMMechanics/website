@props(['id', 'label' => 'Upload', 'color' => 'secondary'])
<div id="{{ $id }}" data-page-upload>
    <x-ui.button type="button" :color="$color" class="px-4! sm:px-6!" data-upload-browse><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>{{ $label }}</x-ui.button>
    <x-ui.input-control id="{{ $id }}-input" type="file" multiple class="hidden" aria-label="Choose files to upload" />
    <div data-page-drop-overlay hidden class="sm-page-drop-overlay" aria-hidden="true">
        <div><i class="fa-solid fa-cloud-arrow-up text-5xl" aria-hidden="true"></i><strong class="mt-5 block text-2xl">Drop files to upload</strong><span class="mt-2 block">Your files will be added to the media library.</span></div>
    </div>
    <div id="{{ $id }}-status" role="status" class="hidden">
        <div class="flex items-center justify-between gap-3"><span id="{{ $id }}-status-text"></span><span id="{{ $id }}-status-percent"></span></div>
        <div class="mt-2 h-1 overflow-hidden rounded bg-slate-200"><div id="{{ $id }}-status-bar" class="h-full bg-primary-color transition-all" style="width:0%"></div></div>
    </div>
</div>
