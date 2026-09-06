<x-layout>
    <x-mast>Workshop Categories
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.workshop-category.create') }}">Create</x-ui.button></x-slot:actions>
    </x-mast>

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
        <x-ui.dynamic-list name="admin-workshop-category-index">
        <x-ui.collection-controls class="my-5" />

        @if($categories->isEmpty())
            <x-none-found item="categories" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="name" label="Category" />
                    <x-ui.list-heading class="hidden md:table-cell text-center" label="Slug" />
                    <x-ui.list-heading class="hidden md:table-cell text-center" label="Workshops" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.workshop-category.edit', $category) }}" />
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger"
                                        type="button"
                                        x-on:click="openDeleteDialog({{ \Illuminate\Support\Js::from(route('admin.workshop-category.destroy', $category)) }}, {{ \Illuminate\Support\Js::from($category->name) }}, {{ \Illuminate\Support\Js::from((int) $category->workshops_count) }})"
                                     />
                                </x-ui.row-actions>
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
            class="fixed inset-0 z-300 flex items-center justify-center bg-gray-900/60 px-4 py-8"
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
    </x-ui.dynamic-list>
</x-container>
</x-layout>
