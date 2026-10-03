<x-layout title="Sponsor details — STEMMechanics">
    <x-mast title="Business Sponsorship" description="Add your business and billing details." />
    <x-container class="mx-auto max-w-3xl py-6 sm:py-9">
        <x-sponsorship-stepper :current="1" />
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <x-sponsorship-flow-heading
                icon="fa-solid fa-building"
                title="Business and invoice details"
                description="We’ll use these details for billing and email your invoices or receipts. Invoice requests are confirmed by email before we create them."
            />
            @if($errors->any())
                <div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('sponsor.details.save') }}" class="mt-6" x-data="{ country: @js(old('country', $details['country'] ?? 'Australia')), isAustralia() { return ['australia', 'au', 'aus'].includes((this.country || '').trim().toLowerCase()); } }">
                @csrf
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-ui.input type="email" name="email" label="Email address" :value="old('email', $details['email'] ?? '')" autocomplete="email" maxlength="255" required />
                    <x-ui.input name="contact_name" label="Contact name" :value="old('contact_name', $details['contact_name'] ?? '')" autocomplete="name" maxlength="255" required />
                    <x-ui.input name="company_name" label="Organisation name" :value="old('company_name', $details['company_name'] ?? '')" autocomplete="organization" maxlength="255" required />
                </div>

                <div class="mt-7 border-t border-gray-200 pt-5">
                    <h3 class="font-semibold text-gray-900">Invoice details</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Invoices are emailed to the contact above. Add the organisation’s billing address and tax details you’d like included.</p>
                    <div class="mt-4 grid gap-x-4 sm:grid-cols-2">
                        <x-ui.input name="country" label="Billing country" :value="old('country', $details['country'] ?? 'Australia')" autocomplete="country-name" maxlength="120" x-model="country" required />
                        <x-ui.input name="abn" label="ABN (optional)" :value="old('abn', $details['abn'] ?? '')" maxlength="20" inputmode="numeric" />
                        <x-ui.input name="foreign_tax_id" label="Overseas tax ID (optional)" :value="old('foreign_tax_id', $details['foreign_tax_id'] ?? '')" maxlength="100" />
                        <x-ui.input class="sm:col-span-2" name="billing_address" label="Billing address" :value="old('billing_address', $details['billing_address'] ?? '')" autocomplete="address-line1" maxlength="255" />
                        <x-ui.input class="sm:col-span-2" name="billing_address2" label="Address line 2" :value="old('billing_address2', $details['billing_address2'] ?? '')" autocomplete="address-line2" maxlength="255" />
                        <x-ui.input name="billing_city" label="City or locality" :value="old('billing_city', $details['billing_city'] ?? '')" autocomplete="address-level2" maxlength="120" />
                        <x-ui.input name="billing_state" label="State or region" :value="old('billing_state', $details['billing_state'] ?? '')" autocomplete="address-level1" maxlength="120" />
                        <x-ui.input name="billing_postcode" label="Postcode / ZIP" :value="old('billing_postcode', $details['billing_postcode'] ?? '')" autocomplete="postal-code" maxlength="40" />
                        <div class="mb-4 flex items-center" x-show="!isAustralia()" x-cloak>
                            <input type="hidden" name="non_resident_declaration" value="0">
                            <x-ui.checkbox name="non_resident_declaration" label="I confirm the sponsor is a non-resident of Australia" :checked="old('non_resident_declaration', $details['non_resident_declaration'] ?? false)" class="mb-0" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-ui.button type="submit">Continue</x-ui.button>
                </div>
            </form>
        </section>
    </x-container>
</x-layout>
