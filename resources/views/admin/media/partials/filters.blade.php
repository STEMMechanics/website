<x-ui.list-dialog id="media-filter-dialog" title="Filter media">
    <form method="GET" action="{{ route('admin.media.index') }}" data-list-form="admin-media-index">
        @foreach(request()->only(['view', 'sort', 'direction', 'per_page']) as $key => $value)
            @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <div class="sm-filter-sections">
            <details open>
                <summary><i class="fa-solid fa-sliders"></i><span>General</span></summary>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.input name="search" label="Search" :value="request('search')" class="mb-0" aria-label="Search" />
                    <x-ui.input name="user_id" label="Owner ID" :value="request('user_id')" class="mb-0" aria-label="Owner ID" />
                    <x-ui.select name="usage" label="Site usage" class="mb-0" aria-label="Site usage"><option value="">Any usage</option><option value="unused" @selected(request('usage') === 'unused')>Unused</option><option value="used" @selected(request('usage') === 'used')>Used on the site</option></x-ui.select>
                    <x-ui.select name="type" label="File type" class="mb-0" aria-label="File type"><option value="">Any type</option>@foreach(\App\Services\MediaListFilters::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</x-ui.select>
                    <x-ui.select name="visibility" label="Visibility" class="mb-0" aria-label="Visibility"><option value="">Any visibility</option>@foreach(['public', 'protected', 'private'] as $visibility)<option @selected(request('visibility') === $visibility) value="{{ $visibility }}">{{ ucfirst($visibility) }}</option>@endforeach</x-ui.select>
                    <x-ui.select name="storage_disk" label="Storage" class="mb-0" aria-label="Storage"><option value="">Any storage</option><option value="media" @selected(request('storage_disk') === 'media')>Primary</option><option value="archive" @selected(request('storage_disk') === 'archive')>Archive</option></x-ui.select>
                </div>
            </details>
            <details @if(request()->filled('mime_type') || request()->filled('name_pattern')) open @endif>
                <summary><i class="fa-solid fa-file"></i><span>Name & MIME</span></summary>
                <div class="space-y-3">
                    <x-ui.input name="name_pattern" label="Title or filename matches" :value="request('name_pattern')" placeholder="*-cats" maxlength="255" class="mb-0" aria-label="Title or filename matches" />
                    <p class="text-sm text-slate-500">Matches the whole title or filename. Use * for any characters and ? for one character; for example, *-cats or *-cats.*.</p>
                    <x-ui.input name="mime_type" label="MIME types" :value="request('mime_type')" placeholder="image/*, application/pdf" maxlength="255" class="mb-0" aria-label="MIME types" />
                    <p class="text-sm text-slate-500">Separate alternatives with commas. All other filters must also match.</p>
                </div>
            </details>
            <details @if(request()->filled('tags_include') || request()->filled('tags_exclude')) open @endif>
                <summary><i class="fa-solid fa-tags"></i><span>Tags</span></summary>
                <div class="grid gap-5">
                    <div><x-ui.tags name="tags_include" label="Must have all tags" :value="request('tags_include')" noWrapper /></div>
                    <div><x-ui.tags name="tags_exclude" label="Must not have any tags" :value="request('tags_exclude')" noWrapper /></div>
                    <p class="text-sm text-slate-500">Matches whole tags, ignoring capitalisation.</p>
                </div>
            </details>
            <details @if(request()->filled('size_min') || request()->filled('size_max')) open @endif>
                <summary><i class="fa-solid fa-hard-drive"></i><span>File size</span></summary>
                <div class="grid grid-cols-2 gap-3"><x-ui.input type="number" min="0" step="any" name="size_min" label="Minimum (MB)" :value="request('size_min')" class="mb-0"  aria-label="Minimum (MB)" /><x-ui.input type="number" min="0" step="any" name="size_max" label="Maximum (MB)" :value="request('size_max')" class="mb-0"  aria-label="Maximum (MB)" /></div>
            </details>
            <details @if(request()->filled('uploaded_from') || request()->filled('uploaded_to')) open @endif>
                <summary><i class="fa-solid fa-calendar"></i><span>Upload date</span></summary>
                <div class="grid grid-cols-2 gap-3"><x-ui.input type="date" name="uploaded_from" label="From" :value="request('uploaded_from')" class="mb-0"  aria-label="From" /><x-ui.input type="date" name="uploaded_to" label="To" :value="request('uploaded_to')" class="mb-0"  aria-label="To" /></div>
            </details>
            <details @if(request()->filled('workshop') || request()->filled('location')) open @endif>
                <summary><i class="fa-solid fa-location-dot"></i><span>Workshop & location</span></summary>
                <div class="grid gap-3"><x-ui.input name="workshop" label="Workshop" :value="request('workshop')" class="mb-0"  aria-label="Workshop" /><x-ui.input name="location" label="Location" :value="request('location')" class="mb-0"  aria-label="Location" /></div>
            </details>
        </div>
        <div class="sm-dialog-footer"><x-ui.button color="outline" type="button" data-clear-filter-fields>Clear all</x-ui.button><x-ui.button type="submit">Apply filters</x-ui.button></div>
    </form>
</x-ui.list-dialog>
<x-ui.list-dialog id="media-sort-dialog" title="Sort media" kind="sort">
    <form method="GET" action="{{ route('admin.media.index') }}" data-list-form="admin-media-index">
        @foreach(request()->except(['sort', 'direction', 'page']) as $key => $value)
            @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <fieldset class="p-5 space-y-3"><legend class="sr-only">Sort by</legend>
            @foreach(\App\Services\MediaListFilters::SORTS as $key => $label)
                <label class="flex items-center gap-3"><x-ui.input-control type="radio" name="sort" value="{{ $key }}" :checked="request('sort', 'created_at') === $key" />{{ $label }}</label>
            @endforeach
        </fieldset>
        <fieldset class="border-t border-slate-200 p-5 space-y-3"><legend class="px-2 text-sm font-semibold">Order</legend>
            <label class="flex items-center gap-3"><x-ui.input-control type="radio" name="direction" value="asc" :checked="request('direction', 'desc') === 'asc'" />Ascending (A–Z, smallest, oldest)</label>
            <label class="flex items-center gap-3"><x-ui.input-control type="radio" name="direction" value="desc" :checked="request('direction', 'desc') === 'desc'" />Descending (Z–A, largest, newest)</label>
        </fieldset>
        <div class="sm-dialog-footer"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit">Apply</x-ui.button></div>
    </form>
</x-ui.list-dialog>
