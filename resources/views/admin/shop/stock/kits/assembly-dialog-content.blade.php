@php
    $dialogMode = $dialogMode ?? false;
    $ignoreOldAssemblyUsage = $ignoreOldAssemblyUsage ?? false;
    $completedQuantity = $completedQuantity ?? old('completed_quantity', $plannedQuantity);
@endphp

<div>
    <form method="POST" action="{{ route('admin.shop.stock.kit.assemble', $kit) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="quantity" value="{{ $plannedQuantity }}">
        @if(($workshop ?? null) instanceof \App\Models\Workshop)
            <input type="hidden" name="workshop_id" value="{{ $workshop->getKey() }}">
        @elseif(($workshopReturnId ?? null) !== null)
            <input type="hidden" name="workshop_return_id" value="{{ $workshopReturnId }}">
        @endif
        @if(($workshop ?? null) instanceof \App\Models\Workshop || ($workshopReturnId ?? null) !== null)
            <input type="hidden" name="workshop_return_to" value="{{ $workshopReturnTo ?? 'stock-reconciliation' }}">
        @endif

        <div class="relative flex flex-col gap-3 lg:flex-row lg:items-center">
            <h2 id="assembly-dialog-title" class="text-lg font-semibold text-slate-900">Assembly plan</h2>
            <div class="flex w-full flex-nowrap items-center justify-between gap-3 lg:ml-auto lg:w-auto lg:justify-end lg:pr-12">
                <label for="assembly-completed-quantity" class="whitespace-nowrap text-xs font-medium text-slate-700 sm:text-sm">Finished kits to add to stock</label>
                <div class="w-20 shrink-0">
                    <x-ui.input-control
                        id="assembly-completed-quantity"
                        name="completed_quantity"
                        type="number"
                        min="0"
                        :max="$plannedQuantity"
                        step="1"
                        :value="$completedQuantity"
                        class="text-center tabular-nums"
                        data-assembly-quantity
                        required
                    />
                    @error('completed_quantity')<p data-assembly-validation-error class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            @if($dialogMode)
                <x-ui.button type="button" variant="plain" class="absolute right-0 top-0 h-10 w-10 shrink-0 rounded-lg p-0! text-slate-500" data-close-assembly-dialog aria-label="Close assembly dialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 md:overflow-x-auto">
            <table data-assembly-table class="min-w-full w-full divide-y divide-slate-200 text-sm md:min-w-[880px]">
                <caption class="sr-only">Material quantities for this kit assembly</caption>
                <thead class="hidden bg-slate-50 text-left text-xs font-semibold text-slate-600 md:table-header-group">
                    <tr>
                        <th scope="col" class="px-3 py-2">Stock item</th>
                        <th scope="col" class="px-3 py-2 text-center">Available</th>
                        <th scope="col" class="px-3 py-2 text-center">Estimated use</th>
                        <th scope="col" class="px-3 py-2 text-center">Suggested deduction</th>
                        <th scope="col" class="w-36 px-3 py-2 text-right">Actual deduction</th>
                    </tr>
                </thead>
                <tbody class="block bg-white md:table-row-group">
                    @forelse($assemblyRows as $stockItemId => $row)
                        @php
                            $stockItem = $row['stock_item'];
                            $quantityField = 'actual_usage.'.$stockItemId;
                            $available = (float) $row['available_quantity'];
                            $shortBy = max(0, (float) $row['suggested_deduction'] - $available);
                            $actualUsageValue = $ignoreOldAssemblyUsage ? $row['suggested_deduction'] : old($quantityField, $row['suggested_deduction']);
                        @endphp
                        <tr class="mb-2 block rounded-lg border border-slate-200 bg-white last:mb-0 md:mb-0 md:table-row md:rounded-none md:border-0 md:bg-transparent">
                            <th scope="row" class="block px-3 py-3 text-left font-medium text-slate-900 md:table-cell">
                                <span class="mb-1 block text-xs font-medium text-slate-500 md:hidden">Stock item</span>
                                <span class="block">{{ $stockItem->linkLabel() }}</span>
                                <span class="mt-0.5 block text-xs font-normal text-slate-500">{{ $stockItem->unit }}</span>
                            </th>
                            <td class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2.5 text-slate-700 md:table-cell md:border-t md:px-3 md:py-3 md:text-center md:tabular-nums">
                                <span class="text-xs font-medium text-slate-500 md:hidden">Available</span>
                                <span class="text-right tabular-nums md:text-center">
                                    {{ $stockItem->formatQuantity($available) }}
                                    @if($shortBy > 0.0005)
                                        <span class="mt-0.5 block text-xs font-normal text-amber-800 md:text-center">Short {{ $stockItem->formatQuantity($shortBy) }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2.5 text-slate-700 md:table-cell md:border-t md:px-3 md:py-3 md:text-center md:tabular-nums">
                                <span class="text-xs font-medium text-slate-500 md:hidden">Estimated use</span>
                                <span class="text-right tabular-nums md:text-center">{{ $stockItem->formatQuantity($row['estimated_usage']) }}</span>
                            </td>
                            <td class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2.5 text-slate-700 md:table-cell md:border-t md:px-3 md:py-3 md:text-center md:tabular-nums">
                                <span class="text-xs font-medium text-slate-500 md:hidden">Suggested deduction</span>
                                <span class="text-right tabular-nums md:text-center">{{ $stockItem->formatQuantity($row['suggested_deduction']) }}</span>
                            </td>
                            <td class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2.5 md:table-cell md:border-t md:px-3 md:py-3">
                                <span class="text-xs font-medium text-slate-500 md:hidden">Actual deduction</span>
                                <label for="actual-usage-{{ $stockItemId }}" class="sr-only">Actual {{ $stockItem->unit }} deduction for {{ $stockItem->linkLabel() }}</label>
                                <div class="ml-auto w-28">
                                    <x-ui.input-control
                                        id="actual-usage-{{ $stockItemId }}"
                                        :name="'actual_usage['.$stockItemId.']'"
                                        type="number"
                                        min="0"
                                        :max="$available"
                                        :step="$row['increment']"
                                        :value="$actualUsageValue"
                                        class="text-right tabular-nums"
                                        required
                                    />
                                </div>
                                @error($quantityField)<p data-assembly-validation-error class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p>@enderror
                            </td>
                        </tr>
                    @empty
                        <tr class="block md:table-row"><td colspan="5" class="block px-3 py-5 text-center text-sm text-slate-600 md:table-cell">Add stock items to the recipe before assembling this kit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('actual_usage')<p data-assembly-validation-error class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        @error('quantity')<p data-assembly-validation-error class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

        <div class="w-full border-t border-slate-200 pt-4">
            <x-ui.input type="textarea" name="assembly_notes" label="Assembly note (optional)" rows="2" :value="old('assembly_notes')" class="w-full" />
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
            @if($dialogMode)
                <x-ui.button type="button" color="outline" data-close-assembly-dialog>Cancel</x-ui.button>
            @else
                <x-ui.button color="outline" :href="route('admin.shop.stock.kit.edit', $kit)">Back to kit</x-ui.button>
            @endif
            <x-ui.button type="submit" :disabled="count($assemblyRows) === 0">Confirm assembly</x-ui.button>
        </div>
    </form>
</div>
