@php
    $details = (array) $checkout['details'];
    $paymentChoice = (array) $checkout['payment'];
    $frequency = $paymentChoice['frequency'] ?? 'one_time';
    $selectedOption = $project->options->firstWhere('id', (int) ($paymentChoice['option_id'] ?? 0));
    $amount = $selectedOption ? (float) $selectedOption->amount : (float) ($paymentChoice['custom_amount'] ?? 0);
    $amountLabel = $selectedOption?->label ?: ($benefitOption ? 'Custom · includes '.$benefitOption->label.' benefits' : 'Custom sponsorship');
    $logoRecognition = (bool) $benefitOption?->recognition_enabled
        && $majorRecognitionLevel
        && $amount >= (float) $majorRecognitionLevel->minimum_total;
@endphp
<x-layout title="Secure checkout — Sponsor STEMMechanics">
    <x-mast title="Business Sponsorship" description="Review your sponsorship and payment method." />
    <x-container class="mx-auto max-w-3xl py-6 sm:py-9">
        <x-sponsorship-stepper :current="3" />
        @if(!$monthlyEnabled && $frequency === 'monthly')
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Monthly card payments are not available right now. You can still request monthly invoices below.</div>
        @endif
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <x-sponsorship-flow-heading icon="fa-solid fa-credit-card" :title="$invoiceAvailable ? 'How would you like to pay?' : 'Complete your sponsorship'" :description="$invoiceAvailable ? ($frequency === 'monthly' ? 'Pay by card or receive a monthly invoice by email.' : 'Pay by card or request a one-time invoice.') : 'Pay securely by card in AUD.'" />

            <form id="sponsorship-checkout" method="POST" action="{{ route('sponsor.process') }}" enctype="multipart/form-data" class="mt-7 space-y-5" x-data="sponsorshipSquareCheckout({ squareEnabled: @js($squareEnabled), invoiceAvailable: @js($invoiceAvailable), applicationId: @js($squareApplicationId), locationId: @js($squareLocationId), environment: @js($squareEnvironment), frequency: @js($frequency), paymentMethod: @js(old('payment_method', 'square')), billingContact: @js([
                'email' => data_get($checkout, 'details.email', ''),
                'contact_name' => data_get($checkout, 'details.contact_name', ''),
                'country' => data_get($checkout, 'details.country', ''),
                'billing_address' => data_get($checkout, 'details.billing_address', ''),
                'billing_address2' => data_get($checkout, 'details.billing_address2', ''),
                'billing_city' => data_get($checkout, 'details.billing_city', ''),
                'billing_state' => data_get($checkout, 'details.billing_state', ''),
                'billing_postcode' => data_get($checkout, 'details.billing_postcode', ''),
            ]) })" @submit.prevent="submitForm($event)">
                @csrf

            <dl class="mt-5 divide-y divide-gray-100 rounded-2xl border border-gray-200 px-4">
                <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600">Organisation</dt><dd class="text-right text-sm font-medium text-gray-900 pr-6">{{ $details['company_name'] ?? 'Organisation' }}</dd></div>
                <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600">Contact</dt><dd class="text-right text-sm font-medium text-gray-900">{{ $details['contact_name'] }} · {{ $details['email'] }} <a href="{{ route('sponsor.details') }}" class="ml-2 w-4 font-small text-primary-color hover:text-primary-color-dark"><i class="fa-solid fa-pencil"></i></a></dd></div>
                <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600">Sponsorship</dt><dd class="text-right text-sm font-medium text-gray-900 pr-6">{{ $amountLabel }} · {{ $frequency === 'monthly' ? 'Monthly' : 'One-time' }}</dd></div>
                @if($frequency === 'monthly')
                    <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600">Monthly sponsorship</dt><dd class="text-right text-sm font-semibold text-gray-900">AUD ${{ number_format($amount, 2) }} / month <a href="{{ route('sponsor.payment') }}" class="ml-2 w-4 font-small text-primary-color hover:text-primary-color-dark"><i class="fa-solid fa-pencil"></i></a></dd></div>
                    <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600" x-text="paymentMethod === 'invoice' ? 'First invoice' : 'Due today'"></dt><dd class="text-right text-lg font-semibold text-gray-900 pr-6">AUD ${{ number_format((float) $initialMonthlyPaymentAmount, 2) }}</dd></div>
                    <p x-cloak x-show="paymentMethod === 'square'" class="py-3 text-sm leading-5 text-gray-600">Your next payment is due {{ \Illuminate\Support\Carbon::parse($nextMonthlyPaymentDate)->format('j M Y') }}, then on {{ $monthlyBillingLabel }}.</p>
                    <p x-cloak x-show="paymentMethod === 'invoice'" class="py-3 text-sm leading-5 text-gray-600">After you confirm your email, we’ll send the first invoice. Further invoices will be sent monthly, starting {{ \Illuminate\Support\Carbon::parse($nextMonthlyPaymentDate)->format('j M Y') }}, until you cancel.</p>
                @else
                    <div class="flex flex-wrap justify-between gap-2 py-3"><dt class="text-sm text-gray-600">Total</dt><dd class="text-right text-lg font-semibold text-gray-900">AUD ${{ number_format($amount, 2) }}</dd></div>
                @endif
            </dl>

            @if($errors->any())
                <div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700" role="alert">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
                @if($invoiceAvailable)
                    <fieldset>
                        <legend class="sr-only">Payment method</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition" x-bind:class="paymentMethod === 'square' ? 'border-primary-color bg-sky-50 ring-1 ring-primary-color' : 'border-gray-200 bg-white hover:bg-gray-50'">
                                <input type="radio" name="payment_method" value="square" x-model="paymentMethod" x-on:change="paymentMethodChanged()" @disabled(!$squareEnabled) class="mt-1 text-primary-color focus:ring-primary-color">
                                <span><span class="block font-semibold text-gray-900">Pay by card</span><span class="mt-1 block text-sm leading-5 text-gray-600">Pay securely through Square.</span></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition" x-bind:class="paymentMethod === 'invoice' ? 'border-primary-color bg-sky-50 ring-1 ring-primary-color' : 'border-gray-200 bg-white hover:bg-gray-50'">
                                <input type="radio" name="payment_method" value="invoice" x-model="paymentMethod" class="mt-1 text-primary-color focus:ring-primary-color">
                                <span><span class="block font-semibold text-gray-900">Pay by invoice</span><span class="mt-1 block text-sm leading-5 text-gray-600" x-text="frequency === 'monthly' ? 'Confirm your email first. We’ll send an invoice now and another each month until cancelled.' : 'Confirm your email first, then we’ll send an invoice with payment options.'"></span></span>
                            </label>
                        </div>
                        @error('payment_method')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </fieldset>
                @endif

                @if($benefitOption?->recognition_enabled)
                    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                        <h3 class="font-semibold text-gray-900">Public recognition</h3>
                        <div class="mt-4 grid gap-x-4 sm:grid-cols-2">
                            <div class="mb-4 sm:col-span-2">
                                <input type="hidden" name="recognition_public" value="0">
                                <x-ui.checkbox name="recognition_public" label="List our organisation as a sponsor" :checked="old('recognition_public', data_get($checkout, 'details.recognition_public', false))" class="mb-0" />
                            </div>
                            <x-ui.input name="display_name" label="Name shown publicly (optional)" :value="old('display_name', data_get($checkout, 'details.display_name', ''))" maxlength="255" info="Your organisation name is used when this is left blank." />
                            <x-ui.input type="text" inputmode="url" name="website_url" label="Website URL (optional)" placeholder="example.com.au" :value="old('website_url', data_get($checkout, 'details.website_url', ''))" maxlength="2048" />
                            @if($logoRecognition)
                                <x-ui.input class="sm:col-span-2" type="file" name="logo" label="Logo (optional)" accept="image/png,image/jpeg,image/webp" info="Supported formats: PNG, JPEG or WebP." />
                            @endif
                            @foreach(['recognition_public', 'display_name', 'website_url', 'logo'] as $field)@error($field)<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror @endforeach
                        </div>
                    </section>
                @endif

                <div x-cloak x-show="paymentMethod === 'square'" class="rounded-2xl border border-gray-200 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">Card payment</h3>
                        <x-ui.badge color="success">Secure checkout</x-ui.badge>
                    </div>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Square handles your card details securely. STEMMechanics never receives or stores your card number or security code.</p>
                    <div x-ref="squareCardContainer" class="mt-4 min-h-[88px] rounded-xl bg-white"></div>
                    <input type="hidden" name="source_id" x-model="sourceId">
                    <p x-show="errorMessage" x-text="errorMessage" class="mt-2 text-sm text-red-600" role="alert"></p>
                    @error('source_id')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>

                @if($invoiceAvailable)
                    <div x-cloak x-show="paymentMethod === 'invoice'" class="rounded-2xl border border-sky-200 bg-sky-50 p-4 sm:p-5">
                        <h3 class="font-semibold text-gray-900" x-text="frequency === 'monthly' ? 'Request monthly invoices' : 'Request an invoice'"></h3>
                        <p class="mt-1 text-sm leading-6 text-gray-700">We’ll email a confirmation link to <span class="font-medium">{{ $details['email'] }}</span>. Once you confirm, we’ll create and send the invoice with payment instructions<span x-show="frequency === 'monthly'"> and email a new invoice each month until you cancel</span>.</p>
                    </div>
                @endif
                @unless($squareEnabled)
                    <p x-cloak x-show="paymentMethod === 'square'" class="text-sm text-amber-800">Card sponsorships are temporarily unavailable.@if($invoiceAvailable) Choose invoice to request a one-time or monthly invoice.@else Please contact STEMMechanics to arrange sponsorship.@endif</p>
                @endunless

                <p x-cloak x-show="paymentMethod === 'square' || frequency === 'monthly'" class="text-sm leading-6 text-gray-600">Monthly sponsorship can be managed or cancelled on the <a href="{{ route('sponsor.manage.request') }}" class="font-medium text-primary-color underline hover:no-underline">Manage My Sponsorship</a> page.</p>
                <div class="flex items-center justify-between gap-3">
                    <x-ui.button color="outline" href="{{ route('sponsor.payment') }}">Back</x-ui.button>
                    <x-ui.button type="submit" x-bind:disabled="isSubmitting || (paymentMethod === 'square' && (!squareEnabled || !card))">
                        <span x-show="!isSubmitting && paymentMethod === 'square'">Sponsor — AUD ${{ number_format($frequency === 'monthly' ? (float) $initialMonthlyPaymentAmount : $amount, 2) }}{{ $frequency === 'monthly' ? ' today' : '' }}</span>
                        <span x-show="!isSubmitting && paymentMethod === 'invoice' && frequency === 'one_time'">Email confirmation link</span>
                        <span x-show="!isSubmitting && paymentMethod === 'invoice' && frequency === 'monthly'">Request monthly invoices</span>
                        <span x-show="isSubmitting" x-cloak>Processing…</span>
                    </x-ui.button>
                </div>
                <x-altcha-proof />
            </form>
        </section>
    </x-container>

    @if($squareEnabled)
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
    @endif
    @include('sponsorship.partials.square-checkout-script')
</x-layout>
