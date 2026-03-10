<x-layout>
    <x-mast>Store Products</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.shop.product.create') }}">Create</x-ui.button>
                <x-ui.button type="link" href="{{ route('admin.shop.settings.edit') }}" color="outline">Store Settings</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($products->isEmpty())
            <x-none-found item="products" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Product</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th>Price</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($products as $product)
                        <tr>
                            <td class="flex items-center gap-3">
                                <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" class="h-12 w-12 rounded object-cover bg-gray-100" />
                                <div>
                                    <div>{{ $product->title }}</div>
                                    @if($product->category)
                                        <div class="text-xs text-gray-500">{{ $product->category }}</div>
                                    @endif
                                    <div class="text-xs text-gray-500">{{ $product->slug }}</div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">{{ \App\Models\Product::statusLabel((string) $product->status) }}</td>
                            <td class="hidden md:table-cell">{{ \App\Models\Product::productTypeLabel((string) $product->product_type) }}</td>
                            <td>${{ number_format((float) $product->price, 2) }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.shop.product.edit', $product) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @if($product->isActive())
                                        <a href="{{ route('shop.product.show', $product) }}" class="hover:text-primary-color" title="View"><i class="fa-solid fa-up-right-from-square"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                {{ $products->appends(request()->query())->links() }}
            </div>
        @endif
    </x-container>
</x-layout>
