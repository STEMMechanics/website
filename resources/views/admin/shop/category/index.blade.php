<x-layout>
    <x-mast>Product Categories</x-mast>

    <x-container class="mt-4">
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.shop.category.create') }}">Create</x-ui.button>
            </x-slot:left>
        </x-ui.toolbar>

        @if($categories->isEmpty())
            <x-none-found item="categories" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Category</th>
                    <th class="hidden md:table-cell text-center">Slug</th>
                    <th class="hidden md:table-cell text-center">Products</th>
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
                                        <div class="text-xs text-gray-500">Sort order {{ (int) $category->sort_order }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ $category->slug }}</td>
                            <td class="hidden md:table-cell text-center text-gray-600">{{ (int) $category->products_count }}</td>
                            <td>
                                <div class="flex items-center justify-center gap-3">
                                    <form method="POST" action="{{ route('admin.shop.category.move-up', $category) }}" class="inline-flex">
                                        @csrf
                                        <button type="submit" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" @disabled($loop->first) title="Move up">
                                            <i class="fa-solid fa-arrow-up"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.shop.category.move-down', $category) }}" class="inline-flex">
                                        @csrf
                                        <button type="submit" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" @disabled($loop->last) title="Move down">
                                            <i class="fa-solid fa-arrow-down"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.shop.category.edit', $category) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    </x-container>
</x-layout>
