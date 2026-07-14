<x-layout>
    <x-mast>Workshop Categories</x-mast>

    <x-container
        class="mt-4"
        x-data="{
            deleteOpen: false,
            deleteAction: '',
            deleteName: '',
            deleteCount: 0,
            openDeleteDialog(action, name, count) {
                this.deleteAction = action;
                this.deleteName = name;
                this.deleteCount = Number(count || 0);
                this.deleteOpen = true;
                this.$nextTick(() => this.$refs.reassignCategory?.focus());
            },
            closeDeleteDialog() {
                this.deleteOpen = false;
                this.deleteAction = '';
                this.deleteName = '';
                this.deleteCount = 0;
            },
        }"
        x-on:keydown.escape.window="if (deleteOpen) closeDeleteDialog()"
    >
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.workshop-category.create') }}">Create</x-ui.button>
            </x-slot:left>
        </x-ui.toolbar>

        @if($categories->isEmpty())
            <x-none-found item="categories" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Category</th>
                    <th class="hidden md:table-cell text-center">Slug</th>
                    <th class="hidden md:table-cell text-center">Workshops</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($categories as $category)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                                        <i class="{{ $category->iconClass() }}"></i>
                                    </span>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $category->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ $category->slug }}</td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ (int) $category->workshops_count }}</td>
                            <td>
                                <div class="flex items-center justify-center gap-3">
                                    <a href="{{ route('admin.workshop-category.edit', $category) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button
                                        type="button"
                                        class="hover:text-danger-color"
                                        title="Delete"
                                        x-on:click="openDeleteDialog(@js(route('admin.workshop-category.destroy', $category)), @js($category->name), @js((int) $category->workshops_count))"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif

        <div
            x-cloak
            x-show="deleteOpen"
            x-transition.opacity
            class="fixed inset-0 z-[300] flex items-center justify-center bg-gray-900/60 px-4 py-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="workshop-category-delete-title"
        >
            <div class="absolute inset-0" x-on:click="closeDeleteDialog()"></div>
            <form
                method="POST"
                x-bind:action="deleteAction"
                class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl ring-1 ring-black/5"
            >
                @csrf
                @method('DELETE')

                <div class="flex items-start gap-4">
                    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-50 text-danger-color">
                        <i class="fa-solid fa-trash"></i>
                    </span>
                    <div>
                        <h2 id="workshop-category-delete-title" class="text-xl font-bold text-gray-900">Delete category?</h2>
                        <p class="mt-2 text-sm text-gray-600">
                            Delete <span class="font-semibold text-gray-900" x-text="deleteName"></span>.
                            <template x-if="deleteCount > 0">
                                <span>This category is currently assigned to <span x-text="deleteCount"></span> workshop<span x-show="deleteCount !== 1">s</span>.</span>
                            </template>
                        </p>
                    </div>
                </div>

                <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <x-ui.select
                        name="reassign_category_id"
                        label="Move assigned workshops to"
                        info="Leave blank to only remove this category from the workshops."
                        x-ref="reassignCategory"
                    >
                        <option value="">Do not move them</option>
                        @foreach($categories as $category)
                            <option x-bind:disabled="deleteName === @js($category->name)" value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" color="outline" x-on:click="closeDeleteDialog()">Cancel</x-ui.button>
                    <x-ui.button type="submit" color="danger">Delete Category</x-ui.button>
                </div>
            </form>
        </div>
    </x-container>
</x-layout>
