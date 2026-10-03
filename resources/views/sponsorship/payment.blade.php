@php
    $savedPayment = (array) ($checkout['payment'] ?? []);
    $savedFrequency = old('frequency', $savedPayment['frequency'] ?? 'one_time');
    $savedOption = old('option_id', $savedPayment['option_id'] ?? '');
    $savedCustomAmount = old('custom_amount', $savedPayment['custom_amount'] ?? '');
    $savedChoice = (string) old('sponsorship_choice', filled($savedOption) ? (string) $savedOption : (filled($savedCustomAmount) ? 'custom' : ''));
    $majorThreshold = $majorRecognitionLevel ? (float) $majorRecognitionLevel->minimum_total : null;
    $businessOptions = $project->options->map(function ($option) use ($majorThreshold) {
        $amount = (float) $option->amount;
        $benefits = preg_split('/\R/u', (string) $option->additional_benefits) ?: [];

        return [
            'value' => (string) $option->id,
            'frequency' => $option->frequency,
            'recognition' => (bool) $option->recognition_enabled,
            'homepageRecognition' => (bool) $option->recognition_enabled && $majorThreshold !== null && $amount >= $majorThreshold,
            'homepageRecognitionText' => $option->frequency === 'monthly'
                ? 'Homepage feature while your monthly sponsorship is active'
                : 'Homepage feature in the month your sponsorship is received',
            'amount' => number_format($amount, 2, '.', ''),
            'priceLabel' => 'AUD $'.number_format($amount, 2).($option->frequency === 'monthly' ? ' / month' : ''),
            'label' => $option->label,
            'benefits' => collect($benefits)->map(fn ($benefit) => trim($benefit))->filter()->values()->all(),
        ];
    })->values()->all();
    if ($project->allow_custom_amount) {
        $businessOptions[] = [
            'value' => 'custom',
            'frequency' => 'one_time',
            'recognition' => false,
            'homepageRecognition' => false,
            'amount' => '',
            'priceLabel' => 'Choose your amount',
            'label' => 'Custom amount',
            'benefits' => [],
        ];
    }
