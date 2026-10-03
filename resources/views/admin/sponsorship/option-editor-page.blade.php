<x-layout>
    <x-admin.sponsorship-mast :title="$option->exists ? 'Edit sponsorship amount' : 'Add sponsorship amount'" />
    <x-container class="py-5 sm:py-8">
        <form method="POST" action="{{ $option->exists ? route('admin.sponsorship.option.update', $option) : route('admin.sponsorship.option.store') }}" class="mx-auto max-w-2xl" data-record-form>
            @csrf
            @if($option->exists) @method('PUT') @endif
            <x-ui.input name="label" label="Checkout label" :value="$option->label" maxlength="100" required />
            <x-ui.input type="textarea" name="additional_benefits" label="Additional benefits (optional)" :value="$option->additional_benefits" maxlength="2000" info="One benefit per line. Only list benefits STEMMechanics has agreed to provide." />
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-ui.select name="checkout_group" label="Show in checkout" :value="old('checkout_group', $option->checkout_group ?? 'both')" required>
                    <option value="{{ \App\Models\SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT }}" @selected(old('checkout_group', $option->checkout_group ?? 'both') === \App\Models\SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT)>Community Support</option>
                    <option value="business" @selected(old('checkout_group', $option->checkout_group ?? 'both') === 'business')>Business Sponsorship</option>
                    <option value="both" @selected(old('checkout_group', $option->checkout_group ?? 'both') === 'both')>Both</option>
                </x-ui.select>
                <x-ui.select name="frequency" label="Frequency" :value="old('frequency', $option->frequency ?? 'one_time')" required>
                    <option value="one_time" @selected(old('frequency', $option->frequency ?? 'one_time') === 'one_time')>One-time</option>
                    <option value="monthly" @selected(old('frequency', $option->frequency ?? 'one_time') === 'monthly')>Monthly</option>
                </x-ui.select>
                <x-ui.input name="amount" label="Amount (AUD)" type="number" min="0.01" max="1000000" step="0.01" :value="old('amount', $option->amount)" required />
                <p class="sm:col-span-2 text-sm leading-5 text-gray-600">Monthly sponsorships are charged in full at sign-up. Renewals use the same calendar date; if it falls on the month's final day, renewals stay on each month's final day. Otherwise, dates missing from shorter months use that month's final day, then return to the signup date when possible.</p>
                <div class="sm:col-span-2">
                    <input type="hidden" name="recognition_enabled" value="0">
                    <x-ui.checkbox name="recognition_enabled" label="Allow public recognition for this sponsorship tier" :checked="old('recognition_enabled', $option->recognition_enabled ?? false)" class="mb-0" />
                    <p class="mt-1 text-sm text-gray-500">Sponsors can manage their public listing only after a successful payment at this amount.</p>
                </div>
                <x-ui.input name="sort_order" label="Display order" type="number" min="0" max="100000" :value="old('sort_order', $option->sort_order ?? 0)" />
                <div class="mb-4 flex items-end">
                    <input type="hidden" name="enabled" value="0">
                    <x-ui.checkbox name="enabled" label="Available on the Sponsor page" :checked="old('enabled', $option->exists ? $option->enabled : true)" class="mb-0" />
                </div>
            </div>
            <x-ui.editor-actions class="justify-end gap-3">
                <x-ui.button color="outline" type="button" data-close-dialog>Cancel</x-ui.button>
                <x-ui.button type="submit">Save amount</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
