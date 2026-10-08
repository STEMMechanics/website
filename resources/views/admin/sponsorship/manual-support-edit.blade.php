@php
    $editing = $support->exists;
    $card = 'rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6';
@endphp
<x-layout>
    <x-admin.sponsorship-mast :title="$editing ? 'Edit sponsor support' : 'Add sponsor'" :description="$sponsor->exists ? 'For '.($sponsor->company_name ?: $sponsor->contact_name) : 'Add a person or organisation supporting STEMMechanics.'">
        @if($sponsor->exists)
            <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.sponsorship.sponsor.show', $sponsor) }}">Sponsor history</x-ui.button></x-slot:actions>
        @endif
    </x-admin.sponsorship-mast>
    <x-container class="py-5 sm:py-8">
        <form method="POST" action="{{ $editing ? route('admin.sponsorship.sponsor.manual-support.update', $support) : route('admin.sponsorship.sponsor.manual-support.store') }}" enctype="multipart/form-data" class="mx-auto max-w-5xl space-y-6" x-data="{
            supportMethod: @js(old('support_method', $support->support_method ?: 'cheque')),
            sendInvoiceEmail: @js((bool) old('send_invoice_email', false)),
            sponsorDetails: {
                email: @js(old('email', $sponsor->email ?? '')),
                sponsor_type: @js(old('sponsor_type', $sponsor->sponsor_type ?: 'organisation')),
                company_name: @js(old('company_name', $sponsor->company_name ?? '')),
                country: @js(old('country', $sponsor->country ?: 'Australia')),
                billing_address: @js(old('billing_address', $sponsor->billing_address ?? '')),
                billing_address2: @js(old('billing_address2', $sponsor->billing_address2 ?? '')),
                billing_city: @js(old('billing_city', $sponsor->billing_city ?? '')),
                billing_state: @js(old('billing_state', $sponsor->billing_state ?? '')),
                billing_postcode: @js(old('billing_postcode', $sponsor->billing_postcode ?? '')),
            },
            applyLinkedUser(detail) {
                const user = detail?.user;
                if (!user) return;
                this.sponsorDetails.email = user.email || '';
                this.sponsorDetails.sponsor_type = user.sponsor_type || 'individual';
                this.sponsorDetails.company_name = user.company_name || '';
                this.sponsorDetails.country = user.country || 'Australia';
                this.sponsorDetails.billing_address = user.billing_address || '';
                this.sponsorDetails.billing_address2 = user.billing_address2 || '';
                this.sponsorDetails.billing_city = user.billing_city || '';
                this.sponsorDetails.billing_state = user.billing_state || '';
                this.sponsorDetails.billing_postcode = user.billing_postcode || '';
            }
        }" x-on:admin-linked-user-changed.window="applyLinkedUser($event.detail)">
            @csrf
            @if($editing) @method('PUT') @endif
            @if(!$editing && $sponsor->exists)<input type="hidden" name="sponsor_id" value="{{ $sponsor->id }}">@endif

            <section class="{{ $card }}">
                <h2 class="text-xl font-semibold text-gray-900">Sponsor details</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">For money already received by cheque, cash or bank transfer, saving this form records the payment and creates an invoice or receipt. You can choose whether to email it to the sponsor below. In-kind support is recorded without an invoice.</p>
                <div class="mt-5 grid gap-x-4 md:grid-cols-2">
                    <div>
                        <x-admin.user-selector-inline
                            :users="$users"
                            :selected-user-id="$selectedUserId"
                            field-name="user_id"
                            lookup-name="sponsor_user_lookup"
                            label="Sponsor or contact name"
                            info="Search previous users by name, organisation or email, or enter a new name. Selecting someone fills in their saved contact details."
                            :allow-create="false"
                            :include-contact-details="true"
                            submit-label-as="contact_name"
                            :submit-label-value="old('contact_name', $sponsor->contact_name ?? '')"
                        />
                        @if($errors->has('contact_name'))
                            <div class="-mt-1 mb-3 text-xs text-red-600">{{ $errors->first('contact_name') }}</div>
                        @endif
                    </div>
                    <x-ui.input name="email" label="Email" type="email" x-model="sponsorDetails.email" maxlength="255" info="Optional unless you choose to email the invoice or receipt." x-bind:required="sendInvoiceEmail && supportMethod !== 'other_benefit'" />
                    <x-ui.select name="sponsor_type" label="Sponsor type" required x-model="sponsorDetails.sponsor_type">
                        <option value="individual">Individual</option>
                        <option value="organisation">Organisation</option>
                    </x-ui.select>
                    <x-ui.input name="company_name" label="Organisation name (optional)" x-model="sponsorDetails.company_name" maxlength="255" />
                    <x-ui.input name="country" label="Country" x-model="sponsorDetails.country" maxlength="120" required />
                </div>
            </section>

            <section class="{{ $card }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Public recognition</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Sponsors are private by default. Only turn this on with their permission. Never enter billing details or an ABN here.</p>
                    </div>
                    <div class="mb-0 flex items-center">
                        <input type="hidden" name="recognition_public" value="0">
                        <x-ui.checkbox name="recognition_public" label="Show this sponsor publicly" :checked="old('recognition_public', $sponsor->recognition_public ?? false)" class="mb-0" />
                    </div>
                </div>
                <div class="mt-4 grid gap-x-4 md:grid-cols-2">
                    <x-ui.input name="display_name" label="Name shown publicly" :value="old('display_name', $sponsor->display_name ?: ($sponsor->sponsor_type === 'organisation' ? $sponsor->company_name : ''))" maxlength="255" info="Enter the individual or organisation name exactly as it should appear." />
                    <x-ui.input name="website_url" label="Website (optional)" type="text" inputmode="url" placeholder="example.com.au" :value="old('website_url', $sponsor->website_url)" maxlength="2048" />
                    <x-ui.input name="logo" label="Logo or avatar (optional)" type="file" accept="image/png,image/jpeg,image/webp" info="PNG, JPG or WebP." />
                    <p class="md:col-span-2 -mt-3 mb-4 text-sm text-gray-600">Only approved names and optional websites are shown publicly. Logos are reserved for Major Sponsors.</p>
                    @if($sponsor->recognition_logo_path)
                        <div class="mb-4 flex items-center gap-3 text-sm text-gray-600">
                            <img src="{{ route('admin.sponsorship.sponsor.logo', $sponsor) }}" class="h-12 w-12 rounded border border-gray-200 object-contain p-1" alt="Current sponsor logo">
                            Current logo
                        </div>
                    @endif
                </div>
            </section>

            <section class="{{ $card }}">
                <h2 class="text-xl font-semibold text-gray-900">Support received</h2>
                <div class="mt-5 grid gap-x-4 md:grid-cols-2">
                    <x-ui.select name="support_method" label="Support method" required x-model="supportMethod">
                        <option value="cheque" @selected(old('support_method', $support->support_method ?: 'cheque') === 'cheque')>Cheque</option>
                        <option value="cash" @selected(old('support_method', $support->support_method ?: 'cheque') === 'cash')>Cash</option>
                        <option value="bank_transfer" @selected(old('support_method', $support->support_method ?: 'cheque') === 'bank_transfer')>Bank transfer received</option>
                        <option value="other_benefit" @selected(old('support_method', $support->support_method ?: 'cheque') === 'other_benefit')>Other benefit / in-kind</option>
                    </x-ui.select>
                    <div class="mb-4" x-show="supportMethod === 'other_benefit'" x-cloak>
                        <x-ui.input name="value_amount" label="Estimated benefit value (AUD)" type="number" min="0.01" step="0.01" :value="old('value_amount', $support->value_amount)" info="Optional for in-kind support." x-bind:disabled="supportMethod !== 'other_benefit'" />
                    </div>
                    <input type="hidden" name="project_id" value="{{ $support->project_id ?: $primaryProjectId }}">
                    <x-ui.select name="recognition_level_id" label="Public sponsor group (optional)">
                        <option value="" @selected(old('recognition_level_id', $support->recognition_level_id) === null || old('recognition_level_id', $support->recognition_level_id) === '')>Use configured support totals</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}" @selected((string) old('recognition_level_id', $support->recognition_level_id) === (string) $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="starts_on" label="Support starts / payment date" type="date" :value="old('starts_on', $support->starts_on?->format('Y-m-d') ?: now()->toDateString())" required />
                    <x-ui.input name="ends_on" label="Support ends (optional)" type="date" :value="old('ends_on', $support->ends_on?->format('Y-m-d'))" info="Leave blank for ongoing support. Major Sponsors appear on the homepage while their support is active." />
                    <x-ui.input class="md:col-span-2" type="textarea" name="support_description" label="Description (required for in-kind support)" :value="old('support_description', $support->support_description)" rows="3" maxlength="500" />
                    <x-ui.input class="md:col-span-2" type="textarea" name="internal_note" label="Internal note (optional)" :value="old('internal_note', $support->internal_note)" rows="3" maxlength="10000" />
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" x-show="supportMethod !== 'other_benefit'" x-cloak>
                <h2 class="text-xl font-semibold text-gray-900">Payment and invoice</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">This amount is recorded as the payment and used to create the sponsor’s invoice or receipt.</p>
                <div class="mt-5 max-w-sm">
                    <x-ui.input name="value_amount" label="Payment amount (AUD)" type="number" min="0.01" step="0.01" :value="old('value_amount', $support->value_amount)" info="Required when money has been received." x-bind:required="supportMethod !== 'other_benefit'" x-bind:disabled="supportMethod === 'other_benefit'" />
                </div>
                <div class="mt-4">
                    <input type="hidden" name="send_invoice_email" value="0" x-bind:disabled="supportMethod === 'other_benefit'">
                    <x-ui.checkbox name="send_invoice_email" label="Email the invoice or receipt to the sponsor" :checked="(bool) old('send_invoice_email', false)" class="mb-0" x-model="sendInvoiceEmail" x-bind:disabled="supportMethod === 'other_benefit'" />
                    <p class="mt-1 pl-10 text-xs text-gray-500">Off by default. Turn this on to send the invoice or receipt to the email address above.</p>
                </div>
                <div class="mt-5 rounded-xl bg-gray-50 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Invoice and tax details</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">These details are saved with the sponsor and copied to their invoice or receipt. Add a full billing address for payments of ${{ number_format($recipientThreshold, 2) }} AUD or more. All billing and tax details are private.</p>
                    <div class="mt-4 grid gap-x-4 md:grid-cols-2">
                        <x-ui.input name="billing_address" label="Billing address" x-model="sponsorDetails.billing_address" maxlength="255" />
                        <x-ui.input name="billing_address2" label="Address line 2" x-model="sponsorDetails.billing_address2" maxlength="255" />
                        <x-ui.input name="billing_city" label="City / suburb" x-model="sponsorDetails.billing_city" maxlength="120" />
                        <x-ui.input name="billing_state" label="State / region" x-model="sponsorDetails.billing_state" maxlength="120" />
                        <x-ui.input name="billing_postcode" label="Postcode" x-model="sponsorDetails.billing_postcode" maxlength="40" />
                        <x-ui.input name="abn" label="ABN (Australian organisations)" :value="old('abn', $sponsor->abn)" maxlength="20" />
                        <x-ui.input name="foreign_tax_id" label="Overseas tax ID (optional)" :value="old('foreign_tax_id', $sponsor->foreign_tax_id)" maxlength="100" />
                        <div class="mb-4 flex items-center">
                            <input type="hidden" name="non_resident_declaration" value="0">
                            <x-ui.checkbox name="non_resident_declaration" label="Confirm this overseas sponsor is a non-resident" :checked="old('non_resident_declaration', $sponsor->non_resident_declaration ?? false)" class="mb-0" />
                        </div>
                    </div>
                </div>
            </section>

            <x-ui.editor-actions class="justify-end gap-3">
                <x-ui.button color="outline" href="{{ $sponsor->exists ? route('admin.sponsorship.sponsor.show', $sponsor) : route('admin.sponsorship.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $editing ? 'Save changes' : 'Add sponsor' }}</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
