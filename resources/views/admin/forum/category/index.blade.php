@php
    $seedCategories = old('categories');

    if (! is_array($seedCategories)) {
        $seedCategories = $categories->map(fn ($category) => [
            'id' => (string) $category->id,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'description' => (string) ($category->description ?? ''),
            'icon_class' => (string) ($category->icon_class ?? ''),
            'color_hex' => (string) ($category->color_hex ?? ''),
            'read_group_slug' => (string) ($category->read_group_slug ?? ''),
            'write_group_slug' => (string) ($category->write_group_slug ?? ''),
            'is_divider' => $category->isDivider(),
            'topics_count' => (int) ($category->topics_count ?? 0),
            'posts_count' => (int) ($category->posts_count ?? 0),
        ])->values()->all();
    } else {
        $seedCategories = collect($seedCategories)->map(fn ($category) => [
            'id' => (string) ($category['id'] ?? ''),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'description' => (string) ($category['description'] ?? ''),
            'icon_class' => (string) ($category['icon_class'] ?? ''),
            'color_hex' => (string) ($category['color_hex'] ?? ''),
            'read_group_slug' => (string) ($category['read_group_slug'] ?? ''),
            'write_group_slug' => (string) ($category['write_group_slug'] ?? ''),
            'is_divider' => filter_var($category['is_divider'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'topics_count' => 0,
            'posts_count' => 0,
        ])->values()->all();
    }

    $deletedCategoryIds = old('deleted_category_ids', []);
    if (! is_array($deletedCategoryIds)) {
        $deletedCategoryIds = [];
    }

    $forumCategoryColorOptions = [
        '#F59E0B', '#FBBF24', '#F97316', '#FB7185', '#EF4444', '#DC2626',
        '#EC4899', '#D946EF', '#C026D3', '#8B5CF6', '#7C3AED', '#6366F1',
        '#4F46E5', '#3B82F6', '#2563EB', '#0EA5E9', '#0284C7', '#06B6D4',
        '#0891B2', '#14B8A6', '#0D9488', '#10B981', '#059669', '#22C55E',
        '#16A34A', '#84CC16', '#65A30D', '#A3E635', '#EAB308', '#CA8A04',
        '#A16207', '#92400E', '#78716C', '#6B7280', '#475569', '#334155',
        '#1F2937', '#111827', '#0F172A', '#4B5563', '#9CA3AF', '#CBD5E1',
        '#94A3B8', '#38BDF8', '#2DD4BF', '#4ADE80', '#FACC15', '#FDBA74',
    ];

    $forumCategoryIconOptions = [
        'fa-solid fa-comments', 'fa-solid fa-comment-dots', 'fa-solid fa-comment-medical',
        'fa-solid fa-bullhorn', 'fa-solid fa-flask', 'fa-solid fa-vial',
        'fa-solid fa-cube', 'fa-solid fa-cubes', 'fa-solid fa-shapes',
        'fa-solid fa-robot', 'fa-solid fa-microchip', 'fa-solid fa-memory',
        'fa-solid fa-screwdriver-wrench', 'fa-solid fa-wrench', 'fa-solid fa-hammer',
        'fa-solid fa-gears', 'fa-solid fa-gear', 'fa-solid fa-toolbox',
        'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-bug',
        'fa-solid fa-gamepad', 'fa-solid fa-dice', 'fa-solid fa-puzzle-piece',
        'fa-solid fa-satellite-dish', 'fa-solid fa-tower-broadcast', 'fa-solid fa-wifi',
        'fa-solid fa-earth-oceania', 'fa-solid fa-globe', 'fa-solid fa-compass',
        'fa-solid fa-bolt', 'fa-solid fa-fire', 'fa-solid fa-lightbulb',
        'fa-solid fa-rocket', 'fa-solid fa-plane', 'fa-solid fa-paper-plane',
        'fa-solid fa-book', 'fa-solid fa-book-open', 'fa-solid fa-bookmark',
        'fa-solid fa-graduation-cap', 'fa-solid fa-school', 'fa-solid fa-chalkboard-user',
        'fa-solid fa-compass-drafting', 'fa-solid fa-ruler-combined', 'fa-solid fa-pencil-ruler',
        'fa-solid fa-circle-nodes', 'fa-solid fa-diagram-project', 'fa-solid fa-share-nodes',
        'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-cloud',
        'fa-solid fa-atom', 'fa-solid fa-magnet', 'fa-solid fa-wave-square',
        'fa-solid fa-chart-line', 'fa-solid fa-chart-column', 'fa-solid fa-chart-pie',
        'fa-solid fa-trophy', 'fa-solid fa-medal', 'fa-solid fa-award',
        'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-sparkles', 'fa-solid fa-wand-magic',
        'fa-solid fa-people-group', 'fa-solid fa-user-group', 'fa-solid fa-users',
        'fa-solid fa-shield-halved', 'fa-solid fa-lock', 'fa-solid fa-key',
        'fa-solid fa-star', 'fa-solid fa-heart', 'fa-solid fa-gem',
        'fa-solid fa-mountain', 'fa-solid fa-tree', 'fa-solid fa-seedling',
        'fa-solid fa-futbol', 'fa-solid fa-music', 'fa-solid fa-camera',
        'fa-solid fa-image', 'fa-solid fa-video', 'fa-solid fa-headset',
        'fa-solid fa-laptop', 'fa-solid fa-tablet-screen-button', 'fa-solid fa-mobile-screen-button',
        'fa-solid fa-shop', 'fa-solid fa-cart-shopping', 'fa-solid fa-gift',
        'forum-icon-stemcraft',
    ];
@endphp

<x-layout>
    <x-mast>Discussion Categories</x-mast>

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.forum.category.save') }}"
            x-data="{
                categories: @js($seedCategories),
                deletedIds: @js(array_values(array_map('strval', $deletedCategoryIds))),
                groupSuggestions: @js($groupSuggestions),
                colorOptions: @js($forumCategoryColorOptions),
                iconOptions: @js($forumCategoryIconOptions),
                appearanceEditor: {
                    open: false,
                    index: null,
                    color_hex: '',
                    icon_class: 'fa-solid fa-comments',
                },
                submitting: false,
                newBlankCategory() {
                    return {
                        id: '',
                        name: '',
                        slug: '',
                        description: '',
                        icon_class: '',
                        color_hex: '',
                        read_group_slug: '',
                        write_group_slug: '',
                        is_divider: false,
                        topics_count: 0,
                        posts_count: 0,
                    };
                },
                newDivider() {
                    return {
                        id: '',
                        name: 'Divider',
                        slug: '',
                        description: '',
                        icon_class: '',
                        color_hex: '',
                        read_group_slug: '',
                        write_group_slug: '',
                        is_divider: true,
                        topics_count: 0,
                        posts_count: 0,
                    };
                },
                isBlankCategory(category) {
                    return String(category?.name || '').trim() === ''
                        && String(category?.description || '').trim() === ''
                        && String(category?.read_group_slug || '').trim() === ''
                        && String(category?.write_group_slug || '').trim() === ''
                        && !category?.is_divider;
                },
                hasSingleTrailingBlank() {
                    if (this.categories.length === 0) {
                        return false;
                    }
                    const blankCount = this.categories.filter((category) => this.isBlankCategory(category)).length;
                    return blankCount === 1 && this.isBlankCategory(this.categories[this.categories.length - 1]);
                },
                ensureSingleTrailingBlank() {
                    const nonBlank = this.categories.filter((category) => !this.isBlankCategory(category));
                    this.categories = [...nonBlank, this.newBlankCategory()];
                },
                handleRowChange(index) {
                    const isLastRow = index === (this.categories.length - 1);
                    if (isLastRow && !this.isBlankCategory(this.categories[index])) {
                        this.categories.push(this.newBlankCategory());
                        return;
                    }

                    if (!this.hasSingleTrailingBlank()) {
                        this.ensureSingleTrailingBlank();
                    }
                },
                addDivider() {
                    const insertIndex = Math.max(0, this.categories.length - 1);
                    this.categories.splice(insertIndex, 0, this.newDivider());
                },
                removeCategory(index) {
                    const category = this.categories[index];
                    if (category?.id) {
                        const usage = `${category.topics_count || 0} topics and ${category.posts_count || 0} posts`;
                        if (!window.confirm(`Delete this discussion category? This will also remove ${usage}.`)) {
                            return;
                        }
                    }
                    if (category?.id) {
                        this.deletedIds.push(category.id);
                    }
                    this.categories.splice(index, 1);
                    this.ensureSingleTrailingBlank();
                },
                moveUp(index) {
                    if (index <= 0 || this.isBlankCategory(this.categories[index])) {
                        return;
                    }
                    const previous = this.categories[index - 1];
                    this.categories[index - 1] = this.categories[index];
                    this.categories[index] = previous;
                },
                moveDown(index) {
                    const lastRealIndex = this.categories.length - 2;
                    if (index >= lastRealIndex || this.isBlankCategory(this.categories[index])) {
                        return;
                    }
                    const next = this.categories[index + 1];
                    this.categories[index + 1] = this.categories[index];
                    this.categories[index] = next;
                },
                openAppearance(index) {
                    const category = this.categories[index];
                    if (!category || category.is_divider) {
                        return;
                    }

                    this.appearanceEditor = {
                        open: true,
                        index,
                        color_hex: String(category.color_hex || ''),
                        icon_class: String(category.icon_class || 'fa-solid fa-comments'),
                    };
                },
                closeAppearance() {
                    this.appearanceEditor.open = false;
                },
                applyAppearance() {
                    if (this.appearanceEditor.index === null || !this.categories[this.appearanceEditor.index]) {
                        this.closeAppearance();
                        return;
                    }

                    const normalizedColor = String(this.appearanceEditor.color_hex || '').trim().toUpperCase();
                    this.categories[this.appearanceEditor.index].color_hex = normalizedColor;
                    this.categories[this.appearanceEditor.index].icon_class = String(this.appearanceEditor.icon_class || '').trim();
                    this.handleRowChange(this.appearanceEditor.index);
                    this.closeAppearance();
                },
            }"
            x-init="ensureSingleTrailingBlank()"
            x-on:submit="submitting = true"
        >
            @csrf

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Categories</h2>
                        <p class="text-sm text-gray-600">Blank read access is public. Blank write access allows any logged-in user. Use <span class="font-mono">user</span> to require login for read/write without requiring a specific group. New category slugs are generated from the title on first save and do not change later. Divider rows are stored with dash-only slugs and appear as separators on the discussion index.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addDivider()">Add Divider</x-ui.button>
                </div>

                <template x-for="deletedId in deletedIds" :key="`deleted-${deletedId}`">
                    <input type="hidden" name="deleted_category_ids[]" :value="deletedId">
                </template>

                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-left border-b">Category</th>
                                <th class="p-2 text-left border-b hidden lg:table-cell">Description</th>
                                <th class="p-2 text-left border-b hidden md:table-cell">Access</th>
                                <th class="p-2 text-left border-b hidden xl:table-cell">Usage</th>
                                <th class="p-2 text-left border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(category, index) in categories" :key="category.id || `new-${index}`">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top min-w-[20rem]">
                                        <input type="hidden" :name="!isBlankCategory(category) && category.id ? `categories[${index}][id]` : null" :value="category.id">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][name]` : null" :value="category.name">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][description]` : null" :value="category.description">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][icon_class]` : null" :value="category.icon_class">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][color_hex]` : null" :value="category.color_hex">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][read_group_slug]` : null" :value="category.read_group_slug">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][write_group_slug]` : null" :value="category.write_group_slug">
                                        <input type="hidden" :name="!isBlankCategory(category) ? `categories[${index}][is_divider]` : null" :value="category.is_divider ? 1 : 0">

                                        <div class="space-y-3">
                                            <template x-if="category.is_divider">
                                                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3">
                                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Divider</div>
                                                    <x-ui.input
                                                        name="forum_category_divider_name_placeholder"
                                                        label="Divider Title"
                                                        :noLabel="true"
                                                        class="mb-0"
                                                        fieldClasses="mt-0"
                                                        x-model="category.name"
                                                        x-on:input="category.name = $event.target.value; handleRowChange(index)"
                                                        x-on:change="category.name = $event.target.value; handleRowChange(index)"
                                                    />
                                                </div>
                                            </template>
                                            <x-ui.input
                                                name="forum_category_name_placeholder"
                                                label="Name"
                                                :noLabel="true"
                                                class="mb-0"
                                                fieldClasses="mt-0"
                                                x-model="category.name"
                                                x-on:input="category.name = $event.target.value; handleRowChange(index)"
                                                x-on:change="category.name = $event.target.value; handleRowChange(index)"
                                                x-show="!category.is_divider"
                                            />

                                            <div class="text-xs text-gray-500">
                                                <template x-if="category.slug">
                                                    <span>Slug: <span class="font-mono" x-text="category.slug"></span></span>
                                                </template>
                                                <template x-if="!category.slug && !isBlankCategory(category) && !category.is_divider">
                                                    <span>Slug will be generated on first save.</span>
                                                </template>
                                                <template x-if="!category.slug && category.is_divider">
                                                    <span>Divider slug will be generated as a unique dash sequence on first save.</span>
                                                </template>
                                            </div>

                                            <div class="flex items-center gap-3" x-show="!category.is_divider">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 hover:border-primary-color hover:text-primary-color"
                                                    x-on:click="openAppearance(index)"
                                                >
                                                    <span
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white"
                                                        :style="`background:${category.color_hex || '#475569'}`"
                                                    >
                                                        <template x-if="String(category.icon_class || '').includes('forum-icon-stemcraft')">
                                                            <img src="/stemcraft-short-logo.webp" alt="" class="h-4 w-4 object-contain" />
                                                        </template>
                                                        <template x-if="!String(category.icon_class || '').includes('forum-icon-stemcraft')">
                                                            <i :class="category.icon_class || 'fa-solid fa-comments'"></i>
                                                        </template>
                                                    </span>
                                                    <span class="text-left">
                                                        <span class="block font-medium">Appearance</span>
                                                        <span class="block text-xs text-gray-500" x-text="category.color_hex || 'Default colour'"></span>
                                                    </span>
                                                </button>
                                            </div>

                                            <div class="grid gap-3 md:hidden" x-show="!category.is_divider">
                                                <x-ui.input
                                                    type="textarea"
                                                    name="forum_category_description_placeholder_mobile"
                                                    label="Description"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    x-model="category.description"
                                                    x-on:input="category.description = $event.target.value; handleRowChange(index)"
                                                    x-on:change="category.description = $event.target.value; handleRowChange(index)"
                                                />

                                                <x-ui.input
                                                    name="forum_category_read_group_placeholder_mobile"
                                                    label="Read Access"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    placeholder="Public"
                                                    :suggestions="$groupSuggestions"
                                                    x-model="category.read_group_slug"
                                                    x-on:input="category.read_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-on:change="category.read_group_slug = $event.target.value; handleRowChange(index)"
                                                />

                                                <x-ui.input
                                                    name="forum_category_write_group_placeholder_mobile"
                                                    label="Write Access"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    placeholder="Logged in users"
                                                    :suggestions="$groupSuggestions"
                                                    x-model="category.write_group_slug"
                                                    x-on:input="category.write_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-on:change="category.write_group_slug = $event.target.value; handleRowChange(index)"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-2 align-top hidden lg:table-cell min-w-[20rem]">
                                        <x-ui.input
                                            type="textarea"
                                            name="forum_category_description_placeholder"
                                            label="Description"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            x-model="category.description"
                                            x-on:input="category.description = $event.target.value; handleRowChange(index)"
                                            x-on:change="category.description = $event.target.value; handleRowChange(index)"
                                            x-show="!category.is_divider"
                                        />
                                    </td>
                                    <td class="p-2 align-top hidden md:table-cell min-w-[14rem]">
                                        <div class="space-y-3">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Read</label>
                                                <x-ui.input
                                                    name="forum_category_read_group_placeholder"
                                                    label="Read Access"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    placeholder="Public"
                                                    :suggestions="$groupSuggestions"
                                                    x-model="category.read_group_slug"
                                                    x-on:input="category.read_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-on:change="category.read_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-show="!category.is_divider"
                                                />
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Write</label>
                                                <x-ui.input
                                                    name="forum_category_write_group_placeholder"
                                                    label="Write Access"
                                                    :noLabel="true"
                                                    class="mb-0"
                                                    fieldClasses="mt-0"
                                                    placeholder="Logged in users"
                                                    :suggestions="$groupSuggestions"
                                                    x-model="category.write_group_slug"
                                                    x-on:input="category.write_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-on:change="category.write_group_slug = $event.target.value; handleRowChange(index)"
                                                    x-show="!category.is_divider"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-2 align-top hidden xl:table-cell whitespace-nowrap text-sm text-gray-600">
                                        <template x-if="category.is_divider">
                                            <div>-</div>
                                        </template>
                                        <template x-if="!category.is_divider">
                                            <div>
                                                <div x-text="`${category.topics_count || 0} topics`"></div>
                                                <div x-text="`${category.posts_count || 0} posts`"></div>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="p-2 align-middle">
                                        <div class="flex items-center justify-center gap-3">
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveUp(index)" :disabled="index === 0 || isBlankCategory(category)" title="Move up">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveDown(index)" :disabled="index >= (categories.length - 2) || isBlankCategory(category)" title="Move down">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeCategory(index)" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                x-cloak
                x-show="appearanceEditor.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:keydown.escape.window="closeAppearance()"
                x-on:click.self="closeAppearance()"
            >
                <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Category Appearance</h2>
                            <p class="text-sm text-gray-500">Choose a colour and icon for the public discussion category card.</p>
                        </div>
                        <button type="button" class="text-gray-500 hover:text-gray-900" x-on:click="closeAppearance()" title="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="grid min-h-0 flex-1 gap-6 overflow-y-auto px-6 py-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full text-2xl text-white" :style="`background:${appearanceEditor.color_hex || '#475569'}`">
                                    <template x-if="String(appearanceEditor.icon_class || '').includes('forum-icon-stemcraft')">
                                        <img src="/stemcraft-short-logo.webp" alt="" class="h-8 w-8 object-contain" />
                                    </template>
                                    <template x-if="!String(appearanceEditor.icon_class || '').includes('forum-icon-stemcraft')">
                                        <i :class="appearanceEditor.icon_class || 'fa-solid fa-comments'"></i>
                                    </template>
                                </div>
                                <div class="mt-4 text-center text-sm font-medium text-gray-700">Live preview</div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700">Custom Hex</label>
                                <input
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase text-gray-900 focus:border-primary-color focus:outline-none focus:ring-0"
                                    placeholder="#475569"
                                    x-model="appearanceEditor.color_hex"
                                />
                                <p class="mt-2 text-xs text-gray-500">Enter `RRGGBB` or `#RRGGBB`.</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-gray-700">Preset Colours</label>
                                <div class="flex flex-wrap gap-3">
                                    <template x-for="color in colorOptions" :key="color">
                                        <button
                                            type="button"
                                            class="h-10 w-10 rounded-full border-2 border-transparent transition hover:scale-105"
                                            :class="appearanceEditor.color_hex === color ? '!border-gray-900' : ''"
                                            :style="`background:${color}`"
                                            x-on:click="appearanceEditor.color_hex = color"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Icon</label>
                            <div class="flex max-h-[28rem] flex-wrap gap-3 overflow-y-auto rounded-2xl border border-gray-200 p-3 sm:grid-cols-4 lg:grid-cols-5">
                                <template x-for="iconClass in iconOptions" :key="iconClass">
                                    <button
                                        type="button"
                                        class="flex h-16 w-16 items-center justify-center rounded-xl border border-gray-200 text-xl text-gray-600 transition hover:border-primary-color hover:text-primary-color"
                                        :class="appearanceEditor.icon_class === iconClass ? '!border-primary-color bg-primary-color/10 text-primary-color' : ''"
                                        x-on:click="appearanceEditor.icon_class = iconClass"
                                        :title="iconClass"
                                    >
                                        <template x-if="String(iconClass || '').includes('forum-icon-stemcraft')">
                                            <img src="/stemcraft-short-logo.webp" alt="" class="h-6 w-6 object-contain" />
                                        </template>
                                        <template x-if="!String(iconClass || '').includes('forum-icon-stemcraft')">
                                            <i :class="iconClass"></i>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                        <x-ui.button type="button" color="outline" x-on:click="closeAppearance()">Cancel</x-ui.button>
                        <x-ui.button type="button" x-on:click="applyAppearance()">Apply</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting">Save Categories</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>Saving...</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
