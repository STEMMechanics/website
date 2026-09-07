<form method="POST" action="{{ route('admin.allocation-overrides.apply', ['kind' => $kind]) }}" data-bulk-save
    x-data="Object.assign(SM.allocationTally(@js(['values' => $categories->mapWithKeys(fn ($category) => [$category->id => '0.00'])->all(), 'total' => 10000, 'enabled' => false, 'exact' => true, 'message' => 'Cost centre percentages must total exactly 100%.'])), { fields: @js((object) $common), initial: @js((object) $common) })">
    @csrf
    @foreach($ids as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
    <div class="space-y-5 p-5">
        <p class="text-sm text-slate-600">Edit {{ count($ids) }} selected {{ $kind }}. Only fields you change are applied.</p>
        @if($kind === 'expenses')
            <section class="space-y-4">
                <h2 class="font-semibold">Fields</h2>
                <p class="text-sm text-slate-500">Matching values are shown. Mixed values are marked Mixed. Only values you change are applied to every selected expense.</p>
                @foreach(['supplier' => 'Supplier', 'description' => 'Description', 'paid_on' => 'Date'] as $field => $label)
                    <input type="hidden" name="change_{{ $field }}" x-bind:value="fields.{{ $field }} !== initial.{{ $field }} ? '1' : '0'">
                    <div>
                        <label for="bulk-expense-{{ $field }}" class="mb-1 block text-sm">{{ $label }}</label>
                        <x-ui.input-control :id="'bulk-expense-'.$field" :name="$field" :type="$field === 'paid_on' ? 'date' : 'text'" :value="$common[$field]" :placeholder="$mixed[$field] ? 'Mixed' : ''" x-model="fields.{{ $field }}" />
                        @if($field === 'paid_on' && $mixed[$field])<p class="mt-1 text-xs text-slate-500" x-show="fields.paid_on === initial.paid_on">Mixed</p>@endif
                    </div>
                @endforeach
            </section>
        @endif
        <section class="space-y-4">
            <h2 class="font-semibold">Cost centre allocation</h2>
            <x-ui.checkbox name="allocation_override" value="1" label="Override allocations" x-model="enabled" />
            <p class="text-sm text-slate-600">Apply these percentages to each total excluding GST. Enabling overrides replaces existing allocations. Fill one field to 100% to use a single cost centre.</p>
            <x-finance.allocation-fields :categories="$categories" prefix="percentages" id-prefix="bulk-percentage" :columns="1" :percentage="true" />
        </section>
    </div>
    <div class="sm-dialog-footer">
        <x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button>
        <x-ui.button type="submit" x-bind:disabled="!valid || !(enabled || Object.keys(fields).some(field => fields[field] !== initial[field]))">Save changes</x-ui.button>
    </div>
</form>
