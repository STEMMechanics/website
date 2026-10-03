<x-layout>
    <x-container class="py-10 sm:py-16">
        <section class="mx-auto max-w-2xl rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-700">✓</div>
            <h1 class="mt-5 text-3xl font-bold text-gray-900">Thank you for supporting STEMMechanics</h1>
            @if($sponsorship->frequency === 'monthly' && $paidPayment)
                <p class="mt-3 text-gray-600">Your monthly sponsorship is set up. We’ll email an invoice/receipt after each successful payment.</p>
            @elseif($sponsorship->frequency === 'monthly')
                <p class="mt-3 text-gray-600">We’re confirming your first monthly payment. We’ll email your invoice/receipt as soon as it’s complete.</p>
            @else
                <p class="mt-3 text-gray-600">Your one-time sponsorship payment has been received. A STEMMechanics invoice or receipt will be emailed to you.</p>
            @endif
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <x-ui.button href="{{ route('sponsor.manage.request') }}" color="outline">Manage sponsorship</x-ui.button>
                <x-ui.button href="{{ route('sponsor.index') }}">Back to Sponsor page</x-ui.button>
            </div>
        </section>

        @if($sponsorship->checkout_type === \App\Models\Sponsorship::CHECKOUT_TYPE_COMMUNITY_SUPPORT && $paidPayment)
            <section class="mx-auto mt-5 max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
                @if(session('message'))<p class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('message') }}</p>@endif
                <h2 class="text-lg font-semibold text-gray-900">Need a name or address on your invoice?</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">Your invoice is ready without recipient details. Add a person or organisation and billing address below if you need them shown. The same invoice number is retained, and the updated copy will be emailed to you.</p>
                @foreach($errors->all() as $error)<p class="mt-3 text-sm text-red-600" role="alert">{{ $error }}</p>@endforeach
                <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4" @if($errors->any() || session('message')) open @endif>
                    <summary class="cursor-pointer font-semibold text-primary-color">Add invoice recipient details</summary>
                    <form method="POST" action="{{ route('sponsor.complete.invoice-details') }}" class="mt-4 grid gap-x-4 sm:grid-cols-2" x-data="{ recipientType: @js(old('sponsor_type', 'individual')) }">
                        @csrf
                        <x-ui.select name="sponsor_type" label="Recipient type" x-model="recipientType" required>
                            <option value="individual" @selected(old('sponsor_type', 'individual') === 'individual')>Individual</option>
                            <option value="organisation" @selected(old('sponsor_type') === 'organisation')>Organisation</option>
                        </x-ui.select>
                        <x-ui.input name="contact_name" label="Recipient name" :value="old('contact_name', $sponsorship->sponsor->contact_name === 'Supporter' ? '' : $sponsorship->sponsor->contact_name)" maxlength="255" required />
                        <div x-show="recipientType === 'organisation'" x-cloak><x-ui.input name="company_name" label="Organisation name" :value="old('company_name', $sponsorship->sponsor->company_name)" maxlength="255" x-bind:required="recipientType === 'organisation'" /></div>
                        <x-ui.input name="abn" label="ABN (optional)" :value="old('abn', $sponsorship->sponsor->abn)" maxlength="20" inputmode="numeric" />
                        <x-ui.input name="foreign_tax_id" label="Overseas tax ID (optional)" :value="old('foreign_tax_id', $sponsorship->sponsor->foreign_tax_id)" maxlength="100" />
                        <x-ui.input class="sm:col-span-2" name="billing_address" label="Billing address (optional)" :value="old('billing_address', $sponsorship->sponsor->billing_address)" autocomplete="address-line1" maxlength="255" />
                        <x-ui.input class="sm:col-span-2" name="billing_address2" label="Address line 2 (optional)" :value="old('billing_address2', $sponsorship->sponsor->billing_address2)" autocomplete="address-line2" maxlength="255" />
                        <x-ui.input name="billing_city" label="City or locality (optional)" :value="old('billing_city', $sponsorship->sponsor->billing_city)" autocomplete="address-level2" maxlength="120" />
                        <x-ui.input name="billing_state" label="State or region (optional)" :value="old('billing_state', $sponsorship->sponsor->billing_state)" autocomplete="address-level1" maxlength="120" />
                        <x-ui.input name="billing_postcode" label="Postcode (optional)" :value="old('billing_postcode', $sponsorship->sponsor->billing_postcode)" autocomplete="postal-code" maxlength="40" />
                        @foreach(['sponsor_type','contact_name','company_name','abn','foreign_tax_id','billing_address','billing_address2','billing_city','billing_state','billing_postcode'] as $field)@error($field)<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror @endforeach
                        <div class="sm:col-span-2"><x-ui.button type="submit">Update and email invoice</x-ui.button></div>
                    </form>
                </details>
            </section>
        @endif
    </x-container>
</x-layout>
