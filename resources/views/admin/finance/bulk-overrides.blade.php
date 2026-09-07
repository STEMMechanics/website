<form method="POST" action="{{ route('admin.allocation-overrides.apply', ['kind' => $kind]) }}" data-bulk-save
    x-data="Object.assign(SM.allocationTally(@js(['values' => $categories->mapWithKeys(fn ($category) => [$category->id => '0.00'])->all(), 'total' => 10000, 'enabled' => false, 'exact' => true, 'message' => 'Cost centre percentages must total exactly 100%.'])), { changeSupplier: false, changeDescription: false, changeDate: false })">
    @csrf
    @foreach($ids as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
    <div class="space-y-5 p-5">
        <p class="text-sm text-slate-600">Edit {{ count($ids) }} selected {{ $kind }}. Unchecked fields stay unchanged.</p>
        @if($kind === 'expenses')
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="font-semibold">Fields</h2>
                <div>
                    <x-ui.checkbox name="change_supplier" value="1" label="Change supplier" x-model="changeSupplier" />
                    <x-ui.input-control name="supplier" aria-label="Supplier" placeholder="Supplier" maxlength="255" x-bind:disabled="!changeSupplier" x-bind:required="changeSupplier" class="mt-2" />
                </div>
                <div>
                    <x-ui.checkbox name="change_description" value="1" label="Change description" x-model="changeDescription" />
                    <x-ui.input-control name="description" aria-label="Description" placeholder="Description" maxlength="255" x-bind:disabled="!changeDescription" x-bind:required="changeDescription" class="mt-2" />
                </div>
                <div>
                    <x-ui.checkbox name="change_paid_on" value="1" label="Change date" x-model="changeDate" />
                    <x-ui.input-control type="date" name="paid_on" aria-label="Date" x-bind:disabled="!changeDate" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-500">Leave blank to clear the date on selected expenses.</p>
                </div>
            </section>
        @endif
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="font-semibold">Cost centre allocation</h2>
            <x-ui.checkbox name="allocation_override" value="1" label="Override allocations" x-model="enabled" />
            <p class="text-sm text-slate-600">Apply these percentages to each total excluding GST. Enabling overrides replaces existing allocations. Fill one field to 100% to use a single cost centre.</p>
            <x-finance.allocation-fields :categories="$categories" prefix="percentages" id-prefix="bulk-percentage" :columns="1" :percentage="true" />
        </section>
    </div>
    <div class="sm-dialog-footer">
        <x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button>
        <x-ui.button type="submit" x-bind:disabled="!valid || !(enabled || changeSupplier || changeDescription || changeDate)">Save changes</x-ui.button>
    </div>
</form>
