<x-layout>
    <x-mast backRoute="admin.custom-page.index" backTitle="Custom Pages">{{ $editing ? 'Edit' : 'Create' }} Custom Page</x-mast>

    @php
        $aliasesValue = old('aliases', implode(PHP_EOL, $page->aliases ?? []));
        if (is_array($aliasesValue)) {
            $aliasesValue = implode(PHP_EOL, $aliasesValue);
        }
        $heroMediaValue = old('hero_media_name', $page->hero_media_name);
        if (is_array($heroMediaValue)) {
            $heroMediaValue = '';
        }
        $showMastChecked = filter_var(old('show_mast', $page->show_mast), FILTER_VALIDATE_BOOLEAN);
        $pageOptionsOpen = $errors->has('aliases')
            || $errors->has('aliases.*')
            || $errors->has('seo_title')
            || $errors->has('seo_description')
            || $errors->has('seo_noindex');
    @endphp

    <x-container class="mt-4">
        <form method="POST" action="{{ $editing ? route('admin.custom-page.update', $page) : route('admin.custom-page.store') }}">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{{ old('title', $page->title) }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Path" name="path" value="{{ old('path', $page->path) }}" info="Example: /stemcraft/rules. A leading slash is added automatically and a trailing slash is removed automatically." />
            </div>
            <div class="mb-4">
                <x-ui.media label="Hero Image" name="hero_media_name" :value="$heroMediaValue" allow_uploads="true" public_usable_only="true" />
            </div>
            <div class="mb-4">
                <x-ui.checkbox label="Show mast header" name="show_mast" :checked="$showMastChecked" />
            </div>
            <div class="mb-4">
                <x-ui.checkbox label="Published" name="is_published" checked="{{ old('is_published', $page->is_published) }}" />
            </div>
            <details class="mb-4 rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4" @if($pageOptionsOpen) open @endif>
                <summary class="cursor-pointer list-none text-sm font-semibold text-gray-800">
                    <span class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-sliders"></i>
                        Page Options
                    </span>
                </summary>
                <div class="mt-4 border-t border-gray-200 pt-4">
                    <div class="mb-4">
                        <x-ui.input type="textarea" label="Aliases" name="aliases" value="{{ $aliasesValue }}" info="One path per line. Requests to these paths will redirect to the main page URL." />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 mb-4">
                        <x-ui.input label="SEO Title" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" info="Optional. Falls back to the page title." />
                        <x-ui.input label="SEO Description" name="seo_description" value="{{ old('seo_description', $page->seo_description) }}" info="Optional. Used for search and social previews." />
                    </div>
                    <div>
                        <x-ui.checkbox label="Hide from search engines (noindex)" name="seo_noindex" checked="{{ old('seo_noindex', $page->seo_noindex) }}" />
                    </div>
                </div>
            </details>
            <div class="mb-4">
                <x-ui.editor name="content" value="{!! old('content', $page->content) !!}" label="Page Content" />
            </div>

            <div class="flex justify-end gap-4 mt-8">
                @if($editing)
                    <x-ui.button color="outline" href="{{ url($page->path) }}" target="_blank">View Page</x-ui.button>
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete page?', 'Are you sure you want to delete this page? This action cannot be undone', '{{ route('admin.custom-page.destroy', $page) }}')">Delete</x-ui.button>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
