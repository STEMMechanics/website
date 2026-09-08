@php
    $hasPricingDefaults = array_sum($allocation['suggestedTargets']) > 0;
    $linkedWorkshops = $invoice->tickets()->with('workshop')->get()->pluck('workshop')->filter()->unique('id');
@endphp
@if($linkedWorkshops->isNotEmpty())
    <p class="mb-3 text-sm text-slate-600">Ticket allocation managed by workshop.</p>
    <div class="mb-4 flex flex-wrap gap-3">@foreach($linkedWorkshops as $linkedWorkshop)<a class="text-primary-color underline" href="{{ route('admin.workshop.edit', $linkedWorkshop) }}">{{ $linkedWorkshops->count() > 1 ? $linkedWorkshop->title : 'View workshop' }}</a>@endforeach</div>
@endif
@if(! $allocation['warning'])
@php
        $inline = $inline ?? false;
        $values = $allocation['categories']->mapWithKeys(fn ($category) => [$category->id => number_format(($allocation['editorTargets'][$category->id] ?? $allocation['targets'][$category->id] ?? 0) / 100, 2, '.', '')])->all();
    @endphp
    @php($previewRules = json_decode($allocation['version']->rules, true))
    <form x-on:allocation-calculated.stop="applyCalculatorValues($event.detail)" x-on:invoice-lines-updated.window="@if($inline && $linkedWorkshops->isEmpty()) previewInvoice($event.detail, @js($previewRules), @js(json_decode($allocation['version']->prices, true))); @endif" method="POST" action="{{ route('admin.invoice.allocation.store', $invoice) }}" @if($inline) data-allocation-inline @else data-record-form @endif x-data="SM.allocationTally(@js(['values' => $values, 'total' => $allocation['total'], 'exact' => false, 'enabled' => ! $hasPricingDefaults || (bool) ($allocation['budget']->manual ?? false)]))">
        @unless($inline)
            <div class="mb-4 flex justify-end"><x-finance.allocation-calculator-button :invoice="$invoice" :inline="false" /></div>
        @endunless
        <x-finance.invoice-allocation-calculator :invoice="$invoice" :allocation="$allocation" :inline="$inline" :total-label="$linkedWorkshops->isNotEmpty() ? 'Non-ticket items excluding GST' : 'Invoice total excluding GST'" />
        @csrf
        @if($inline)<input type="hidden" name="inline" value="1">@endif
        <input type="hidden" name="budget_id" value="{{ $allocation['budget']->id ?? '' }}">
        @if(! $allocation['budget'] && $hasPricingDefaults)
            <div class="mb-5" x-data="{ versionId: @js((string) $allocation['version']->id), editorUrl: @js(route('admin.invoice.allocation.edit', $invoice)) }">
                <x-ui.select name="version_id" label="Allocation plan" x-model="versionId">
                    @foreach(\Illuminate\Support\Facades\DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->orWhere('id', $allocation['version']->id)->orderByDesc('effective_from')->orderByDesc('id')->get() as $version)
                        <option value="{{ $version->id }}">{{ $version->name }}{{ $version->archived ? ' (archived)' : '' }}</option>
                    @endforeach
                </x-ui.select>
                @if($inline)
                    <x-ui.button color="outline" data-allocation-load href="{{ route('admin.invoice.allocation.edit', [$invoice, 'inline' => 1]) }}" x-bind:href="editorUrl + '?inline=1&version_id=' + versionId">Load plan</x-ui.button>
                @else
                    <x-ui.button color="outline" data-record-editor href="{{ route('admin.invoice.allocation.edit', $invoice) }}" x-bind:href="editorUrl + '?version_id=' + versionId">Load plan</x-ui.button>
                @endif
            </div>
        @else
            <input type="hidden" name="version_id" value="{{ $allocation['version']->id }}">
        @endif
        @if($allocation['warning'])
            <p class="mb-4 text-amber-800">{{ $allocation['warning'] }}</p>
        @else
            @if($allocation['automaticWarning'])<p class="mb-4 text-sm text-amber-800">{{ $allocation['automaticWarning'] }}</p>@endif
            @if($allocation['workshopId'])
                <x-finance.supplied-options :rules="json_decode($allocation['version']->rules, true)" :categories="$allocation['categories']" :values="$allocation['assumptions']['supplied_categories'] ?? []" :venueSupplied="$allocation['assumptions']['venue_supplied'] ?? false" />
            @endif
            @if(! $inline && ! $allocation['workshopId'])
                <div class="mb-5">
                    @foreach($invoice->lines->whereIn('kind', ['workshop', 'travel']) as $line)
                        <div class="my-3 rounded-lg border border-slate-200 p-3">
                            <p class="mb-3 text-sm font-semibold">{{ $line->description }} · Quantity {{ $line->quantity }}</p>
                            @if($line->kind === 'workshop')
                                <div class="grid gap-x-4 sm:grid-cols-2">
                                    <x-ui.input :name="'line_details['.$line->id.'][workshop_hours]'" label="Hours" type="number" step="0.01" min="0.01" max="24" :value="$line->details_json['workshop']['hours'] ?? ''" />
                                    <x-ui.input :name="'line_details['.$line->id.'][workshop_seats]'" label="Seats" type="number" step="1" min="1" max="10000" :value="$line->details_json['workshop']['seats'] ?? ''" />
                                </div>
                                <x-finance.supplied-options :rules="json_decode($allocation['version']->rules, true)" :categories="$allocation['categories']" kind="workshop" :prefix="'line_details['.$line->id.'][supplied_categories]'" :values="$line->details_json['workshop']['supplied_categories'] ?? []" :venueSupplied="$line->details_json['workshop']['venue_supplied'] ?? true" />
                            @else
                                <x-finance.supplied-options kind="travel" :rules="json_decode($allocation['version']->rules, true)" :categories="$allocation['categories']" :prefix="'line_details['.$line->id.'][supplied_categories]'" :values="$line->details_json['travel']['supplied_categories'] ?? []" />
                                <x-ui.input :name="'line_details['.$line->id.'][travel_units]'" label="Billable 15-minute units" type="number" min="0" step="1" :value="$line->details_json['travel']['billable_units'] ?? ''" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            @if($hasPricingDefaults)
            <x-ui.checkbox name="use_defaults" value="1" x-on:change="enabled = !$event.target.checked" label="Use pricing defaults" x-bind:checked="!enabled" />
            <p class="mb-4 mt-2 text-xs text-slate-600">Untick to enter a manual override.</p>
            @endif
            <x-finance.allocation-fields :categories="$allocation['categories']" prefix="targets" idPrefix="invoice-allocation" :exact="false" :totalLabel="$linkedWorkshops->isNotEmpty() ? 'Non-ticket items excluding GST' : null" :shortfall="true" :columns="$inline ? 1 : 2" />
            @if($inline)<p x-show="previewDirty" x-cloak class="mt-3 text-sm text-slate-600">Preview of unsaved invoice changes. Save the invoice before applying automatic allocations.</p>@endif
            <x-finance.save x-bind:disabled="previewDirty && !enabled">Save allocation</x-finance.save>
        @endif
    </form>

@endif
