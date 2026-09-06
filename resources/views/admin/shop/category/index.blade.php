<x-layout>
    <x-mast>Product Categories
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.shop.category.create') }}">Create</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="mt-4">
        <x-ui.dynamic-list name="admin-shop-category">
        <x-ui.collection-controls class="my-5" />

        @if($categories->isEmpty())
            <x-none-found item="categories" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="name" label="Category" />
                    <x-ui.list-heading class="hidden md:table-cell text-center" label="Slug" />
                    <x-ui.list-heading class="hidden md:table-cell text-center" label="Products" />
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
                                        <div class="text-xs text-gray-500">Sort order {{ (int) $category->sort_order }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ $category->slug }}</td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ (int) $category->products_count }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions :menu="false">
                                    <form method="POST" action="{{ route('admin.shop.category.move-up', $category) }}" class="inline-flex" data-list-reorder>
                                        @csrf
                                        <x-ui.row-action label="Move up" icon="fa-solid fa-arrow-up" tone="neutral" type="submit" :disabled="(int) $category->id === (int) $firstCategoryId" />
                                    </form>
                                    <form method="POST" action="{{ route('admin.shop.category.move-down', $category) }}" class="inline-flex" data-list-reorder>
                                        @csrf
                                        <x-ui.row-action label="Move down" icon="fa-solid fa-arrow-down" tone="neutral" type="submit" :disabled="(int) $category->id === (int) $lastCategoryId" />
                                    </form>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.category.edit', $category) }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
