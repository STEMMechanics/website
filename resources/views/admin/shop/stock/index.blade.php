<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products" :tabs="$stockTabs">
        Stock items
        <x-slot:actions>
            <x-ui.button color="mast" href="{{ route('admin.shop.stock.create') }}">Create stock item</x-ui.button>
        </x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-shop-stock">
            <x-ui.preset-views class="mb-4" :items="$statusTabs" label="Stock item status" />
            @if($attentionCount > 0)
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <x-ui.badge color="warning">{{ $attentionCount }} stock {{ $attentionCount === 1 ? 'item needs' : 'items need' }} attention</x-ui.badge>
                </div>
            @endif

            <x-ui.collection-controls class="mb-5" label="Search stock items" />

            @if($stockItems->isEmpty())
                <x-none-found item="stock items" search="{{ request()->get('search') }}" />
            @else
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading field="name" label="Stock item" />
                        <x-ui.list-heading label="Available" />
                        <x-ui.list-heading field="replacement_unit_cost_ex_tax" label="Replacement cost" />
                        <x-ui.list-heading label="Links" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($stockItems as $stockItem)
                            @php
                                $reserved = (float) ($stockItem->active_reserved_quantity ?? 0);
                                $available = $stockItem->tracksInventory() ? max(0, (float) $stockItem->on_hand_quantity - $reserved) : null;
                                $reservationShortage = max(0, $reserved - (float) $stockItem->on_hand_quantity);
                                $needsReorder = $available !== null && (float) $stockItem->reorder_point > 0 && $available < (float) $stockItem->reorder_point;
                                $unitSuffix = strtolower(trim((string) $stockItem->unit)) === 'each' ? '' : ' '.$stockItem->unit;
                                $replacementCost = $stockItem->replacementCost();
                            @endphp
                            <tr>
                                <td data-mobile-primary>
                                    <div class="flex min-w-0 items-center gap-3">
                                        @if($stockItem->image?->thumbnail)
                                            <img src="{{ $stockItem->image->thumbnail }}" alt="" class="h-12 w-12 shrink-0 rounded-lg bg-slate-100 object-cover" loading="lazy" />
                                        @else
                                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400" role="img" aria-label="No stock item image"><i class="fa-regular fa-image" aria-hidden="true"></i></span>
                                        @endif
                                        <div class="min-w-0">
                                            <a class="font-semibold text-gray-900 hover:text-primary-color" href="{{ route('admin.shop.stock.edit', $stockItem) }}">{{ $stockItem->linkLabel() }}</a>
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $stockItem->sku ?: 'No SKU' }} · {{ $stockItem->unit }} · {{ ucfirst($stockItem->status) }}
                                                @if($stockItem->shared_workshop_supply) · Shared workshop supply @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Available">
                                    @if($available === null)
                                        <div class="font-medium text-slate-800">Not tracked</div>
                                    @else
                                        <div class="font-semibold {{ $needsReorder ? 'text-amber-700' : 'text-gray-900' }}">{{ $stockItem->formatQuantity($available) }}{{ $unitSuffix }} available</div>
                                        <div class="mt-1 space-y-1 text-xs text-gray-500">
                                            <div>{{ $stockItem->formatQuantity((float) $stockItem->on_hand_quantity) }} on hand · {{ $stockItem->formatQuantity($reserved) }} reserved{{ $stockItem->shared_workshop_supply ? ' (forecast)' : '' }}</div>
                                            @if($reservationShortage > 0.0005)
                                                <div class="font-medium text-amber-700">Reservations exceed stock by {{ $stockItem->formatQuantity($reservationShortage) }}{{ $unitSuffix }}</div>
                                            @endif
                                            @if($needsReorder)
                                                <div class="font-medium text-amber-700">Below reorder alert threshold ({{ $stockItem->formatQuantity((float) $stockItem->reorder_point) }})</div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Replacement cost">
                                    @if($replacementCost !== null)
                                        <div class="font-medium text-slate-800">${{ number_format($replacementCost, 2, '.', ',') }} / {{ $stockItem->unit }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ match($stockItem->replacement_cost_source) { 'latest_receipt' => 'Latest receipt', 'manual_adjustment' => 'Manual adjustment', 'manual' => 'Manual', default => 'Current cost' } }}</div>
                                    @else
                                        <span class="text-slate-500">Not set</span>
                                    @endif
                                </td>
                                <td data-label="Links" class="text-sm text-slate-600">{{ $stockItem->products_count + $stockItem->variants_count + $stockItem->kit_components_count + $stockItem->used_in_kits_count + $stockItem->pick_list_items_count }} links</td>
                                <td data-mobile-actions class="text-center whitespace-nowrap">
                                    <x-ui.row-actions>
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.stock.edit', $stockItem) }}" />
                                        @if($stockItem->status === \App\Models\StockItem::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('admin.shop.stock.restore', $stockItem) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.row-action label="Restore" icon="fa-solid fa-box-open" tone="neutral" type="submit" aria-label="Restore {{ $stockItem->linkLabel() }}" />
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.shop.stock.archive', $stockItem) }}" x-data x-on:submit.prevent="SM.confirm('Archive?', 'This removes it from active stock lists and selectors while preserving its history.', 'Archive', confirmed => { if (confirmed) $el.submit() })">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.row-action label="Archive" icon="fa-solid fa-box-archive" tone="neutral" type="submit" aria-label="Archive {{ $stockItem->linkLabel() }}" />
                                            </form>
                                        @endif
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>

                <x-ui.list-pagination :paginator="$stockItems" label="stock items" />
            @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
