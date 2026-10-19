<x-ui.list-dialog id="finance-price-rounding" title="Price rounding" kind="filters" draggable>
    <template x-if="roundingItem">
        <div>
            <div class="space-y-5 p-5">
            <div>
                <p class="font-medium text-slate-800" x-text="roundingItem.details_json?.workshop?.name || roundingItem.description || itemTypeLabel(roundingItem.kind)"></p>
                <p class="mt-1 text-sm text-slate-600">Choose a rate that gives exact cents before and after GST for this quantity.</p>
            </div>
            <dl class="text-sm">
                <div class="flex items-center justify-between gap-4 rounded-lg bg-slate-50 p-3"><dt>Billable quantity</dt><dd class="font-semibold tabular-nums" x-text="roundingItem.quantity"></dd></div>
            </dl>
            <x-ui.table variant="listing">
                <thead><tr><th>Price</th><th class="text-right">Entered</th><th class="text-right">Selected</th></tr></thead>
                <tbody>
                    <tr><td>Unit price ex GST</td><td class="text-right tabular-nums" x-text="'$' + SM.formatUnitPrice(roundingOriginal?.unitEx)"></td><td class="text-right font-medium tabular-nums" x-text="'$' + normalizeMoney(roundingPreview?.unitEx)"></td></tr>
                    <tr><td>Unit price inc GST</td><td class="text-right tabular-nums" x-text="'$' + SM.formatUnitPrice(roundingOriginal?.unitInc)"></td><td>
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button type="button" variant="soft" size="icon-small" aria-label="Previous clean price" title="Previous clean price" x-bind:disabled="roundingProposed &lt;= 0" x-on:click="moveRoundingPrice(-1)"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></x-ui.button>
                            <span class="font-semibold tabular-nums" x-text="'$' + normalizeMoney(roundingPreview?.unitInc)"></span>
                            <x-ui.button type="button" variant="soft" size="icon-small" aria-label="Next clean price" title="Next clean price" x-on:click="moveRoundingPrice(1)"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></x-ui.button>
                        </div>
                    </td></tr>
                    <tr><td>Subtotal ex GST</td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingOriginal?.net)"></td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingPreview?.net)"></td></tr>
                    <tr><td>GST</td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingOriginal?.tax)"></td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingPreview?.tax)"></td></tr>
                    <tr class="font-semibold"><td>Total inc GST</td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingOriginal?.gross)"></td><td class="text-right tabular-nums" x-text="'$' + normalizeMoney(roundingPreview?.gross)"></td></tr>
                </tbody>
            </x-ui.table>
            <p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-900">Change to total: <strong class="tabular-nums" x-text="(roundingPreview?.gross &gt;= roundingOriginal?.gross ? '+' : '−') + '$' + normalizeMoney(Math.abs((roundingPreview?.gross || 0) - (roundingOriginal?.gross || 0)))"></strong>. This is included in the unit price.</p>
            </div>
            <div class="sm-dialog-footer">
                <x-ui.button type="button" color="outline" data-close-dialog>Cancel</x-ui.button>
                <x-ui.button type="button" x-on:click="applyRoundingPrice()">Use selected rate</x-ui.button>
            </div>
        </div>
    </template>
</x-ui.list-dialog>
