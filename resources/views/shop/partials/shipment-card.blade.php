<div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 font-semibold text-gray-900">
                <i :class="shipment.type === 'immediate' ? 'fa-solid fa-paper-plane text-sky-600' : 'fa-solid fa-clock text-amber-600'" aria-hidden="true"></i>
                <span x-text="shipment.title_primary || shipment.title"></span>
            </div>
            <div x-show="shipment.title_meta" x-cloak class="mt-1 max-w-sm text-xs text-gray-500" x-text="shipment.title_meta"></div>
        </div>
        <div class="text-right">
            <div class="font-semibold text-gray-900" x-text="shipment.is_pickup ? 'Free' : {{ $moneyFunction ?? 'formatMoney' }}(shipment.amount)"></div>
        </div>
    </div>

    <template x-if="Array.isArray(shipment.items) && shipment.items.length > 0">
        <ul class="mt-3 list-disc space-y-2 border-t border-gray-200 pl-5 pt-3 text-xs text-gray-600">
            <template x-for="item in shipment.items" :key="`${shipment.key}-${item.display_title}-${item.quantity}`">
                <li>
                    <div>
                        <div class="font-medium text-gray-800" x-text="`${item.display_title} x ${item.quantity}`"></div>
                    </div>
                </li>
            </template>
        </ul>
    </template>
</div>