@endphp
<x-layout title="Choose a sponsorship — STEMMechanics">
    <x-mast title="Business Sponsorship" description="Choose a sponsorship level for your organisation." />
    <x-container class="mx-auto max-w-5xl py-6 sm:py-9">
        <x-sponsorship-stepper :current="2" />
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <x-sponsorship-flow-heading icon="fa-solid fa-coins" title="Choose your sponsorship" description="Compare the options, then choose one-time or monthly sponsorship." />

            <form method="POST" action="{{ route('sponsor.payment.save') }}" enctype="multipart/form-data" class="mt-6" x-data="sponsorshipBusinessAmountChoice({ options: @js($businessOptions), savedFrequency: @js($savedFrequency), savedChoice: @js($savedChoice), savedOption: @js((string) $savedOption), savedCustomAmount: @js((string) $savedCustomAmount), monthlyAvailable: @js($monthlyEnabled), squareEnabled: @js($squareEnabled) })">
                @csrf
                <input type="hidden" name="payment_method" value="square">
                <input type="hidden" name="frequency" x-model="frequency">
                <input type="hidden" name="option_id" x-model="optionId" value="{{ $savedOption }}">

                <div class="flex flex-wrap items-end justify-end gap-3">
                    <div role="tablist" aria-label="Sponsorship frequency" class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1">
                        <button type="button" role="tab" x-bind:aria-selected="frequency === 'one_time'" x-bind:class="frequency === 'one_time' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'" x-on:click="changeFrequency('one_time')" class="rounded-lg px-4 py-2 text-sm font-semibold transition">One-time</button>
                        <button type="button" role="tab" x-cloak x-show="monthlyAvailable" x-bind:aria-selected="frequency === 'monthly'" x-bind:class="frequency === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'" x-on:click="changeFrequency('monthly')" class="rounded-lg px-4 py-2 text-sm font-semibold transition">Monthly</button>
                    </div>
                </div>

                <div role="tabpanel" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <template x-for="option in availableOptions()" :key="option.value">
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border p-4 transition hover:border-primary-color" x-bind:class="{ 'border-primary-color bg-sky-50 ring-1 ring-primary-color': selectedChoice === String(option.value), 'border-gray-200 bg-white': selectedChoice !== String(option.value), 'col-span-full': option.value === 'custom' }">
                            <input type="radio" name="sponsorship_choice" x-bind:value="option.value" x-model="selectedChoice" x-on:change="changeAmount($event)" required class="sr-only">
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-base font-semibold text-gray-900" x-text="option.label"></span>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border" x-bind:class="selectedChoice === String(option.value) ? 'border-primary-color bg-primary-color text-white' : 'border-gray-300 text-transparent'" aria-hidden="true"><i class="fa-solid fa-check text-[10px]"></i></span>
                            </span>
                            <span class="mt-2 text-lg font-bold text-gray-900" x-text="option.priceLabel"></span>
                            <ul class="mt-4 space-y-2 text-sm leading-5 text-gray-600">
                                <template x-if="option.recognition">
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1 text-xs text-primary-color" aria-hidden="true"></i><span>Listing on the <a href="{{ route('sponsors.index') }}" class="font-medium text-primary-color underline hover:no-underline">Sponsors page</a></span></li>
                                </template>
                                <template x-if="option.homepageRecognition">
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1 text-xs text-primary-color" aria-hidden="true"></i><span x-text="option.homepageRecognitionText"></span></li>
                                </template>
                                <template x-for="(benefit, index) in option.benefits" :key="index">
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1 text-xs text-primary-color" aria-hidden="true"></i><span x-text="benefit"></span></li>
                                </template>
                                <template x-if="option.value === 'custom'">
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-heart mt-1 text-xs text-primary-color" aria-hidden="true"></i><span>Choose an amount; the matching package benefits apply</span></li>
                                </template>
                            </ul>
                        </label>
                    </template>
                </div>
                @error('option_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('frequency')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                @if($project->allow_custom_amount)
                    <div class="mt-4 max-w-sm" x-cloak x-show="frequency === 'one_time' && selectedChoice === 'custom'">
                        <x-ui.input type="number" name="custom_amount" label="Custom amount (AUD)" :value="$savedCustomAmount" min="{{ $customAmountMinimum }}" max="{{ $project->custom_amount_max }}" step="0.01" x-model="customAmount" placeholder="Enter an amount" info="Benefits match the highest listed one-time package at or below your sponsorship amount. Enter ${{ number_format($customAmountMinimum, 2) }} to ${{ number_format((float) $project->custom_amount_max, 2) }}." x-bind:disabled="selectedChoice !== 'custom'" x-bind:required="selectedChoice === 'custom'" />
                        @error('custom_amount')<p class="-mt-2 mb-4 text-sm text-red-600">{{ $message }}</p>@enderror
                        <div x-cloak x-show="customBenefitOption()" class="mt-1 rounded-xl border border-sky-200 bg-sky-50 p-4">
                            <p class="text-sm font-medium leading-6 text-sky-950" x-text="customBenefitSummary()"></p>
                            <ul class="mt-3 space-y-2 text-sm leading-5 text-sky-900">
                                <template x-for="(benefit, index) in customBenefitDetails()" :key="index">
                                    <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1 text-xs text-primary-color" aria-hidden="true"></i><span x-text="benefit"></span></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="mt-7 flex items-center justify-between gap-3">
                    <x-ui.button color="outline" href="{{ route('sponsor.details') }}">Back</x-ui.button>
                    <x-ui.button type="submit">Continue to payment</x-ui.button>
                </div>
                <x-altcha-proof />
            </form>
        </section>
    </x-container>
    @include('sponsorship.partials.amount-choice-script')
</x-layout>
