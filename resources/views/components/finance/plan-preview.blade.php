@props(['pricingParticipants' => 10, 'hasLegacyIncrements' => false])
<x-finance.panel title="Pricing">
    @if($hasLegacyIncrements)
        <p class="mb-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-900">This plan has extra price increments. Saving will remove them and use automatic GST cent adjustments.</p>
    @endif
    <div class="max-w-md">
        <x-ui.input name="pricing_participants" label="Participants used to set the workshop rate" type="number" min="1" max="10000" :value="$pricingParticipants" required info="Sets the rate per participant. The example quote below uses its own participant count to calculate quantity." />
    </div>
    <div x-show="preview" class="grid gap-4 sm:grid-cols-2" x-cloak>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-950">
            <h3 class="font-semibold">Workshop rate</h3>
            <p class="text-xs">Per hour, per participant</p>
            <p class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                <span class="whitespace-nowrap"><strong class="text-lg tabular-nums" x-text="money(preview?.workshopRounding.suggested.unitEx)"></strong> ex GST</span>
                <span class="whitespace-nowrap"><strong class="text-lg tabular-nums" x-text="money(preview?.workshopRounding.suggested.unitInc)"></strong> inc GST</span>
            </p>
            <p class="mt-2 text-xs">Based on <strong x-text="preview?.pricingParticipants"></strong> pricing participants for the example’s delivery duration.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="font-semibold">Travel rate</h3>
            <p class="text-xs">Per billable hour</p>
            <p class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                <span class="whitespace-nowrap"><strong class="text-lg tabular-nums" x-text="money(preview?.travelRate.suggested.unitEx)"></strong> ex GST</span>
                <span class="whitespace-nowrap"><strong class="text-lg tabular-nums" x-text="money(preview?.travelRate.suggested.unitInc)"></strong> inc GST</span>
            </p>
            <p class="mt-2 text-xs">Travel costs above are entered per 15 minutes. This is the hourly rate used on quotes.</p>
        </div>
    </div>
    <p class="mt-3 text-sm text-slate-600">Rates automatically increase only as much as needed to give exact cents before and after GST.</p>

    <div class="mt-5 border-t border-slate-200 pt-5">
        <h3 class="mb-3 font-semibold">Example quote</h3>
        <div class="grid gap-x-4 sm:grid-cols-3">
            <x-ui.input name="preview_hours" label="Delivery hours" type="number" min="0.25" step="0.25" value="1" />
            <x-ui.input name="preview_seats" label="Participants" type="number" min="1" step="1" value="10" />
            <x-ui.input name="preview_travel_units" label="Billable travel hours" type="number" min="0" step="0.25" value="0" />
        </div>
        <div x-show="supplies.length" class="mb-4" x-cloak>
            <p class="mb-2 text-sm font-medium">Supplied by the customer</p>
            <div class="flex flex-wrap gap-4">
                <template x-for="cost in supplies" :key="cost.id">
                    <label class="flex items-center gap-2 text-sm"><x-ui.checkbox :bare="true" x-bind:name="'preview_supplied[' + cost.id + ']'" value="1" /><span x-text="cost.name"></span></label>
                </template>
            </div>
        </div>
        <p x-show="!preview" class="text-sm text-slate-600">Enter valid hours, participants and billable travel hours to calculate prices.</p>
        <dl x-show="preview" class="space-y-2 text-sm" x-cloak>
            <div class="flex justify-between gap-4"><dt>Workshop <span class="text-slate-500" x-text="'(' + preview?.quantity + ' × ' + money(preview?.workshopRounding.suggested.unitInc) + ')' "></span></dt><dd class="whitespace-nowrap tabular-nums" x-text="money(preview?.cleanWorkshopGross)"></dd></div>
            <div x-show="preview?.travelRounding" class="flex justify-between gap-4"><dt>Travel <span class="text-slate-500" x-text="'(' + (preview?.travelRounding?.quantity || 0) + ' hrs × ' + money(preview?.travelRate.suggested.unitInc) + ')' "></span></dt><dd class="whitespace-nowrap tabular-nums" x-text="money(preview?.cleanTravelGross)"></dd></div>
            <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 font-semibold"><dt>Quote total inc GST</dt><dd class="whitespace-nowrap tabular-nums" x-text="money(preview?.cleanInvoiceGross)"></dd></div>
        </dl>
        <div x-show="preview?.roundingNeeded" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950" role="status" x-cloak>
            <p class="font-semibold">Automatic GST cent adjustment</p>
            <p x-show="preview?.workshopRounding &amp;&amp; !preview.workshopRounding.clean" class="mt-1">Workshop rate: <span x-text="rate(preview?.workshopRounding.current.unitInc)"></span> → <strong x-text="money(preview?.workshopRounding.suggested.unitInc)"></strong> inc GST.</p>
            <p x-show="preview?.travelRounding &amp;&amp; !preview.travelRounding.clean" class="mt-1">Travel rate per hour: <span x-text="rate(preview?.travelRounding?.current.unitInc)"></span> → <strong x-text="money(preview?.travelRounding?.suggested.unitInc)"></strong> inc GST.</p>
            <p class="mt-1" x-text="'Adds ' + money(preview?.roundingIncrease) + ' to this example’s total (inc GST). Already included above.'"></p>
        </div>
        <details x-show="preview" class="mt-4 border-t border-slate-200 pt-3" x-cloak>
            <summary class="cursor-pointer text-sm font-medium">Actual cost breakdown <span class="font-normal text-slate-500">(ex GST)</span></summary>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt>Per-workshop costs</dt><dd x-text="money(preview?.costs.workshop / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Delivery-hour costs</dt><dd x-text="money(preview?.costs.hour / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Participant costs</dt><dd x-text="money(preview?.costs.participant / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Travel costs</dt><dd x-text="money(preview?.costs.travel / 100)"></dd></div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-2 font-semibold"><dt>Total costs</dt><dd x-text="money(preview?.totalCost)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Actual cost per participant <span class="text-slate-500">(including travel)</span></dt><dd class="whitespace-nowrap tabular-nums" x-text="approximateMoney(preview?.costPerParticipant)"></dd></div>
            </dl>
        </details>
    </div>
</x-finance.panel>
