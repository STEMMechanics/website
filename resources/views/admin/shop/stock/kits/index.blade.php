<x-layout>
    <x-mast title="Kits" description="Each kit has its own recipe and inventory. Duplicate a kit to start a similar recipe." :tabs="$stockTabs">
        <x-slot:actions>
            <x-ui.button color="mast" href="{{ route('admin.shop.stock.kit.create') }}">Create kit</x-ui.button>
        </x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-shop-stock-kits">
            <x-ui.preset-views class="mb-4" :items="$statusTabs" label="Kit status" />
            <x-ui.collection-controls class="mb-5" label="Search kits" />

            @if($kits->isEmpty())
                <x-none-found item="kits" search="{{ request()->get('search') }}" />
            @else
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading field="name" label="Kit" />
                        <x-ui.list-heading label="Ready made" />
                        <x-ui.list-heading class="text-center!" label="Buildable" />
                        <x-ui.list-heading class="text-center!" label="Available" />
                        <x-ui.list-heading label="Recipe" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($kits as $kit)
                            @php
                                $reserved = (float) ($kit->active_reserved_quantity ?? 0);
                                $readyMadeAvailable = max(0, (float) $kit->on_hand_quantity - $reserved);
                                $reservationShortage = max(0, $reserved - (float) $kit->on_hand_quantity);
                                $available = $kit->availableQuantity() ?? 0;
                                $buildable = max(0, $available - $readyMadeAvailable);
                            @endphp
                            <tr>
                                <td data-mobile-primary>
                                    <a class="font-semibold text-gray-900 hover:text-primary-color" href="{{ route('admin.shop.stock.kit.edit', $kit) }}">{{ $kit->linkLabel() }}</a>
                                    <div class="mt-1 text-xs text-gray-500">{{ $kit->sku ?: 'No SKU' }} · {{ ucfirst($kit->status) }}</div>
                                </td>
                                <td data-label="Ready made">
                                    <div class="font-medium text-slate-800">{{ $kit->formatQuantity($readyMadeAvailable) }} available</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $kit->formatQuantity((float) $kit->on_hand_quantity) }} on hand · {{ $kit->formatQuantity($reserved) }} reserved</div>
                                    @if($reservationShortage > 0.0005)
                                        <div class="mt-1 text-xs font-medium text-amber-700">Reservations exceed stock by {{ $kit->formatQuantity($reservationShortage) }}</div>
                                    @endif
                                </td>
                                <td data-label="Buildable" class="text-center! font-medium text-slate-800">{{ $kit->formatQuantity($buildable) }}</td>
                                <td data-label="Available" class="text-center! font-semibold text-gray-900">{{ $kit->formatQuantity($available) }} {{ $kit->unit }}</td>
                                <td data-label="Recipe" class="text-sm text-slate-600">{{ $kit->kit_components_count }} stock {{ $kit->kit_components_count === 1 ? 'item' : 'items' }}</td>
                                <td data-mobile-actions class="text-center whitespace-nowrap">
                                    <x-ui.row-actions>
                                        @if($kit->status === \App\Models\StockItem::STATUS_ACTIVE)
                                            <x-ui.row-action label="Assemble kits" icon="fa-solid fa-hammer" tone="neutral" href="{{ route('admin.shop.stock.kit.edit', ['stockItem' => $kit, 'assemble' => 1]) }}" />
                                        @endif
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.shop.stock.kit.edit', $kit) }}" />
                                        <form method="POST" action="{{ route('admin.shop.stock.kit.duplicate', $kit) }}">
                                            @csrf
                                            <x-ui.row-action label="Duplicate" icon="fa-solid fa-copy" tone="neutral" type="submit" aria-label="Duplicate {{ $kit->linkLabel() }}" />
                                        </form>
                                        @if($kit->status === \App\Models\StockItem::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('admin.shop.stock.kit.restore', $kit) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.row-action label="Restore" icon="fa-solid fa-box-open" tone="neutral" type="submit" aria-label="Restore {{ $kit->linkLabel() }}" />
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.shop.stock.kit.archive', $kit) }}" x-data x-on:submit.prevent="SM.confirm('Archive?', 'This removes it from active stock lists and selectors while preserving its history.', 'Archive', confirmed => { if (confirmed) $el.submit() })">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.row-action label="Archive" icon="fa-solid fa-box-archive" tone="neutral" type="submit" aria-label="Archive {{ $kit->linkLabel() }}" />
                                            </form>
                                        @endif
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
                <x-ui.list-pagination :paginator="$kits" label="kits" />
            @endif
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
