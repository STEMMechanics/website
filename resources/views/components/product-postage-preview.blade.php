@props(['variant' => false])
<div class="mt-4 rounded-xl border border-sky-200 bg-sky-50/50 p-4" x-data="SM.productPostagePreview(@js(route('admin.shop.product.postage-preview')), @js(csrf_token()))"
     x-effect="schedule({ product_type: productType, box_only: boxOnly,
        length_mm: {{ $variant ? "variant.length_mm === '' || variant.length_mm == null ? basePackedLength : variant.length_mm" : 'basePackedLength' }},
        width_mm: {{ $variant ? "variant.width_mm === '' || variant.width_mm == null ? basePackedWidth : variant.width_mm" : 'basePackedWidth' }},
        height_mm: {{ $variant ? "variant.height_mm === '' || variant.height_mm == null ? basePackedHeight : variant.height_mm" : 'basePackedHeight' }},
        weight_grams: {{ $variant ? "variant.weight_grams === '' || variant.weight_grams == null ? basePackedWeight : variant.weight_grams" : 'basePackedWeight' }}
     })">
    <h3 class="text-sm font-semibold text-gray-900"><i class="fa-solid fa-box mr-2 text-primary-color" aria-hidden="true"></i>Postage fit</h3>
    <p class="mt-1 text-xs text-gray-600">One packed item. Cheapest fit per delivery channel. @if($variant)Blank overrides use the base measurements.@endif</p>
    <p class="mt-3 text-sm text-gray-600" x-show="busy" x-cloak role="status">Checking postage…</p>
    <p class="mt-3 text-sm text-gray-600" x-show="message" x-text="message" x-cloak role="status"></p>
    <div class="mt-3 grid gap-3 xl:grid-cols-2" x-show="options.length" x-cloak>
        <template x-for="option in options" :key="option.code">
            <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-3">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-sm font-semibold text-gray-900" x-text="option.channel"></h4>
                    <span class="shrink-0 text-sm font-bold text-gray-900" x-show="option.fits" x-text="money(option.amount)"></span>
                </div>
                <template x-if="option.fits">
                    <div class="mt-1 space-y-1 text-xs text-gray-600">
                        <p class="font-medium text-primary-color" x-text="option.package"></p>
                        <p x-show="option.dimensions_mm" x-text="(option.dimensions_mm || []).join(' × ') + ' mm' + (option.max_weight_grams ? ' · Up to ' + option.max_weight_grams + ' g' : '')"></p>
                        <p x-show="option.chargeable_weight_grams !== null" x-text="'Chargeable weight: ' + option.chargeable_weight_grams + ' g'"></p>
                        <p x-show="option.packaging_cost > 0" x-text="'Includes ' + money(option.packaging_cost) + ' packaging'"></p>
                    </div>
                </template>
                <p class="mt-1 text-xs text-amber-800" x-show="!option.fits" x-text="option.reason"></p>
            </div>
        </template>
    </div>
</div>
