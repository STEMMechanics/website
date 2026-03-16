<x-layout>
    <x-mast backRoute="admin.shop.coupon.index" backTitle="Store Vouchers">{{ isset($coupon) ? 'Edit' : 'Create' }} Voucher</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.shop.coupon.'.(isset($coupon) ? 'update' : 'store'), $coupon ?? []) }}">
            @csrf
            @isset($coupon)
                @method('PUT')
            @endisset

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="code" label="Voucher Code" :value="$coupon->code ?? ''" />
                    <x-ui.input name="description" label="Description" :value="$coupon->description ?? ''" />
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.select name="status" label="Status">
                        @foreach(\App\Models\Coupon::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $coupon->status ?? \App\Models\Coupon::STATUS_ACTIVE) === $status)>{{ \App\Models\Coupon::statusLabel($status) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select name="discount_type" label="Discount Type">
                        @foreach(\App\Models\Coupon::DISCOUNT_TYPES as $type)
                            <option value="{{ $type }}" @selected(old('discount_type', $coupon->discount_type ?? \App\Models\Coupon::DISCOUNT_TYPE_FIXED_AMOUNT) === $type)>{{ \App\Models\Coupon::discountTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="amount" label="Amount" :value="isset($coupon) ? number_format((float) $coupon->amount, 2, '.', '') : '0.00'" info="Use dollars for fixed discounts, percent for percentage discounts, or leave at 0 for free shipping." />
                    <x-ui.input name="minimum_order_amount" label="Minimum Order Amount" :value="isset($coupon) && $coupon->minimum_order_amount !== null ? number_format((float) $coupon->minimum_order_amount, 2, '.', '') : ''" />
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input name="usage_limit" label="Total Usage Limit" type="number" min="1" :value="$coupon->usage_limit ?? ''" />
                    <x-ui.input name="usage_limit_per_user" label="Per User Limit" type="number" min="1" :value="$coupon->usage_limit_per_user ?? ''" />
                    <x-ui.input name="starts_at" label="Starts At" type="datetime-local" :value="old('starts_at', isset($coupon) && $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : '')" />
                    <x-ui.input name="ends_at" label="Ends At" type="datetime-local" :value="old('ends_at', isset($coupon) && $coupon->ends_at ? $coupon->ends_at->format('Y-m-d\TH:i') : '')" />
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit">Save Voucher</x-ui.button>
                @isset($coupon)
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-danger-color px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition hover:bg-danger-color-dark"
                        x-data
                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete voucher?', 'Are you sure you want to delete this voucher? This action cannot be undone.', '{{ route('admin.shop.coupon.destroy', $coupon) }}')"
                    >Delete Voucher</button>
                @endisset
            </div>
        </form>
    </x-container>
</x-layout>
