<x-layout>
    <x-mast>Store Products
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.shop.product.create') }}">Create</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-shop-product">
        @php
            $selectedFilter = $selectedFilter ?? 'all';
            $baseIndexQuery = request()->except('page', 'filter');
        @endphp
        <x-finance.attention-notice kind="products" :total="$allocationAttentionCount" />
        <x-ui.collection-controls class="mb-5" />

        @if($products->isEmpty())
            <x-none-found item="products" search="{{ request()->get('search') }}" />
        @else
            @php
                $inventorySummaries = $inventorySummaries ?? [];
            @endphp
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Product" />
                    <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Status" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading label="Qty Remaining" />
                    <x-ui.list-heading class="text-center!" label="Price" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                        @if($product->displayCategories()->isNotEmpty())
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @foreach($product->displayCategories()->take(3) as $category)
                                                    <x-product-category-badge :label="$category->name" :icon-class="$category->iconClass()" />
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500">{{ $product->slug }}</div>
                                        @if(in_array($product->id, $allocationAttentionIds, true))
                                            <x-ui.badge class="mt-1" tone="warning" icon="fa-solid fa-circle-exclamation" :href="route('admin.shop.product.edit', $product).'#cost-centre-allocation'">Allocation needs review</x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell text-center!">{{ \App\Models\Product::statusLabel((string) $product->status) }}</td>
                            <td class="hidden md:table-cell text-center!">{{ \App\Models\Product::productTypeLabel((string) $product->product_type) }}</td>
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
                            <td class="text-center!">{{ \App\Models\Product::priceAmountLabel((float) $product->price) }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.product.edit', $product) }}" />
                                    <form method="POST" action="{{ route('admin.shop.product.duplicate', $product) }}">
                                        @csrf
                                        <x-ui.row-action label="Duplicate" icon="fa-solid fa-copy" tone="neutral" type="submit" aria-label="Duplicate {{ $product->title }}" />
                                    </form>
                                    @if($product->status === \App\Models\Product::STATUS_ARCHIVED)
                                        <form method="POST" action="{{ route('admin.shop.product.restore', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.row-action label="Restore as draft" icon="fa-solid fa-box-open" tone="neutral" type="submit" aria-label="Restore {{ $product->title }} as draft" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.shop.product.archive', $product) }}" x-data x-on:submit.prevent="SM.confirm('Archive product?', 'This removes the product from the store while preserving its order history.', 'Archive Product', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.row-action label="Archive" icon="fa-solid fa-box-archive" tone="neutral" type="submit" aria-label="Archive {{ $product->title }}" />
                                        </form>
                                    @endif
                                    @if(! $product->store_order_items_exists)
                                        <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger"
                                            type="button"
                                            aria-label="Delete {{ $product->title }}"
                                            x-data
                                            x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete product?', 'Permanently delete this unused product? This action cannot be undone.', '{{ route('admin.shop.product.destroy', $product) }}')"
                                         />
                                    @endif
                                    @if($product->isActive())
                                        <x-ui.row-action label="View" icon="fa-solid fa-up-right-from-square" tone="neutral" href="{{ route('shop.product.show', $product) }}" />
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <div class="mt-6">
                <x-ui.list-pagination :paginator="$products" />
            </div>
        @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
