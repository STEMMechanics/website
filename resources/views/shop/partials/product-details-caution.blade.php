<dl class="overflow-hidden rounded-xl border border-gray-200 bg-white px-4 py-3" x-show="currentProductDetails().length > 0" x-cloak>
        <h3 class="font-bold text-md mb-4">Product Details</h3>
        <template x-for="(detail, index) in currentProductDetails()" :key="`${detailSlug(detail.key)}-${index}`">
            <div class="grid grid-cols-[minmax(8rem,0.9fr)_minmax(0,1.1fr)] gap-4 mb-2">
                <dt class="text-sm font-semibold text-gray-700" x-text="detail.key"></dt>
                <dd class="text-sm text-gray-600" x-text="detail.value"></dd>
            </div>
        </template>
</dl>

@if(trim((string) $product->caution_message) !== '')
    <div class="mt-4 mx-auto flex items-center justify-center gap-3" role="note" aria-label="Product caution">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-md text-yellow-500" aria-hidden="true"></i>
        <p class="text-xs leading-4">{{ $product->caution_message }}</p>
    </div>
@endif
