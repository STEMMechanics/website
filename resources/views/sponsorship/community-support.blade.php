@php
    $savedFrequency = old('frequency', $savedFrequency ?? 'one_time');
    $savedOption = old('option_id', $savedOption ?? '');
    $savedCustomAmount = old('custom_amount', $savedCustomAmount ?? '');
    $savedCountry = old('country', $user?->billing_country ?? 'Australia');
    $savedChoice = (string) old('sponsorship_choice', filled($savedOption) ? (string) $savedOption : (filled($savedCustomAmount) ? 'custom' : ''));
    $majorThreshold = $majorRecognitionLevel ? (float) $majorRecognitionLevel->minimum_total : null;
    $communitySupportOptions = $project->options
        ->filter(fn ($option) => $option->frequency !== 'monthly' || $monthlyEnabled)
        ->map(function ($option) {
            $monthlySchedule = $option->frequency === 'monthly'
                ? app(\App\Services\SponsorshipBillingScheduleService::class)->initialPayment((float) $option->amount)
                : null;

            return [
            'value' => (string) $option->id,
            'frequency' => $option->frequency,
            'recognition' => (bool) $option->recognition_enabled,
            'logoRecognition' => (bool) $option->recognition_enabled && $majorThreshold !== null && (float) $option->amount >= $majorThreshold,
            'amount' => number_format((float) $option->amount, 2, '.', ''),
            'label' => '$'.number_format((float) $option->amount, 2),
            'firstPayment' => $monthlySchedule ? number_format($monthlySchedule['amount_cents'] / 100, 2, '.', '') : null,
            'firstPaymentDate' => $monthlySchedule ? \Illuminate\Support\Carbon::parse($monthlySchedule['next_payment_date'])->format('j M Y') : null,
            'billingLabel' => $monthlySchedule['billing_label'] ?? null,
        ];
        })
        ->values()
        ->all();
    if ($project->allow_custom_amount) {
            $communitySupportOptions[] = ['value' => 'custom', 'frequency' => 'one_time', 'recognition' => false, 'logoRecognition' => false, 'amount' => '', 'label' => 'Custom amount'];
    }
