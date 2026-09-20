@props(['amount', 'eligible' => 'true'])
<div x-data="SM.squareApplePay({
        enabled: squareEnabled,
        applicationId: squareApplicationId,
        locationId: squareLocationId,
        amount: () => ({{ $amount }}),
        eligible: () => ({{ $eligible }}),
        setBusy: value => { isSubmitting = value },
        setSource: value => { sourceId = value },
        track: stage => $dispatch('checkout-payment-event', { stage })
    })" x-show="walletReady && walletAmount() > 0" x-cloak class="mb-5">
    <button type="button" aria-label="Pay with Apple Pay"
        class="square-apple-pay-button w-full disabled:opacity-50 disabled:cursor-not-allowed"
        x-bind:disabled="isSubmitting || walletBusy || !walletEligible()"
        x-on:click="payWithApple($event, isSubmitting)"></button>
    <p x-show="walletError" x-text="walletError" role="alert" class="mt-2 text-sm text-red-600"></p>
    <div class="mt-3 flex items-center gap-3 text-xs text-gray-500">
        <span class="h-px flex-1 bg-gray-200" aria-hidden="true"></span>
        <span class="shrink-0">Or pay by card</span>
        <span class="h-px flex-1 bg-gray-200" aria-hidden="true"></span>
    </div>
</div>
