@if($workshop->stock_reconciled_at)
            <section class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="font-semibold text-emerald-950">Workshop stock reconciled</h2>
                <p class="mt-1 text-sm text-emerald-900">
                    Recorded {{ $workshop->stock_reconciled_at->format('j M Y, g:ia') }}
                    @if($workshop->stockReconciledBy)
                        by {{ $workshop->stockReconciledBy->getName() }}
                    @endif
                    . Recorded stock has been deducted and unused reservations returned to availability.
                </p>
                @if($workshopStockUsage->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-sm text-emerald-950">
                        @foreach($workshopStockUsage as $usageRow)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span>{{ $usageRow['stock_item']->linkLabel() }}: {{ $usageRow['stock_item']->formatQuantity((float) $usageRow['used_quantity']) }} {{ $usageRow['stock_item']->unit }} used</span>
                                @if($usageRow['stock_item']->is_kit && (float) $usageRow['used_quantity'] > 0)
                                    <x-ui.button
                                        type="button"
                                        color="primary-outline"
                                        size="compact"
                                        data-assembly-url="{{ route('admin.shop.stock.kit.assemble.preview', ['stockItem' => $usageRow['stock_item'], 'quantity' => 1, 'workshop_return_id' => (string) $workshop->getKey(), 'workshop_return_to' => 'stock-reconciliation']) }}"
                                        x-on:click="$dispatch('open-workshop-assembly', { url: $el.dataset.assemblyUrl })"
                                    >Assemble another</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-emerald-900">No stock was recorded as used.</p>
                @endif
            </section>
        @elseif($canReconcileWorkshopStock)
            @php
                $listedStockIds = $workshopPlanStock
                    ->pluck('stock_item')
                    ->map(fn ($stockItem): int => (int) $stockItem->id)
                    ->unique()
                    ->values()
                    ->all();
                $activeStockItems = $stockItems->map(fn ($stockItem): array => [
                    'id' => (int) $stockItem->id,
                    'name' => (string) $stockItem->linkLabel(),
                    'sku' => (string) ($stockItem->sku ?? ''),
                ])->values()->all();
            @endphp
            <section
                id="workshop-stock-reconciliation"
                class="scroll-mt-24 mb-6 rounded-xl border border-sky-200 bg-white p-5 shadow-sm"
                x-data="{
                    listedStockIds: @js($listedStockIds),
                    activeStockItems: @js($activeStockItems),
                    additionalItems: [],
                    addAdditionalItem() {
                        this.additionalItems.push({ stock_item_id: '', item_name: '', used: '' });
                    },
                    removeAdditionalItem(index) {
                        this.additionalItems.splice(index, 1);
                    },
                    isDuplicateAdditionalItem(index) {
                        const id = Number(this.additionalItems[index]?.stock_item_id || 0);
                        if (!id) return false;
                        return this.listedStockIds.includes(id)
                            || this.additionalItems.some((item, itemIndex) => itemIndex !== index && Number(item.stock_item_id || 0) === id);
                    },
                }"
            >
                @if($errors->has('stock') || collect($errors->getMessages())->keys()->contains(fn ($errorKey) => str_starts_with((string) $errorKey, 'actual_used')))
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        @foreach($errors->getMessages() as $errorKey => $messages)
                            @if($errorKey === 'stock' || $errorKey === 'actual_used' || str_starts_with($errorKey, 'actual_used.'))
                                <p>{{ $messages[0] ?? 'Check the stock quantities and try again.' }}</p>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($workshopKitPreparation->isNotEmpty())
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <p class="font-semibold">Assemble any planned kits that were used before recording their workshop use.</p>
                        <ul class="mt-2 space-y-2">
                            @foreach($workshopKitPreparation as $kitPreparation)
                                <li class="flex flex-wrap items-center justify-between gap-2">
                                    <span>{{ $kitPreparation['quantity'] }} {{ \Illuminate\Support\Str::plural($kitPreparation['stock_item']->name, $kitPreparation['quantity']) }} to assemble</span>
                                    <x-ui.button type="button" color="primary-outline" size="compact" data-assembly-url="{{ $kitPreparation['url'] }}" x-on:click="$dispatch('open-workshop-assembly', { url: $el.dataset.assemblyUrl })">Assemble kits</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.workshop.stock.reconcile', $workshop) }}" class="mt-3">
                    @csrf
                    @if($workshopPlanStock->isNotEmpty())
                        <h3 class="text-sm font-semibold text-slate-800">Planned stock for this workshop</h3>
                        <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                            <x-ui.table variant="plain" table-class="min-w-full">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-3 py-2">Stock item</th>
                                        <th scope="col" class="px-3 py-2 text-center">Planned</th>
                                        <th scope="col" class="w-52 px-3 py-2 text-center">Actually used</th>
                                        <th scope="col" class="w-44 px-3 py-2 text-center">Unused</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($workshopPlanStock as $plannedRow)
                                        @php
                                            $stockItem = $plannedRow['stock_item'];
                                            $stockItemId = (int) $stockItem->id;
                                            $plannedQuantity = (float) $plannedRow['planned_quantity'];
                                        @endphp
                                        <tr x-data="{
                                            planned: @js($plannedQuantity),
                                            used: @js(old('actual_used.'.$stockItemId, '')),
                                            unused() {
                                                const quantity = Number(this.used);
                                                return this.used === '' || !Number.isFinite(quantity) ? null : Math.max(0, this.planned - quantity);
                                            },
                                            extraUsed() {
                                                const quantity = Number(this.used);
                                                return this.used === '' || !Number.isFinite(quantity) ? 0 : Math.max(0, quantity - this.planned);
                                            },
                                            format(quantity) {
                                                return new Intl.NumberFormat('en-AU', { maximumFractionDigits: 3 }).format(quantity);
                                            },
                                        }">
                                            <td class="px-3 py-3">
                                                <a
                                                    href="{{ $stockItem->is_kit ? route('admin.shop.stock.kit.edit', $stockItem) : route('admin.shop.stock.edit', $stockItem) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="Open stock item in a new tab"
                                                    class="font-medium text-primary-color hover:underline"
                                                >{{ $stockItem->linkLabel() }}</a>
                                            </td>
                                            <td class="px-3 py-3 text-center text-sm text-slate-700 tabular-nums">{{ $stockItem->formatQuantity($plannedQuantity) }}</td>
                                            <td class="px-3 py-3 text-center">
                                                <x-ui.input-control
                                                    name="actual_used[{{ $stockItemId }}]"
                                                    type="number"
                                                    min="0"
                                                    max="100000"
                                                    step="1"
                                                    required
                                                    value="{{ old('actual_used.'.$stockItemId, '') }}"
                                                    class="text-center tabular-nums"
                                                    x-model.number="used"
                                                    aria-label="Actual {{ strtolower($stockItem->linkLabel()) }} quantity used"
                                                />
                                            </td>
                                            <td class="px-3 py-3 text-center text-sm text-slate-700 tabular-nums">
                                                <span x-text="unused() === null ? '-' : format(unused())"></span>
                                                <span x-show="extraUsed() > 0" x-cloak class="block text-xs text-amber-700" x-text="'+' + format(extraUsed()) + ' from unreserved stock'"></span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                        </div>
                    @else
                        <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">No linked stock to reconcile. Add any other stock used below.</p>
                    @endif

                    <div class="mt-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800">Other stock used</h3>
                                <p class="mt-1 text-xs text-slate-500">Add stock items used outside the workshop plan.</p>
                            </div>
                            <x-ui.button type="button" color="primary-outline" size="compact" x-on:click="addAdditionalItem()">
                                <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add stock item
                            </x-ui.button>
                        </div>
                        <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200" x-show="additionalItems.length > 0" x-cloak>
                            <x-ui.table variant="plain" table-class="min-w-full">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-3 py-2">Stock item</th>
                                        <th scope="col" class="w-52 px-3 py-2 text-center">Actually used</th>
                                        <th scope="col" class="w-16 px-3 py-2"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(additionalItem, index) in additionalItems" :key="index">
                                        <tr>
                                            <td class="px-3 py-3 text-center">
                                                <x-admin.stock-item-link-field
                                                    model="additionalItem"
                                                    stock-items-expression="activeStockItems"
                                                    placeholder="Search name or SKU"
                                                    aria-label="Additional stock item"
                                                    no-matches-text="No matching active stock items."
                                                />
                                                <p x-show="isDuplicateAdditionalItem(index)" x-cloak class="mt-1 text-xs text-red-700">This item is already listed. Enter its total used quantity in the existing row.</p>
                                            </td>
                                            <td class="px-3 py-3">
                                                <x-ui.input-control
                                                    type="number"
                                                    min="0"
                                                    max="100000"
                                                    step="1"
                                                    x-bind:name="additionalItem.stock_item_id && !isDuplicateAdditionalItem(index) ? `actual_used[${Number(additionalItem.stock_item_id)}]` : null"
                                                    x-bind:required="Boolean(additionalItem.stock_item_id) && !isDuplicateAdditionalItem(index)"
                                                    x-bind:disabled="isDuplicateAdditionalItem(index)"
                                                    x-model.number="additionalItem.used"
                                                    class="text-center tabular-nums"
                                                    aria-label="Additional stock quantity used"
                                                />
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <x-ui.row-action label="Remove" icon="fa-solid fa-trash" tone="danger" type="button" x-on:click="removeAdditionalItem(index)" />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </x-ui.table>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-ui.button type="submit">Save stock reconciliation</x-ui.button>
                    </div>
                </form>
            </section>
        @endif

@include('admin.workshop.partials.stock-assembly-dialog')
