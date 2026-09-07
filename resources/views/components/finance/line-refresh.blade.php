@props(['price' => false])
<x-ui.button variant="plain" type="button" :aria-label="$price ? 'Refresh price from allocation plan' : 'Recalculate billable quantity'"
    :title="$price ? 'Refresh price from allocation plan' : 'Recalculate billable quantity'"
    class="absolute right-1 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-md text-slate-400 hover:bg-sky-50 hover:text-primary-color"
    x-show="item.kind === 'multi_workshop' || item.kind === 'workshop' || item.kind === 'travel'"
    x-on:click="{{ $price ? 'SM.refreshLinePrice(item)' : 'SM.refreshLineQuantity(item)' }}; serializeLineItems()">
    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
</x-ui.button>
