<x-layout>
    <x-admin.sponsorship-mast title="Checkout settings" />
    <x-container class="py-5 sm:py-8">
        <form method="POST" action="{{ route('admin.sponsorship.checkout.update') }}" class="mx-auto max-w-3xl" data-record-form>
            @csrf @method('PUT')
            <div class="grid gap-x-4 sm:grid-cols-2">
                <input type="hidden" name="sponsorship_enabled" value="0">
                <x-ui.checkbox name="sponsorship_enabled" label="Accept card sponsorships" :checked="$project->sponsorship_enabled" class="mb-4" />
                <input type="hidden" name="allow_custom_amount" value="0">
                <x-ui.checkbox name="allow_custom_amount" label="Allow custom one-time amounts" :checked="$project->allow_custom_amount" class="mb-4" />
                <x-ui.input name="custom_amount_min" label="Custom amount minimum (AUD)" type="number" min="0.01" step="0.01" :value="$project->custom_amount_min" required />
                <x-ui.input name="custom_amount_max" label="Custom amount maximum (AUD)" type="number" min="0.01" step="0.01" :value="$project->custom_amount_max" required />
                <p class="sm:col-span-2 mt-2 text-sm leading-5 text-gray-600">Monthly sponsorships use the saved Square card and are charged in full at sign-up. Renewals follow the calendar date; month-end sign-ups renew on each month's final day, and dates missing from shorter months use that month's final day.</p>
            </div>
            <x-ui.editor-actions class="justify-end gap-3">
                <x-ui.button color="outline" type="button" data-close-dialog>Cancel</x-ui.button>
                <x-ui.button type="submit">Save checkout settings</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
