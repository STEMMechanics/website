@props(['user'])

@php
    $shipping_same_billing = $user->shipping_address === $user->billing_address
        && $user->shipping_address2 === $user->billing_address2
        && $user->shipping_city === $user->billing_city
        && $user->shipping_state === $user->billing_state
        && $user->shipping_postcode === $user->billing_postcode
        && $user->shipping_country === $user->billing_country;
    $groupValue = old('groups', implode(', ', $user->groupSlugs()));
@endphp

<x-layout>
    <x-mast backRoute="admin.user.index" backTitle="Users">Edit User</x-mast>

    <x-container>
        @php
            $accountCredit = (float) ($accountCredit ?? 0);
            $cardRefundableCredit = (float) ($cardRefundableCredit ?? 0);
        @endphp

        @if($accountCredit > 0.0001)
            <div
                class="absolute right-4 mb-6 rounded-b-lg border border-emerald-200 bg-emerald-50 py-2 px-4 text-emerald-950"
                x-data="{ createRefundOpen: false, isSubmitting: false }"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Account Credit</div>
                    <div class="font-semibold">{{ money($accountCredit) }}</div>
                    @if($cardRefundableCredit > 0.0001)
                        <div class="text-xs text-emerald-800">Card-refundable: {{ money($cardRefundableCredit) }}</div>
                    @endif
                    @if(isset($refundPayment))
                        <x-ui.button color="primary-outline-sm" type="button" x-on:click="createRefundOpen = true">Create refund</x-ui.button>
                    @endif
                    <x-ui.button color="primary-outline-sm" href="{{ route('admin.user.payments', $user) }}">Payments</x-ui.button>
                </div>

                @if(isset($refundPayment))
                    <template x-teleport="body">
                        <div
                            x-show="createRefundOpen"
                            x-cloak
                            x-on:keydown.escape.window="createRefundOpen = false"
                            class="fixed inset-0 z-220 flex items-center justify-center p-4"
                            role="dialog"
                            aria-modal="true"
                        >
                            <div class="absolute inset-0 bg-black/40" x-on:click="createRefundOpen = false"></div>
                            <div class="relative w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                <div class="mb-4 flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-950">Create refund</h3>
                                        <p class="text-sm text-gray-600">
                                            Record a refund payment against the available account credit.
                                            This user currently has {{ money($accountCredit) }} in account credit, and {{ money($refundPaymentAvailableAmount) }} is available to refund from this payment.
                                        </p>
                                    </div>
                                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="createRefundOpen = false">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('admin.payment.refund.manual', $refundPayment) }}"
                                    class="space-y-4"
                                    x-on:submit.prevent="if (isSubmitting) return; isSubmitting = true; $el.submit();"
                                >
                                    @csrf
                                    <x-ui.input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="{{ number_format($refundPaymentAvailableAmount, 2, '.', '') }}"
                                        required
                                        label="Refund Amount"
                                        name="amount"
                                        value="{{ number_format($refundPaymentAvailableAmount, 2, '.', '') }}"
                                        info="Enter an amount up to {{ money($refundPaymentAvailableAmount) }}."
                                        :moneyFormat="true"
                                    />
                                    <input type="hidden" name="strict_amount" value="1">
                                    <x-ui.select label="Refund Method" name="payment_method">
                                        <option value="" disabled {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === '' ? 'selected' : '' }}>Select refund method</option>
                                        <option value="{{ \App\Models\Payment::PAYMENT_METHOD_CASH }}" {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === \App\Models\Payment::PAYMENT_METHOD_CASH ? 'selected' : '' }}>Cash</option>
                                        <option value="{{ \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER }}" {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER ? 'selected' : '' }}>Bank Transfer</option>
                                    </x-ui.select>
                                    <x-ui.input type="datetime-local" label="Refund Date/Time" name="received_on" value="{{ now()->format('Y-m-d\TH:i') }}" />
                                    <x-ui.input label="Transfer / Cash Reference" name="reference" value="" info="Optional receipt number, transfer note, or cash reference." />
                                    <x-ui.input label="Reason (optional)" name="reason" value="" />

                                    <div class="flex justify-end gap-3 pt-1">
                                        <x-ui.button type="button" color="secondary" x-on:click="createRefundOpen = false">Cancel</x-ui.button>
                                        <x-ui.button type="submit" color="dark" x-bind:disabled="isSubmitting">Create refund</x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.user.update', $user) }}" x-data="{groupsRaw: @js($groupValue)}" x-on:submit.prevent="SM.updateShippingAddress(); $el.submit()">
            @method('PUT')
            @csrf
            <h3 class="text-lg font-bold mt-4 mb-3">Contact Information</h3>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input label="First name" name="firstname" value="{{ $user->firstname }}" />
                </div>
                <div class="flex-1">
                    <x-ui.input label="Surname" name="surname" value="{{ $user->surname }}" />
                </div>
            </div>
            <x-ui.input type="email" label="Email" name="email" value="{{ $user->email }}" />
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input label="Phone" name="phone" value="{{ $user->phone }}" />
                </div>
                <div class="flex-1"></div>
            </div>
            <x-ui.input label="Company (Optional)" name="company" value="{{ $user->company }}" />
            <x-ui.select label="Account Terms" name="account_terms_days" info="Set the number of days before invoice payment is due. Current means no extra terms.">
                @foreach(\App\Models\User::accountTermsOptions() as $days => $label)
                    <option value="{{ $days }}" @selected((int) old('account_terms_days', $user->accountTermsDays()) === (int) $days)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input
                label="Groups (comma or space separated)"
                name="groups"
                :suggestions="$groupSuggestions ?? []"
                :value="$groupValue"
                info="Slug format only. Uppercase/spaces/symbols are normalized."
                x-model="groupsRaw"
                x-on:input="groupsRaw = groupsRaw.split(/[\\s,]+/).map(v => v.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/-+/g, '-').replace(/^[-_]+|[-_]+$/g, '')).filter(Boolean).join(', ')"
            />

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Email Subscriptions</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Upcoming Workshops" name="subscribed" checked="{{ $user->subscribed }}" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Home Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.input label="Address" name="billing_address" value="{{ $user->billing_address }}" />
                    <x-ui.input label="Address 2" name="billing_address2" value="{{ $user->billing_address2 }}" />
                    <x-ui.input label="City" name="billing_city" value="{{ $user->billing_city }}" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="billing_state" value="{{ $user->billing_state }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="billing_postcode" value="{{ $user->billing_postcode }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="billing_country" value="{{ $user->billing_country }}" />
                </div>
            </section>

            <section x-data="{ open: true }">
                <a href="#" class="flex items-center" @click.prevent="open = !open">
                    <i :class="{'transform': !open, '-rotate-90': !open, 'translate-y-0.5': true}" class="fa-solid fa-angle-down text-lg transition-transform mr-2"></i>
                    <h3 class="text-lg font-bold mt-4 mb-3">Billing Address</h3>
                </a>
                <div x-show="open">
                    <x-ui.checkbox label="Same as billing address" name="shipping_same_billing" checked="{{ $shipping_same_billing }}" x-data x-on:click="SM.updateShippingAddress" />
                    <x-ui.input label="Address" name="shipping_address" value="{{ $user->shipping_address }}" readonly="{{ $shipping_same_billing }}" />
                    <x-ui.input label="Address 2" name="shipping_address2" value="{{ $user->shipping_address2 }}" readonly="{{ $shipping_same_billing }}" />
                    <x-ui.input label="City" name="shipping_city" value="{{ $user->shipping_city }}" readonly="{{ $shipping_same_billing }}" />
                    <div class="flex gap-8">
                        <div class="flex-1">
                            <x-ui.input label="State" name="shipping_state" value="{{ $user->shipping_state }}" readonly="{{ $shipping_same_billing }}" />
                        </div>
                        <div class="flex-1">
                            <x-ui.input label="Postcode" name="shipping_postcode" value="{{ $user->shipping_postcode }}" readonly="{{ $shipping_same_billing }}" />
                        </div>
                    </div>
                    <x-ui.input label="Country" name="shipping_country" value="{{ $user->shipping_country }}" readonly="{{ $shipping_same_billing }}" />
                </div>
            </section>

            <div class="flex justify-between mt-8">
                @if($user->id !== 1)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete this account? This action cannot be undone', '{{ route('admin.user.destroy', $user) }}')">Delete</x-ui.button>
                @else
                    <div></div>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
