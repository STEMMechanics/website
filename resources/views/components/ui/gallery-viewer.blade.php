@props([
    'items' => [],
    'downloads' => false,
    'viewerId' => null,
    'visibleLimit' => null,
])

@php
    $viewerId = $viewerId ?: 'gallery-viewer-'.\Illuminate\Support\Str::uuid();
@endphp

<div
    id="{{ $viewerId }}"
    x-data="{
        gallery: @js($items),
        thumbnailLimit: @js($visibleLimit),
        modelIndex: -1,
        openGallery(index = 0) {
            if (this.gallery.length) this.modelIndex = Math.max(0, Math.min(index, this.gallery.length - 1));
        },
        closeGallery() { this.modelIndex = -1; },
        thumbnailItems() {
            const limit = Number(this.thumbnailLimit);
            return Number.isInteger(limit) && limit > 0 ? this.gallery.slice(0, limit) : this.gallery;
        },
        remainingThumbnails() {
            const limit = Number(this.thumbnailLimit);
            return Number.isInteger(limit) && limit > 0 ? Math.max(0, this.gallery.length - limit) : 0;
        },
        previousImage() {
            if (this.gallery.length > 1) this.modelIndex = this.modelIndex <= 0 ? this.gallery.length - 1 : this.modelIndex - 1;
        },
        nextImage() {
            if (this.gallery.length > 1) this.modelIndex = this.modelIndex >= this.gallery.length - 1 ? 0 : this.modelIndex + 1;
        }
    }"
    x-on:workshop-gallery-open.window="if ($event.detail?.id === @js($viewerId)) openGallery($event.detail?.index ?? 0)"
    x-on:keydown.escape.window="if (modelIndex !== -1) closeGallery()"
    x-on:keydown.left.window="if (modelIndex !== -1) previousImage()"
    x-on:keydown.right.window="if (modelIndex !== -1) nextImage()"
>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
        x-cloak
        x-show="modelIndex !== -1"
        x-transition.opacity
        role="dialog"
        aria-modal="true"
        aria-label="Photo gallery"
        x-on:click.self="closeGallery()"
    >
        <x-ui.button
            variant="plain"
            type="button"
            aria-label="Previous image"
            class="fixed left-0 top-0 z-10 flex h-full w-12 items-center justify-center text-3xl text-gray-400/60 hover:scale-125 hover:text-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
            x-bind:disabled="gallery.length < 2"
            x-on:click.stop="previousImage()"
        >
            <i class="fa-solid fa-circle-chevron-left" aria-hidden="true"></i>
        </x-ui.button>

        <x-ui.button
            variant="plain"
            type="button"
            aria-label="Next image"
            class="fixed right-0 top-0 z-10 flex h-full w-12 items-center justify-center text-3xl text-gray-400/60 hover:scale-125 hover:text-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
            x-bind:disabled="gallery.length < 2"
            x-on:click.stop="nextImage()"
        >
            <i class="fa-solid fa-circle-chevron-right" aria-hidden="true"></i>
        </x-ui.button>

        <template x-for="(file, index) in gallery" :key="file.download_url || file.url || index">
            <div
                x-show="modelIndex === index"
                class="relative flex max-h-[90vh] max-w-[90vw] items-center justify-center"
                x-on:click.stop
            >
                <template x-if="file.is_video">
                    <video class="max-h-[80vh] max-w-[90vw] rounded object-contain" controls playsinline x-bind:src="file.url"></video>
                </template>
                <template x-if="!file.is_video">
                    <img
                        class="max-h-[80vh] max-w-[90vw] rounded object-contain"
                        x-bind:src="file.url"
                        x-bind:alt="file.alt || file.title || ''"
                    />
                </template>

                <div class="pointer-events-none fixed inset-x-14 top-4 z-10 flex items-start justify-between gap-4">
                    <div class="min-w-0 max-w-xl rounded bg-black/45 px-3 py-2 text-left text-white">
                        <p class="truncate text-sm font-semibold" x-text="file.title || file.alt || ''"></p>
                        <p class="truncate text-xs text-white/80" x-show="file.caption" x-text="file.caption"></p>
                        <p class="mt-1 text-xs text-white/70" x-text="`${index + 1} of ${gallery.length}`"></p>
                    </div>
                    <div class="pointer-events-auto flex shrink-0 items-center gap-2 rounded bg-black/45 px-1 py-1">
                        @if($downloads)
                            <x-ui.button
                                variant="plain"
                                size="icon"
                                aria-label="Download image"
                                x-bind:href="file.download_url || '#'"
                                target="_blank"
                                class="text-2xl text-white/80 hover:text-white"
                                x-on:click.stop
                            >
                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                            </x-ui.button>
                        @endif
                        <x-ui.button
                            variant="plain"
                            size="icon"
                            type="button"
                            aria-label="Close image gallery"
                            class="text-3xl text-white/80 hover:text-white"
                            x-on:click.prevent.stop="closeGallery()"
                        >
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <ul class="mt-2 flex flex-wrap justify-center gap-2">
        <template x-for="(file, index) in thumbnailItems()" :key="file.download_url || file.url || index">
            <li class="relative flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100 transition-transform hover:scale-105">
                <button
                    type="button"
                    class="h-full w-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                    x-on:click="openGallery(index)"
                    x-bind:aria-label="`Open ${file.title || file.alt || 'image'} in the photo gallery`"
                >
                    <img class="h-full w-full object-cover" x-bind:src="file.thumbnail_url || file.url" x-bind:alt="file.alt || file.title || ''" loading="lazy" />
                    <template x-if="file.is_video">
                        <span class="absolute inset-0 flex items-center justify-center bg-black/30 text-white" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                    </template>
                </button>
            </li>
        </template>
        <template x-if="remainingThumbnails() > 0">
            <li class="relative flex h-14 w-14 items-center justify-center overflow-hidden rounded border border-gray-200 bg-slate-100">
                <button
                    type="button"
                    class="flex h-full w-full flex-col items-center justify-center text-xs font-semibold leading-tight text-slate-700 transition hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                    x-on:click="openGallery(thumbnailLimit)"
                    x-bind:aria-label="`Open ${remainingThumbnails()} more photos in the gallery`"
                >
                    <span x-text="`+${remainingThumbnails()}`"></span>
                    <span class="text-[10px] font-normal">more</span>
                </button>
            </li>
        </template>
    </ul>
</div>
