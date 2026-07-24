@php
$password = '';
if(isset($medium) && ($medium->password !== null && $medium->password !== '')) {
    $password = 'yes';
}
$originalFileInfo = collect($mediaFilesInfo ?? [])->firstWhere('variant', '');
$variantFilesInfo = collect($mediaFilesInfo ?? [])->filter(fn ($info) => ($info['variant'] ?? '') !== '')->values();
$selectedWorkshopIds = collect(old('workshop_ids', isset($medium) ? $medium->workshopPhotos->pluck('id')->all() : []))
    ->map(fn ($id) => (string) $id)
    ->all();
$visibilityValue = old('visibility', $medium->visibility ?? 'public');
$visibilityValue = in_array((string) $visibilityValue, ['private', 'public'], true) ? (string) $visibilityValue : 'private';
$publicUsages = collect($mediaUsages ?? [])->filter(fn ($usage) => (bool) ($usage['public'] ?? false));
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
                    <h3 class="mb-3 text-base font-semibold">Preview</h3>
                    <div class="flex justify-center rounded-lg border border-gray-200 bg-gray-100 p-3">
                        <a href="{{ $medium->url }}" target="_blank" rel="noopener noreferrer" class="inline-block overflow-hidden rounded-lg">
                            <img src="{{ $medium->thumbnail ?: $medium->url }}" alt="{{ $medium->title }}" class="max-h-96 max-w-full object-contain">
                        </a>
                    </div>
                </div>
            @endisset

            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-3 text-base font-semibold">Usage</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.select
                        label="Visibility"
                        name="visibility"
                        value="{{ $visibilityValue }}"
                        :info="$publicUsages->isNotEmpty() ? 'Public usage must be removed before this image can be made private.' : null"
                    >
                            <option value="private" @selected($visibilityValue === 'private')>Private</option>
                            <option value="public" @selected($visibilityValue === 'public')>Public</option>
                    </x-ui.select>
                    <x-ui.input label="Photographed At" name="photographed_at" type="date" value="{{ old('photographed_at', isset($medium) ? optional($medium->photographed_at)->format('Y-m-d') : '') }}" />
                    <div class="md:col-span-2">
                        <x-ui.tags name="tags" value="{{ old('tags', $medium->tags ?? '') }}" :options="$tagOptions ?? []" noWrapper="true" />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input label="Caption" name="caption" type="textarea" value="{{ old('caption', $medium->caption ?? '') }}" />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input label="Notes" name="consent_notes" type="textarea" value="{{ old('consent_notes', $medium->consent_notes ?? '') }}" />
                    </div>
                </div>
            </div>

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-base font-semibold">Linked & Used By</h3>
                    @if(empty($mediaUsages))
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500">No current links or usage found.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2">Type</th>
                                        <th class="px-3 py-2">Item</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($mediaUsages as $usage)
                                        <tr>
                                            <td class="px-3 py-2 font-semibold">{{ $usage['type'] }}</td>
                                            <td class="px-3 py-2">
                                                @if(! empty($usage['url']))
                                                    <a href="{{ $usage['url'] }}" class="text-primary-color hover:underline">{{ $usage['label'] }}</a>
                                                @else
                                                    {{ $usage['label'] }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endisset

            <div
                class="mb-6 rounded-lg border border-gray-200 bg-white p-4"
                x-data="{
                    search: '',
                    selected: @js($selectedWorkshopIds),
                    workshops: @js(collect($workshopOptions ?? [])->map(function ($workshopOption) {
                        $locationLabel = $workshopOption->location?->name ?: $workshopOption->getLocationName();
                        $dateLabel = $workshopOption->starts_at ? $workshopOption->starts_at->format('j M Y') : 'No date';
                        return [
                            'id' => (string) $workshopOption->id,
                            'title' => (string) $workshopOption->title,
                            'location' => (string) $locationLabel,
                            'date' => (string) $dateLabel,
                            'label' => (string) ($workshopOption->title.' · '.$locationLabel.' · '.$dateLabel),
                            'search' => strtolower(trim((string) ($workshopOption->title.' '.$locationLabel.' '.$dateLabel))),
                            'edit_url' => route('admin.workshop.edit', $workshopOption),
                        ];
                    })->values()->all()),
                    filtered() {
                        const term = this.search.trim().toLowerCase();
                        if (term.length < 2) return [];
                        return this.workshops
                            .filter((workshop) => workshop.search.includes(term) && !this.isSelected(workshop.id))
                            .slice(0, 25);
                    },
                    isSelected(id) {
                        return this.selected.includes(String(id));
                    },
                    toggle(id) {
                        id = String(id);
                        this.selected = this.isSelected(id)
                            ? this.selected.filter((item) => item !== id)
                            : [...this.selected, id];
                    },
                    remove(id) {
                        id = String(id);
                        this.selected = this.selected.filter((item) => item !== id);
                    },
                    selectedWorkshops() {
                        return this.workshops.filter((workshop) => this.isSelected(workshop.id));
                    },
                }"
            >
                <h3 class="mb-3 text-base font-semibold">Workshop Links</h3>
                <div class="mb-3 flex flex-wrap gap-1.5" x-show="selectedWorkshops().length > 0" x-cloak>
                    <template x-for="workshop in selectedWorkshops()" :key="workshop.id">
                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">
                            <a :href="workshop.edit_url" class="hover:underline" x-text="workshop.label"></a>
                            <button type="button" class="text-sky-600 hover:text-red-600" x-on:click.prevent="remove(workshop.id)" aria-label="Remove workshop link">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </span>
                    </template>
                </div>
                <template x-for="workshopId in selected" :key="`selected-workshop-${workshopId}`">
                    <input type="hidden" name="workshop_ids[]" :value="workshopId">
                </template>

                <label class="mb-1 block text-sm font-semibold text-gray-700" for="workshop_link_search">Find Workshops</label>
                <input id="workshop_link_search" type="search" x-model="search" placeholder="Search workshop title, location, or date" class="mb-3 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring-0">

                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="search.trim().length < 2" x-cloak>
                    Enter at least 2 characters to search workshops.
                </div>

                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50" x-show="search.trim().length >= 2" x-cloak>
                    <template x-for="workshop in filtered()" :key="workshop.id">
                        <label class="flex cursor-pointer items-start gap-3 border-b border-gray-200 bg-white px-3 py-2 text-sm last:border-b-0 hover:bg-sky-50">
                            <input type="checkbox" :checked="isSelected(workshop.id)" x-on:change="toggle(workshop.id)" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                            <span class="min-w-0">
                                <span class="block font-semibold text-gray-900" x-text="workshop.title"></span>
                                <span class="block text-xs text-gray-500" x-text="`${workshop.location} · ${workshop.date}`"></span>
                            </span>
                        </label>
                    </template>
                    <div class="px-3 py-3 text-sm text-gray-500" x-show="filtered().length === 0" x-cloak>No unselected workshops found.</div>
                </div>
                <p class="mt-1 text-xs text-gray-500">Selected workshops appear as pills above. Results are limited to 25 matches.</p>
            </div>

            @if(isset($mediaOwners))
                <div class="mb-4">
                    <x-admin.user-selector-inline
                        :users="$mediaOwners"
                        fieldName="user_id"
                        lookupName="media_owner_lookup"
                        label="Owner"
                        info="Admins can reassign media ownership."
                        :selectedUserId="old('user_id', $medium->user_id ?? '')"
                    />
                </div>
            @endif

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="text-base font-semibold mb-3">File Details</h3>
                    @if(trim((string) ($medium->last_processing_error ?? '')) !== '')
                        <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                            <div class="font-semibold">Last processing error</div>
                            <div class="mt-1 whitespace-pre-line">{{ (string) $medium->last_processing_error }}</div>
                            <div class="mt-1 text-xs text-red-700">
                                @if($medium->last_processing_failed_at)
                                    Failed at: {{ $medium->last_processing_failed_at->format('M j, Y g:i a') }}
                                @else
                                    Failed at: Unknown
                                @endif
                            </div>
                        </div>
                    @endif
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
                        <div class="flex items-center gap-2">
                            @if($variantFilesInfo->isNotEmpty())
                            <x-ui.button type="button" color="outline" x-data x-on:click.prevent="confirmDeleteVariants()">Delete Variants</x-ui.button>
                            @endif
                            <x-ui.button type="button" color="outline" x-data x-on:click.prevent="confirmRegenerateVariants()">Regenerate Variants</x-ui.button>
                        </div>
                    </div>
                    @if($variantFilesInfo->isNotEmpty())
                        <x-ui.table>
                            <x-slot:header>
                                <th>Variant</th>
                                <th class="text-center">Format</th>
                                <th class="text-center">Dimensions</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
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
                                            <div class="flex items-center justify-center gap-3">
                                                @if(($fileInfo['url'] ?? '-') !== '-' && ($fileInfo['exists'] ?? false))
                                                    <a
                                                        href="{{ $fileInfo['url'] }}"
                                                        title="Open variant"
                                                        class="hover:text-primary-color"
                                                        target="_blank"
                                                        rel="noopener noreferrer">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                @else
                                                    <span class="text-gray-400" title="Variant file missing">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </span>
                                                @endif
                                                <a
                                                    href="#"
                                                    title="Delete this variant"
                                                    class="hover:text-red-600"
                                                    x-data
                                                    x-on:click.prevent="confirmDeleteSingleVariant('{{ (string) ($fileInfo['variant'] ?? '') }}')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
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

            @unless(isset($medium))
                <x-ui.file name="file" onchange="updateTitle" value="" />
            @endunless

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
    const deleteVariantAction = @json(isset($medium) ? route('admin.media.delete-variant', $medium) : null);
    const deleteVariantsAction = @json(isset($medium) ? route('admin.media.delete-variants', $medium) : null);
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

    function submitDeleteSingleVariant(action, csrf, variant) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;

        const variantInput = document.createElement('input');
        variantInput.type = 'hidden';
        variantInput.name = 'variant';
        variantInput.value = variant;

        form.appendChild(token);
        form.appendChild(variantInput);
        document.body.appendChild(form);
        form.submit();
    }

    function confirmDeleteVariants() {
        if (!deleteVariantsAction || !regenerateVariantsCsrf) {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitRegenerateVariants(deleteVariantsAction, regenerateVariantsCsrf);
            return;
        }

        window.SM.confirm(
            'Delete variants',
            'Delete all generated variants for this media item? This does not delete the original file.',
            'Delete Variants',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitRegenerateVariants(deleteVariantsAction, regenerateVariantsCsrf);
            }
        );
    }

    function confirmDeleteSingleVariant(variant) {
        const variantName = String(variant || '').trim();
        if (!deleteVariantAction || !regenerateVariantsCsrf || variantName === '') {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitDeleteSingleVariant(deleteVariantAction, regenerateVariantsCsrf, variantName);
            return;
        }

        window.SM.confirm(
            'Delete variant',
            `Delete the "${variantName}" variant? This does not delete the original file.`,
            'Delete Variant',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitDeleteSingleVariant(deleteVariantAction, regenerateVariantsCsrf, variantName);
            }
        );
    }
</script>
