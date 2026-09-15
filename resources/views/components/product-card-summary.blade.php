@props(['product'])
@php
    $inStock = $product->isPurchasable();
    $hasVariants = $product->hasOptionChoices();
    $variantCount = $product->optionChoiceCount();
    $priceRangeLabel = $product->priceRangeLabel();
    $priceIsFromRange = \Illuminate\Support\Str::startsWith($priceRangeLabel, 'From ');
    $priceRangeAmountLabel = $priceIsFromRange
        ? \Illuminate\Support\Str::after($priceRangeLabel, 'From ')
        : $priceRangeLabel;
@endphp
<div {{ $attributes->class(['shop-product-card-stock-price-line']) }}>
    <div class="shop-product-card-stock">
        @if(!$inStock)
            <x-stock-indicator tone="danger" :label="'Out of stock'" />
        @elseif($product->isDigital())
            <x-stock-indicator tone="success" :label="'Instant download after checkout'" />
        @elseif($hasVariants)
            <span class="text-xs font-medium text-gray-500">{{ $variantCount }} option{{ $variantCount === 1 ? '' : 's' }} available</span>
        @else
            <x-stock-indicator :tone="$product->availabilityTone()" :label="$product->availabilityLabel()" :stack-details="true" />
        @endif
    </div>

    <div class="text-right">
        <div class="flex items-baseline gap-1 text-xl font-bold text-gray-900 sm:justify-end">
            @if($priceIsFromRange)
                <span class="text-xs font-medium text-gray-500 mr-1">From</span>
            @endif
            <span>{{ $priceRangeAmountLabel }}</span>
        </div>
    </div>
</div>
