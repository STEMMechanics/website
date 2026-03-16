<x-layout>
    <x-mast>Store Products</x-mast>

    <x-container>
        @php
            $selectedFilter = $selectedFilter ?? 'all';
            $baseIndexQuery = request()->except('page', 'filter');
        @endphp
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button type="link" href="{{ route('admin.shop.product.create') }}">Create</x-ui.button>
                <x-ui.button type="link" href="{{ route('admin.shop.settings.edit') }}" color="outline">Store Settings</x-ui.button>
                <x-ui.button type="link" href="{{ route('admin.shop.product.index', $baseIndexQuery) }}" :color="$selectedFilter === 'all' ? 'primary-outline' : 'outline'">All Products</x-ui.button>
                <x-ui.button type="link" href="{{ route('admin.shop.product.index', array_merge($baseIndexQuery, ['filter' => 'actionable'])) }}" :color="$selectedFilter === 'actionable' ? 'primary-outline' : 'outline'">Actionable</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($products->isEmpty())
            <x-none-found item="products" search="{{ request()->get('search') }}" />
        @else
            @php
                $inventorySummaries = $inventorySummaries ?? [];
            @endphp
            <x-ui.table>
                <x-slot:header>
                    <th>Product</th>
                    <th class="hidden lg:table-cell">Status</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th>Qty Remaining</th>
                    <th>Price</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($products as $product)
                        @php
                            $inventorySummary = $inventorySummaries[(int) $product->id] ?? [
                                'available' => null,
                                'awaiting' => 0,
                                'reserved' => 0,
                                'backorder' => 0,
                                'preorder' => 0,
                                'low_stock_threshold' => null,
                                'low_stock' => false,
                                'actionable' => false,
                            ];
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" class="h-12 w-12 rounded object-cover bg-gray-100" />
                                    <div>
                                        <a href="{{ route('admin.shop.product.edit', $product) }}" class="font-semibold text-gray-900 hover:text-primary-color">
                                            {{ $product->title }}
                                        </a>
                                        @if($product->category)
                                            <div class="text-xs text-gray-500">{{ $product->category }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500">{{ $product->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">{{ \App\Models\Product::statusLabel((string) $product->status) }}</td>
                            <td class="hidden md:table-cell">{{ \App\Models\Product::productTypeLabel((string) $product->product_type) }}</td>
                            <td>
                                <div class="font-semibold text-gray-900">
                                    @if($product->isDigital())
                                        Digital
                                    @elseif($inventorySummary['available'] === null)
                                        Not tracked
                                    @else
                                        {{ $inventorySummary['available'] }} available
                                    @endif
                                </div>
                                <div class="space-y-1 text-xs text-gray-500">
                                    @if($product->isDigital())
                                        <div>Instant download</div>
                                    @endif
                                    @if(($inventorySummary['awaiting'] ?? 0) > 0)
                                        <div>{{ $inventorySummary['awaiting'] }} awaiting fulfilment</div>
                                    @endif
                                    @if(($inventorySummary['reserved'] ?? 0) > 0)
                                        <div>{{ $inventorySummary['reserved'] }} reserved now</div>
                                    @endif
                                    @if(($inventorySummary['backorder'] ?? 0) > 0)
                                        <div>{{ $inventorySummary['backorder'] }} backordered</div>
                                    @endif
                                    @if(($inventorySummary['preorder'] ?? 0) > 0)
                                        <div>{{ $inventorySummary['preorder'] }} preordered</div>
                                    @endif
                                    @if($inventorySummary['low_stock'] ?? false)
                                        <div class="font-semibold text-red-600">Low stock alert at {{ $inventorySummary['low_stock_threshold'] }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>{{ \App\Models\Product::priceAmountLabel((float) $product->price) }}</td>
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