@endphp
<x-layout title="Community Support — Sponsor STEMMechanics">
    <x-mast title="Community Support" description="A simple way to sponsor STEMMechanics." />
    <x-container class="mx-auto max-w-3xl py-6 sm:py-9">
        <section class="mt-4 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-xl text-primary-color"><i class="fa-solid fa-handshake" aria-hidden="true"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Community Support</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">Choose a one-time or monthly sponsorship, add your email and pay securely through Square. We’ll email an invoice or receipt after each successful payment.</p>
                </div>
            </div>

            <form id="community-support-sponsorship-checkout" method="POST" enctype="multipart/form-data" action="{{ route('sponsor.community-support.process') }}" class="mt-6 space-y-6" x-data="sponsorshipCommunitySupportCheckout({ squareEnabled: @js($squareEnabled), applicationId: @js($squareApplicationId), locationId: @js($squareLocationId), environment: @js($squareEnvironment) }, { options: @js($communitySupportOptions), savedChoice: @js($savedChoice), savedFrequency: @js($savedFrequency), savedOption: @js((string) $savedOption), savedCustomAmount: @js((string) $savedCustomAmount) })" @submit.prevent="submitForm($event)">
                @csrf

                <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:p-5">
                    <h2 class="font-semibold text-gray-900">Choose an amount</h2>
                    <div class="mt-4 grid gap-x-4 sm:grid-cols-2">
                        <x-ui.select name="frequency" label="Frequency" x-model="frequency" x-on:change="changeFrequency($event)" required>
                            <option value="one_time" @selected($savedFrequency === 'one_time')>One-time</option>
                            @if($monthlyEnabled && $project->options->contains(fn ($option) => $option->frequency === 'monthly'))
                                <option value="monthly" @selected($savedFrequency === 'monthly')>Monthly</option>
                            @endif
                        </x-ui.select>
                        <input type="hidden" name="option_id" x-model="optionId" value="{{ $savedOption }}">
                        <x-ui.select name="sponsorship_choice" label="Amount (AUD)" x-model="selectedChoice" x-on:change="changeAmount($event)" required>
                            <option value="" disabled @selected($savedChoice === '')>Choose an amount</option>
                            <template x-for="option in availableOptions()" :key="option.value">
                                <option :value="option.value" :data-amount="option.amount" x-text="option.label"></option>
                            </template>
                        </x-ui.select>
                        @if($project->allow_custom_amount)
                            <div class="sm:col-span-2" x-cloak x-show="frequency === 'one_time' && selectedChoice === 'custom'">
                                <x-ui.input type="number" name="custom_amount" label="Custom amount (AUD)" :value="$savedCustomAmount" min="{{ $customAmountMinimum }}" max="{{ $project->custom_amount_max }}" step="0.01" x-model="customAmount" placeholder="Enter an amount" info="Enter an amount from ${{ number_format($customAmountMinimum, 2) }} to ${{ number_format((float) $project->custom_amount_max, 2) }}." x-bind:disabled="frequency !== 'one_time' || selectedChoice !== 'custom'" x-bind:required="frequency === 'one_time' && selectedChoice === 'custom'" />
                            </div>
                        @endif
                    </div>
                    <section x-cloak x-show="recognitionEnabled()" class="mt-3 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                        <h3 class="font-semibold text-gray-900">Public recognition</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">This sponsorship tier includes public recognition. It stays private unless you choose to opt in.</p>
                        <div class="mt-4 grid gap-x-4 sm:grid-cols-2">
                            <div class="mb-4 sm:col-span-2">
                                <input type="hidden" name="recognition_public" value="0" x-bind:disabled="!recognitionEnabled()">
                                <x-ui.checkbox name="recognition_public" label="Show my name publicly as a supporter" :checked="old('recognition_public', false)" class="mb-0" x-bind:disabled="!recognitionEnabled()" />
                            </div>
                            <x-ui.input name="display_name" label="Name shown publicly (optional)" :value="old('display_name')" maxlength="255" x-bind:disabled="!recognitionEnabled()" />
                            <x-ui.input type="text" inputmode="url" name="website_url" label="Website URL (optional)" placeholder="example.com.au" :value="old('website_url')" maxlength="2048" x-bind:disabled="!recognitionEnabled()" />
                            <div class="sm:col-span-2" x-cloak x-show="logoRecognition()">
                                <x-ui.input type="file" name="logo" label="Logo or avatar (optional)" accept="image/png,image/jpeg,image/webp" info="Supported formats: PNG, JPEG or WebP" x-bind:disabled="!logoRecognition()" />
                            </div>
                            @foreach(['recognition_public', 'display_name', 'website_url', 'logo'] as $field)@error($field)<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror @endforeach
                        </div>
                    </section>
                    @error('option_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs leading-5 text-gray-600">Monthly sponsorships can be changed or cancelled later using the <a href="{{ route('sponsor.manage.request') }}" class="font-medium text-primary-color underline hover:no-underline">Manage My Sponsorship</a> page.</p>
                    <p x-cloak x-show="frequency === 'monthly' && selectedOption()" class="mt-2 text-xs leading-5 text-gray-600">Your first payment is AUD $<span x-text="selectedOption()?.firstPayment"></span> today. You will then be charged on <span x-text="selectedOption()?.billingLabel"></span>, until cancelled.</p>
                </section>

                <section class="rounded-2xl border border-gray-200 p-4 sm:p-5">
                    <h2 class="font-semibold text-gray-900">Where should we send your receipt?</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">We’ll issue the invoice without recipient details. After payment, you can add a name, organisation or address if you need them shown.</p>
                    <div class="mt-4 grid gap-x-4 sm:grid-cols-2" x-data="{ country: @js($savedCountry), isAustralia() { return ['australia', 'au', 'aus'].includes((this.country || '').trim().toLowerCase()); } }">
                        <x-ui.input type="email" name="email" label="Email address" :value="old('email', $user?->email ?? '')" autocomplete="email" maxlength="255" required />
                        <x-ui.input name="contact_name" label="Name (optional)" :value="old('contact_name', $user?->getName() ?? '')" autocomplete="name" maxlength="255" />
                        <x-ui.input name="country" label="Country" :value="$savedCountry" autocomplete="country-name" maxlength="120" x-model="country" required />
                        <div class="mb-4 flex items-end" x-show="!isAustralia()" x-cloak>
                            <input type="hidden" name="non_resident_declaration" value="0">
                            <x-ui.checkbox name="non_resident_declaration" label="I confirm the sponsor is a non-resident of Australia" :checked="old('non_resident_declaration', false)" class="mb-0" />
                        </div>
                    </div>
                    @error('non_resident_declaration')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="-mt-1 text-xs leading-5 text-gray-500">We use your declared country and payment details to work out whether GST applies.</p>
                </section>

                <section class="rounded-2xl border border-gray-200 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">Pay by card</h2>
                        <x-ui.badge color="success">Secure checkout</x-ui.badge>
                    </div>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Square securely handles your card details. STEMMechanics never receives or stores your card number or security code.</p>
                    <div x-ref="squareCardContainer" class="mt-4 min-h-[88px] rounded-xl bg-white"></div>
                    <input type="hidden" name="source_id" x-model="sourceId">
                    <p x-show="errorMessage" x-text="errorMessage" class="mt-2 text-sm text-red-600" role="alert"></p>
                    @error('source_id')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </section>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-ui.button type="submit" x-bind:disabled="isSubmitting || !squareEnabled || !card">
                        <span x-show="!isSubmitting" x-text="submitLabel()">Sponsor now</span>
                        <span x-show="isSubmitting" x-cloak>Processing…</span>
                    </x-ui.button>
                </div>
                @unless($squareEnabled)
                    <p class="text-sm text-amber-800">Card sponsorships are temporarily unavailable. Please check back soon.</p>
                @endunless
            </form>
        </section>
    </x-container>

    @if($squareEnabled)
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
    @endif
    @include('sponsorship.partials.square-checkout-script')
    @include('sponsorship.partials.amount-choice-script')
</x-layout>
