@php
    $workspace = app(\App\Services\Finance\InvoiceAllocationWorkspace::class);
    $invoiceAllocation = app(\App\Services\Finance\InvoiceAllocation::class)->context($invoice);
    $workshopContexts = $workspace->contexts($invoice);
    $invoiceItems = $workspace->invoiceItems($invoice);
    $plans = ['invoice' => collect($invoiceAllocation['editorTargets'])->map(fn ($amount) => number_format($amount / 100, 2, '.', ''))->all()];
    foreach ($workshopContexts as $context) $plans['workshop-'.$context['workshop']->id] = collect($context['allocation']['targets'])->map(fn ($amount) => number_format($amount / 100, 2, '.', ''))->all();
    $categories = $invoiceAllocation['categories']->concat($workshopContexts->flatMap(fn ($context) => $context['allocation']['categories']))->unique('id')->sortBy('priority');
    $invoiceNet = app(\App\Services\Finance\FinancePlanner::class)->cents($invoice->subtotal_amount);
    $receivedNet = app(\App\Services\Finance\FinancePlanner::class)->income([$invoice->id])['net'];
@endphp
<x-finance.panel title="Cost centre allocation" x-data="SM.invoiceAllocationWorkspace({{ \Illuminate\Support\Js::from(['plans' => $plans, 'income' => $invoiceNet, 'scope' => $workshopContexts->isEmpty() ? 'invoice' : 'overview']) }})" x-on:allocation-plan-updated="updatePlan($event.detail)" x-on:invoice-lines-updated.window="income = $event.detail.total">
    <x-slot:actions>
        <div class="flex items-center gap-2" x-show="scope === 'invoice'">
            @if(!$invoiceAllocation['warning'])<x-finance.allocation-calculator-button :invoice="$invoice" />@endif
            @if($invoice->lines->contains('kind', 'product'))
                <form method="POST" action="{{ route('admin.product-allocation.apply', $invoice) }}" class="flex" x-data x-on:submit.prevent="window.SM.confirm('Apply current defaults', 'Replace this invoice allocation, including any manual override, with the current product and pricing defaults?', 'Apply defaults', confirmed => { if (confirmed) $el.submit() })">
                    @csrf
                    <x-ui.button type="submit" color="outline" class="size-10 p-0!" aria-label="Apply current product allocations" title="Apply current product allocations"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></x-ui.button>
                </form>
            @endif
        </div>
    </x-slot:actions>
    <p class="mb-5 text-sm text-slate-500">All allocations and totals below exclude GST. Saving the plan does not require payment; funds enter cost centres when received.</p>
    @foreach($errors->get('workshop_allocations.*') as $messages) @foreach($messages as $message)<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@endforeach @endforeach
    @if($workshopContexts->isNotEmpty())
        <dl class="mb-6 grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-4" aria-live="polite">
            <div><dt class="text-sm text-slate-600">Invoice total (ex GST)</dt><dd class="mt-1 text-xl font-semibold tabular-nums" x-text="money(income)"></dd></div>
            <div><dt class="text-sm text-slate-600">Combined allocation (ex GST)</dt><dd class="mt-1 text-xl font-semibold tabular-nums" x-text="money(total)"></dd></div>
            <div><dt class="text-sm text-slate-600" x-text="income < total ? 'Shortfall (ex GST)' : 'Unallocated (ex GST)'"></dt><dd class="mt-1 text-xl font-semibold tabular-nums" x-bind:class="total > income ? 'text-red-700' : 'text-slate-900'" x-text="money(Math.abs(income - total))"></dd></div>
            <div><dt class="text-sm text-slate-600">Received (ex GST)</dt><dd class="mt-1 text-xl font-semibold tabular-nums">${{ number_format($receivedNet / 100, 2) }}</dd></div>
        </dl>
        <nav class="sm-preset-views mb-6" role="tablist" aria-label="Allocation sections">
            <button type="button" class="sm-preset-view" role="tab" x-bind:aria-selected="scope === 'overview'" x-bind:aria-current="scope === 'overview' ? 'page' : null" x-on:click="scope = 'overview'">Overview</button>
            <button type="button" class="sm-preset-view" role="tab" x-bind:aria-selected="scope === 'invoice'" x-bind:aria-current="scope === 'invoice' ? 'page' : null" x-on:click="scope = 'invoice'">Invoice items <span x-cloak x-show="needsInspection('invoice')" class="ml-1 inline-flex text-amber-700" role="img" aria-label="Needs inspection: invoice items allocation exceeds their funding" title="Needs inspection: invoice items allocation exceeds their funding"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span></button>
            @foreach($workshopContexts as $context)
                <button type="button" class="sm-preset-view" role="tab" x-bind:aria-selected="scope === @js('workshop-'.$context['workshop']->id)" x-bind:aria-current="scope === @js('workshop-'.$context['workshop']->id) ? 'page' : null" x-on:click="scope = @js('workshop-'.$context['workshop']->id)">{{ $context['workshop']->title }} <span x-cloak x-show="needsInspection(@js('workshop-'.$context['workshop']->id))" class="ml-1 inline-flex text-amber-700" role="img" x-bind:aria-label="inspectionReason(@js('workshop-'.$context['workshop']->id))" x-bind:title="inspectionReason(@js('workshop-'.$context['workshop']->id))"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span></button>
            @endforeach
        </nav>
        <div role="tabpanel" x-show="scope === 'overview'">
            <x-ui.table variant="listing" table-class="w-full min-w-[32rem]">
                <thead><tr><th>Cost centre</th><th class="text-right">Invoice items</th><th class="text-right">Workshops</th><th class="text-right">Total (ex GST)</th></tr></thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr><td>{{ $category->name }}</td><td class="text-right tabular-nums" x-text="money(categoryAmount('invoice', '{{ $category->id }}'))"></td><td class="text-right tabular-nums" x-text="money(categoryTotal('{{ $category->id }}') - categoryAmount('invoice', '{{ $category->id }}'))"></td><td class="text-right font-semibold tabular-nums" x-text="money(categoryTotal('{{ $category->id }}'))"></td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>Total</th><td class="text-right font-semibold tabular-nums" x-text="money(planTotal('invoice'))"></td><td class="text-right font-semibold tabular-nums" x-text="money(total - planTotal('invoice'))"></td><td class="text-right font-semibold tabular-nums" x-text="money(total)"></td></tr></tfoot>
            </x-ui.table>
            <p class="mt-4 text-sm text-slate-500">Invoice items cover unlinked rows, including travel and manual items. Workshop tabs contain each linked workshop’s full allocation plan. Edit a tab, then save the invoice to save all changes.</p>
        </div>
    @endif
    <div role="tabpanel" x-show="scope === 'invoice'" @if($workshopContexts->isNotEmpty()) x-cloak @endif>
        @if($workshopContexts->isNotEmpty())
            <h3 class="mb-2 font-semibold">Invoice items</h3>
            @if($invoiceItems)
                <p class="mb-2 text-sm text-slate-500">This allocation covers these rows only:</p>
                <ul class="mb-5 list-disc space-y-1 pl-5 text-sm">@foreach($invoiceItems as $description)<li>{{ $description }}</li>@endforeach</ul>
            @else
                <p class="mb-5 text-sm text-slate-500">All rows belong to linked workshops. There is nothing additional to allocate on the invoice.</p>
            @endif
        @endif
        <div id="invoice-cost-centres" data-record-refresh>
            @include('admin.invoice.allocation-form', ['inline' => true, 'allocation' => $invoiceAllocation, 'withinWorkspace' => true])
        </div>
    </div>
    @foreach($workshopContexts as $context)
        <div role="tabpanel" x-show="scope === @js('workshop-'.$context['workshop']->id)" x-cloak>
            @include('admin.workshop.allocation-form', ['inline' => true, 'workshop' => $context['workshop'], 'allocation' => $context['allocation'], 'state' => $context['state']])
        </div>
    @endforeach
</x-finance.panel>
