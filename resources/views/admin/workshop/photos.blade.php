@php
    $workshopTabs = [
        [
            'title' => 'Details',
            'route' => route('admin.workshop.edit', $workshop),
        ],
        [
            'title' => 'Photos',
            'route' => route('admin.workshop.photos', $workshop),
            'active' => true,
        ],
    ];
    $dateLabel = $workshop->starts_at
        ? $workshop->starts_at->format('D j M Y, g:ia').($workshop->ends_at ? ' – '.$workshop->ends_at->format('g:ia') : '')
        : 'No date set';
    $locationLabel = $workshop->getLocationDisplay();
@endphp

<x-layout title="Workshop Photos - {{ $workshop->title }}">
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops" :tabs="$workshopTabs">Workshop Photos</x-mast>

    <x-container>
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold text-gray-900">{{ $workshop->title }}</div>
            <div class="mt-2 grid gap-1 text-sm text-gray-700">
                <div><span class="font-semibold">Date:</span> {{ $dateLabel }}</div>
                <div><span class="font-semibold">Location:</span> {{ $locationLabel }}</div>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <form
                method="POST"
                action="{{ route('admin.workshop.photos.store', $workshop) }}"
                enctype="multipart/form-data"
                class="space-y-4"
                x-data="{
                    previews: [],
                    uploading: false,
                    uploadIndex: 0,
                    uploadError: '',
                    update(files) {
                        this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                        const today = new Date().toISOString().slice(0, 10);
                        this.previews = Array.from(files || []).map((file, index) => ({
                            index,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            url: URL.createObjectURL(file),
                            title: file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, (char) => char.toUpperCase()),
                            visibility: 'private',
                            photographedAt: today,
                            tags: [],
                            tagDraft: '',
                            caption: '',
                            consentNotes: '',
                        }));
                    },
                    addTag(preview, value = null) {
                        const tag = String(value ?? preview.tagDraft).trim().replace(/,$/, '');
                        if (tag === '') return;
                        if (!preview.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
                            preview.tags.push(tag);
                        }
                        preview.tagDraft = '';
                    },
                    removeTag(preview, index) {
                        preview.tags.splice(index, 1);
                    },
                    remove(index) {
                        const removed = this.previews[index];
                        if (removed) {
                            URL.revokeObjectURL(removed.url);
                        }
                        const transfer = new DataTransfer();
                        Array.from(this.$refs.photosInput.files || []).forEach((file, fileIndex) => {
                            if (fileIndex !== index) {
                                transfer.items.add(file);
                            }
                        });
                        this.$refs.photosInput.files = transfer.files;
                        this.previews = this.previews.filter((preview, previewIndex) => previewIndex !== index).map((preview, nextIndex) => ({
                            ...preview,
                            index: nextIndex,
                        }));
                    },
                    clear() {
                        this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                        this.previews = [];
                        this.$refs.photosInput.value = '';
                    },
                    sizeLabel(bytes) {
                        if (!Number.isFinite(bytes)) return '';
                        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
                        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
                        return bytes + ' B';
                    },
                    async uploadAll() {
                        if (this.uploading || this.previews.length === 0) return;
                        this.uploading = true;
                        this.uploadError = '';
                        this.uploadIndex = 0;
                        const files = Array.from(this.$refs.photosInput.files || []);

                        try {
                            for (let index = 0; index < this.previews.length; index++) {
                                const preview = this.previews[index];
                                const file = files[index];
                                if (!file) continue;
                                const maxUploadSize = window.SM && typeof window.SM.maxUploadSize === 'function' ? window.SM.maxUploadSize() : 0;
                                if (maxUploadSize > 0 && file.size > maxUploadSize) {
                                    const sizeLabel = window.SM && typeof window.SM.bytesToString === 'function' ? window.SM.bytesToString(file.size) : `${file.size} bytes`;
                                    const maxLabel = window.SM && typeof window.SM.bytesToString === 'function' ? window.SM.bytesToString(maxUploadSize) : `${maxUploadSize} bytes`;
                                    throw new Error(`${file.name} is ${sizeLabel}, which exceeds the upload limit of ${maxLabel}.`);
                                }
                                this.uploadIndex = index + 1;

                                const formData = new FormData();
                                formData.append('_token', @js(csrf_token()));
                                formData.append('photos[0]', file);
                                formData.append('photos_meta[0][title]', preview.title || '');
                                formData.append('photos_meta[0][visibility]', preview.visibility || 'private');
                                formData.append('photos_meta[0][photographed_at]', preview.photographedAt || '');
                                formData.append('photos_meta[0][tags]', preview.tags.join(', '));
                                formData.append('photos_meta[0][caption]', preview.caption || '');
                                formData.append('photos_meta[0][consent_notes]', preview.consentNotes || '');

                                const response = await fetch(@js(route('admin.workshop.photos.store', $workshop)), {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: formData,
                                });

                                if (!response.ok) {
                                    let message = 'Upload failed.';
                                    try {
                                        const payload = await response.json();
                                        message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || message;
                                    } catch (error) {
                                        message = response.status === 413 ? 'The selected photo is too large for one upload request.' : message;
                                    }
                                    message = `${file.name}: ${message}`;
                                    throw new Error(message);
                                }
                            }

                            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                            window.location.href = @js(route('admin.workshop.photos', $workshop));
                        } catch (error) {
                            this.uploadError = error.message || 'Upload failed.';
                            this.uploading = false;
                        }
                    },
                }"
                x-on:submit.prevent="uploadAll()"
            >
                @csrf
                <div>
                    <label class="mb-1 block text-sm pl-1" for="photos">Upload Workshop Photos</label>
                    <input
                        id="photos"
                        name="photos[]"
                        type="file"
                        accept="image/*"
                        multiple
                        required
                        class="sr-only"
                        x-ref="photosInput"
                        x-on:change="update($event.target.files)"
                        x-bind:disabled="uploading"
                    >
                    <label
                        for="photos"
                        class="group mt-1 flex w-full cursor-pointer items-center justify-between gap-4 rounded-lg border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-left text-sm transition hover:border-primary-color hover:bg-sky-50"
                        x-on:dragover.prevent="$el.classList.add('ring-2', 'ring-primary-color', 'border-primary-color')"
                        x-on:dragleave.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color')"
                        x-on:drop.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color'); $refs.photosInput.files = $event.dataTransfer.files; update($event.dataTransfer.files)"
                    >
                        <div class="min-w-0 grow">
                            <div class="truncate font-medium text-gray-800" x-text="previews.length ? previews.length + ' photo' + (previews.length === 1 ? '' : 's') + ' selected' : 'Drop photos here or click to browse'"></div>
                            <div class="mt-1 text-xs text-gray-500">Supports multiple JPG, PNG, WebP, or GIF images.</div>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-md border border-primary-color px-3 py-1.5 text-xs font-semibold text-primary-color transition group-hover:bg-primary-color group-hover:text-white">Browse</span>
                    </label>
                    @error('photos') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    @error('photos.*') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror

                    <div x-show="previews.length" x-cloak class="mt-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-gray-700">Selected Photos & Metadata</div>
                            <button type="button" class="text-xs font-medium text-gray-500 hover:text-danger-color disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="uploading" x-on:click.prevent="clear()">Clear photos</button>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <tr>
                                        <th class="px-3 py-3">Photo</th>
                                        <th class="px-3 py-3">Details</th>
                                        <th class="px-3 py-3">Usage</th>
                                        <th class="px-3 py-3">Caption & Notes</th>
                                        <th class="px-3 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(preview, previewIndex) in previews" :key="preview.url">
                                        <tr class="align-top">
                                            <td class="w-32 px-3 py-4">
                                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                                    <img :src="preview.url" :alt="preview.name" class="h-24 w-32 object-cover">
                                                    <div class="space-y-0.5 px-2 py-1 text-[11px]">
                                                        <div class="truncate font-semibold text-gray-800" x-text="preview.name"></div>
                                                        <div class="text-gray-500" x-text="sizeLabel(preview.size)"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="min-w-72 px-3 py-4">
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm pl-1">Title</label>
                                                        <input type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-2.5 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0 disabled:bg-gray-100" x-bind:name="`photos_meta[${preview.index}][title]`" x-model="preview.title" x-bind:disabled="uploading">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm pl-1">Photographed At</label>
                                                        <input type="date" required class="mt-1 block w-full rounded-lg border border-gray-300 px-2.5 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0 disabled:bg-gray-100" x-bind:name="`photos_meta[${preview.index}][photographed_at]`" x-model="preview.photographedAt" x-bind:disabled="uploading">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm pl-1">Tags</label>
                                                        <input type="hidden" x-bind:name="`photos_meta[${preview.index}][tags]`" x-bind:value="preview.tags.join(', ')">
                                                        <div class="mt-1 rounded-lg border border-gray-300 bg-white px-2 py-1.5 focus-within:border-indigo-300">
                                                            <div class="flex min-h-9 flex-wrap items-center gap-1.5">
                                                                <template x-for="(tag, tagIndex) in preview.tags" :key="tag">
                                                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">
                                                                        <span x-text="tag"></span>
                                                                        <button type="button" class="text-sky-600 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="uploading" x-on:click.prevent="removeTag(preview, tagIndex)" aria-label="Remove tag">
                                                                            <i class="fa-solid fa-xmark"></i>
                                                                        </button>
                                                                    </span>
                                                                </template>
                                                                <input
                                                                    type="text"
                                                                    class="w-32 max-w-full border-0 px-1 py-1 text-sm focus:outline-none focus:ring-0 disabled:bg-gray-100"
                                                                    list="upload-photo-tag-options"
                                                                    placeholder="tag one, tag two"
                                                                    x-model="preview.tagDraft"
                                                                    x-on:keydown.enter.prevent="addTag(preview)"
                                                                    x-on:keydown.space.prevent="addTag(preview)"
                                                                    x-on:keydown="if ($event.key === ',') { $event.preventDefault(); addTag(preview); }"
                                                                    x-on:blur="addTag(preview)"
                                                                    x-bind:disabled="uploading"
                                                                >
                                                            </div>
                                                        </div>
                                                        <div class="mt-1 text-xs text-gray-500">Press space, comma, or enter to create a tag.</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="min-w-52 px-3 py-4">
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm pl-1">Visibility</label>
                                                        <select class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0 disabled:bg-gray-100" x-bind:name="`photos_meta[${preview.index}][visibility]`" x-model="preview.visibility" x-bind:disabled="uploading">
                                                            <option value="private">Private</option>
                                                            <option value="public">Public</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="min-w-80 px-3 py-4">
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm pl-1">Caption</label>
                                                        <textarea class="mt-1 block w-full rounded-lg border border-gray-300 px-2.5 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0 disabled:bg-gray-100" rows="3" x-bind:name="`photos_meta[${preview.index}][caption]`" x-model="preview.caption" x-bind:disabled="uploading"></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm pl-1">Consent Notes</label>
                                                        <textarea class="mt-1 block w-full rounded-lg border border-gray-300 px-2.5 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0 disabled:bg-gray-100" rows="3" x-bind:name="`photos_meta[${preview.index}][consent_notes]`" x-model="preview.consentNotes" x-bind:disabled="uploading"></textarea>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-4 text-center">
                                                <button type="button" class="text-red-600 hover:text-red-800 disabled:cursor-not-allowed disabled:opacity-50" title="Delete row" x-bind:disabled="uploading" x-on:click.prevent="remove(previewIndex)">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <datalist id="upload-photo-tag-options">
                            @foreach($tagOptions ?? [] as $tagOption)
                                <option value="{{ $tagOption }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <div x-show="uploadError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="uploadError"></div>
                <div x-show="uploading" x-cloak class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="font-medium">
                            <i class="fa-solid fa-circle-notch animate-spin mr-2"></i>
                            Uploading photos
                        </div>
                        <div class="text-xs" x-text="uploadIndex + ' / ' + previews.length"></div>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded bg-sky-100">
                        <div class="h-2 rounded bg-primary-color transition-all" x-bind:style="`width: ${previews.length ? Math.round((uploadIndex / previews.length) * 100) : 0}%`"></div>
                    </div>
                </div>

                <x-ui.button type="submit" x-bind:disabled="uploading">
                    <span x-show="!uploading">Upload Photos</span>
                    <span x-show="uploading" x-cloak>Uploading...</span>
                </x-ui.button>
            </form>
        </div>

        <x-ui.toolbar>
            <x-slot:right>
                <form method="GET" action="{{ route('admin.workshop.photos', $workshop) }}" class="flex flex-wrap items-center justify-end gap-2">
                    <x-ui.input name="search" label="Search photos" value="{{ request('search') }}" class="mb-0 min-w-64" noLabel="true" />
                    <x-ui.select name="visibility" label="Visibility" class="mb-0 min-w-40" selectClass="min-w-40" noLabel="true">
                        <option value="" @selected(request('visibility') === null || request('visibility') === '')>Any visibility</option>
                        <option value="private" @selected(request('visibility') === 'private')>Private</option>
                        <option value="public" @selected(request('visibility') === 'public')>Public</option>
                    </x-ui.select>
                    <x-ui.button type="submit" color="outline">Filter</x-ui.button>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($photos->isEmpty())
            <x-none-found item="photos" search="{{ request()->get('search') }}" />
        @else
            <form
                method="POST"
                action="{{ route('admin.workshop.photos.bulk-update', $workshop) }}"
                x-data="{ dirty: false, saving: false }"
                x-on:input="dirty = true"
                x-on:change="dirty = true"
                x-on:submit="saving = true"
            >
                @csrf
                @method('PUT')
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-3">Photo</th>
                                <th class="px-3 py-3">Details</th>
                                <th class="px-3 py-3">Usage</th>
                                <th class="px-3 py-3">Caption & Notes</th>
                                <th class="px-3 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($photos as $photo)
                                <tr class="align-top">
                                    <td class="w-32 px-3 py-4">
                                        <a href="{{ route('admin.workshop.photos.media', [$workshop, $photo]) }}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                            <img src="{{ route('admin.workshop.photos.media', [$workshop, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->title }}" class="h-24 w-32 object-cover">
                                        </a>
                                    </td>
                                    <td class="min-w-72 px-3 py-4">
                                        <div class="space-y-3">
                                            <x-ui.input label="Title" name="photos[{{ $photo->name }}][title]" value="{{ $photo->title }}" class="mb-0" />
                                            <x-ui.input label="Photographed At" name="photos[{{ $photo->name }}][photographed_at]" type="date" value="{{ optional($photo->photographed_at)->format('Y-m-d') }}" class="mb-0" />
                                            <x-ui.tags name="photos[{{ $photo->name }}][tags]" value="{{ $photo->tags }}" :options="$tagOptions ?? []" noWrapper="true" />
                                        </div>
                                    </td>
                                    <td class="min-w-52 px-3 py-4">
                                        <x-ui.select label="Visibility" name="photos[{{ $photo->name }}][visibility]" value="{{ in_array($photo->visibility, ['private', 'public'], true) ? $photo->visibility : 'private' }}" class="mb-0">
                                            <option value="private" @selected(! in_array($photo->visibility, ['private', 'public'], true) || $photo->visibility === 'private')>Private</option>
                                            <option value="public" @selected($photo->visibility === 'public')>Public</option>
                                        </x-ui.select>
                                    </td>
                                    <td class="min-w-80 px-3 py-4">
                                        <div class="space-y-3">
                                            <x-ui.input label="Caption" name="photos[{{ $photo->name }}][caption]" type="textarea" value="{{ $photo->caption }}" class="mb-0" />
                                            <x-ui.input label="Notes" name="photos[{{ $photo->name }}][consent_notes]" type="textarea" value="{{ $photo->consent_notes }}" class="mb-0" />
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <div class="flex items-center justify-center gap-3">
                                            <a href="{{ route('admin.media.edit', $photo) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-primary-color-dark" title="Open media editor">
                                                <i class="fa-solid fa-up-right-from-square"></i>
                                            </a>
                                            <a href="{{ route('admin.workshop.photos.media', [$workshop, $photo, 'download' => 1]) }}" class="text-primary-color hover:text-primary-color-dark" title="Download photo">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                            <button
                                                type="button"
                                                class="text-amber-600 hover:text-amber-800"
                                                title="Remove from this workshop only"
                                                x-data
                                                x-on:click.prevent="SM.confirmDelete(
                                                    '{{ csrf_token() }}',
                                                    'Remove photo from workshop?',
                                                    'This will remove the photo from this workshop only. The media item will remain in the media library.',
                                                    '{{ route('admin.workshop.photos.destroy', [$workshop, $photo]) }}',
                                                    'Remove from workshop'
                                                )"
                                            >
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="text-red-600 hover:text-red-800"
                                                title="Permanently delete photo"
                                                x-data
                                                x-on:click.prevent="SM.confirmDelete(
                                                    '{{ csrf_token() }}',
                                                    'Delete photo permanently?',
                                                    'This will remove the photo from the media library and from all workshop associations. This action cannot be undone.',
                                                    '{{ route('admin.workshop.photos.delete', [$workshop, $photo]) }}',
                                                    'Delete permanently'
                                                )"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <x-ui.button color="outline" href="{{ route('admin.workshop.photos.zip', $workshop) }}">Download ZIP</x-ui.button>
                    <x-ui.button type="submit" x-bind:disabled="!dirty || saving">
                        <span x-show="!saving">Save Photo Changes</span>
                        <span x-show="saving" x-cloak>Saving...</span>
                    </x-ui.button>
                </div>
            </form>

            <div class="mt-6">{{ $photos->links() }}</div>
        @endif
    </x-container>
</x-layout>
