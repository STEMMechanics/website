@php
    $iconSuggestions = [
        'fa-solid fa-tag',
        'fa-solid fa-tags',
        'fa-solid fa-bullhorn',
        'fa-solid fa-flask',
        'fa-solid fa-robot',
        'fa-solid fa-microchip',
        'fa-solid fa-gears',
        'fa-solid fa-book-open',
        'fa-solid fa-graduation-cap',
        'fa-solid fa-chalkboard-user',
        'fa-solid fa-puzzle-piece',
        'fa-solid fa-rocket',
        'fa-solid fa-seedling',
        'fa-solid fa-lightbulb',
        'fa-solid fa-laptop-code',
        'fa-solid fa-atom',
        'fa-solid fa-satellite-dish',
        'fa-solid fa-people-group',
        'fa-solid fa-palette',
        'fa-solid fa-cubes',
        'fa-solid fa-dice-d6',
        'fa-solid fa-wand-magic-sparkles',
        'fa-solid fa-screwdriver-wrench',
        'fa-solid fa-ruler-combined',
        'fa-solid fa-school',
        'fa-solid fa-circle-nodes',
        'fa-solid fa-diagram-project',
        'fa-solid fa-shapes',
        'fa-solid fa-bolt',
        'fa-solid fa-compass',
        'fa-solid fa-map',
    ];
    $initialIconClass = old('icon_class', $category->icon_class ?? 'fa-solid fa-tag');
@endphp

<x-layout>
    <x-mast backRoute="admin.workshop-category.index" backTitle="Workshop Categories">{{ isset($category) ? 'Edit' : 'Create' }} Workshop Category</x-mast>

    <x-container class="mt-4">
        <form
            id="workshop-category-form"
            method="POST"
            action="{{ route('admin.workshop-category.'.(isset($category) ? 'update' : 'store'), $category ?? []) }}"
            x-data="{
                name: @js(old('name', $category->name ?? '')),
                slug: @js(old('slug', $category->slug ?? '')),
                slugTouched: @js(trim((string) old('slug', $category->slug ?? '')) !== ''),
                slugify(value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '')
                        .replace(/-{2,}/g, '-');
                },
                syncSlugFromName() {
                    if (this.slugTouched || String(this.slug || '').trim() !== '') {
                        return;
                    }

                    this.slug = this.slugify(this.name);
                },
                handleSlugInput() {
                    this.slugTouched = String(this.slug || '').trim() !== '';
                },
            }"
        >
            @isset($category)
                @method('PUT')
            @endisset
            @csrf

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <x-ui.input
                    name="name"
                    label="Name"
                    :value="$category->name ?? ''"
                    x-model="name"
                    x-on:blur="syncSlugFromName()"
                />

                <x-ui.input
                    name="slug"
                    label="Slug"
                    :value="$category->slug ?? ''"
                    x-model="slug"
                    x-on:input="handleSlugInput()"
                />

                <x-ui.checkbox
                    name="hide_in_footer"
                    label="Hide in footer"
                    value="1"
                    :checked="(bool) old('hide_in_footer', $category->hide_in_footer ?? false)"
                    info="Keep this category available for workshop filtering, but do not show it in the footer category column."
                />

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4" x-data="{ iconClass: @js($initialIconClass), iconSuggestions: @js($iconSuggestions) }">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Icon</h3>
                            <p class="text-xs text-gray-500">Pick a suggested icon or type any Font Awesome class.</p>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-gray-600 shadow-sm ring-1 ring-gray-200">
                            <i class="text-lg" :class="iconClass || 'fa-solid fa-tag'"></i>
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm focus-within:border-indigo-300">
                        <input
                            id="icon_class"
                            name="icon_class"
                            type="text"
                            class="w-full border-0 p-0 text-sm text-gray-900 focus:outline-none focus:ring-0"
                            x-model="iconClass"
                            placeholder="fa-solid fa-tag"
                        />
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 justify-center">
                        <template x-for="icon in iconSuggestions" :key="icon">
                            <button
                                type="button"
                                class="h-12 w-12 flex aspect-square items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-color hover:text-primary-color"
                                :class="iconClass === icon ? 'border-primary-color bg-primary-color/5 text-primary-color' : ''"
                                x-on:click="iconClass = icon"
                                :title="icon"
                            >
                                <i class="text-lg" :class="icon"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-6 flex items-center justify-between gap-4">
            @if(isset($category))
                <form
                    method="POST"
                    action="{{ route('admin.workshop-category.destroy', $category) }}"
                    x-data
                    x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete category?', 'Are you sure you want to delete this category? Workshops must be detached first.', $el)"
                >
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" color="danger">Delete</x-ui.button>
                </form>
            @else
                <div></div>
            @endif

            <x-ui.button type="submit" form="workshop-category-form">Save</x-ui.button>
        </div>
    </x-container>
</x-layout>
