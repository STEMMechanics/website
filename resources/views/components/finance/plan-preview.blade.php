<x-finance.panel title="Pricing preview" class="mt-5">
    <div class="grid gap-x-4 sm:grid-cols-3">
        <x-ui.input name="preview_hours" label="Delivery hours" type="number" min="0.25" step="0.25" value="1" />
        <x-ui.input name="preview_seats" label="Participants" type="number" min="1" step="1" placeholder="Use plan pricing participants" />
        <x-ui.input name="preview_travel_units" label="Billable travel hours" type="number" min="0" step="0.25" value="0.25" />
    </div>
    <div x-show="supplies.length" class="mb-4">
        <p class="mb-2 text-sm font-medium">Supplied by the customer</p>
        <div class="flex flex-wrap gap-4">
            <template x-for="cost in supplies" :key="cost.id">
                <label class="flex items-center gap-2 text-sm"><x-ui.checkbox :bare="true" x-bind:name="'preview_supplied[' + cost.id + ']'" value="1" /><span x-text="cost.name"></span></label>
            </template>
        </div>
    </div>
    <p x-show="!preview" class="text-sm text-slate-600">Enter valid hours, participants and billable travel hours to calculate prices.</p>
    <div x-show="preview" class="grid gap-5 lg:grid-cols-2">
        <div>
            <h3 class="mb-3 font-semibold">Cost allocations <span class="whitespace-nowrap">(ex GST)</span></h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt>Per-workshop costs</dt><dd x-text="money(preview?.costs.workshop / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Delivery-hour costs</dt><dd x-text="money(preview?.costs.hour / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Participant costs</dt><dd x-text="money(preview?.costs.participant / 100)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Travel costs</dt><dd x-text="money(preview?.costs.travel / 100)"></dd></div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-2 font-semibold"><dt>Total costs</dt><dd x-text="money(preview?.totalCost)"></dd></div>
            </dl>
        </div>
        <div>
            <h3 class="mb-3 font-semibold">Customer prices</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt>Invoice unit price (per seat-hour, ex GST)</dt><dd class="whitespace-nowrap" x-text="money(preview?.unitNet)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Invoice unit price (including GST)</dt><dd class="whitespace-nowrap" x-text="money(preview?.unitGross)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Workshop total (including GST)</dt><dd class="whitespace-nowrap" x-text="money(preview?.workshopGross)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Travel total (including GST)</dt><dd class="whitespace-nowrap" x-text="money(preview?.travelGross)"></dd></div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-2 font-semibold"><dt>Invoice total (including GST)</dt><dd class="whitespace-nowrap" x-text="money(preview?.invoiceGross)"></dd></div>
                <div class="flex justify-between gap-4"><dt>Public ticket per participant (including GST, excludes travel)</dt><dd class="whitespace-nowrap" x-text="money(preview?.ticket)"></dd></div>
            </dl>
        </div>
    </div>
    <p class="mt-4 text-sm text-slate-600">Uses the current form values and rounding settings. Invoice rounding applies per seat-hour; ticket rounding applies per participant. This preview does not save changes.</p>
</x-finance.panel>
