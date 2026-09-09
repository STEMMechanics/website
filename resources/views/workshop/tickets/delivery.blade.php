<x-layout :title="$workshop->title.' — Delivery details'">
    <x-mast :title="$workshop->title" />
    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
        <div class="flex-1 min-w-0">
        <div class="mb-3 flex items-center gap-3"><x-ui.row-action label="Back" icon="fa-arrow-left" :href="route('workshop.ticket.flow.equipment', $workshop)" /><h2 class="text-2xl font-bold">Delivery details</h2></div>
        <p class="mb-4 text-sm text-gray-600">Choose how you would like to receive your equipment.</p>
        @include('workshop.tickets.partials.summary', ['workshop' => $workshop])
        @foreach($errors->all() as $error)<p class="mb-2 text-sm text-red-600">{{ $error }}</p>@endforeach
        @php($customer = $session['equipment_customer'] ?? [])
        <form method="POST" action="{{ route('workshop.ticket.flow.delivery.save', $workshop) }}" x-data="SM.workshopDelivery(@js(['summary' => $summary, 'ticketAmount' => $ticketAmount]))" x-on:input="scheduleQuote()" x-on:change="scheduleQuote()" x-on:submit="if (loading || quoteError) $event.preventDefault()">
            @csrf
            @if($lines->isNotEmpty())
                <x-finance.panel title="Delivery">
                    <x-ui.select name="shipping_method_code" label="Delivery option" x-model="method">
                        <template x-for="option in quote.shipping_methods || []" :key="option.code"><option :value="option.code" x-text="(option.label || option.name || option.code) + ' — ' + (option.requires_manual_quote ? 'Quote required' : money(option.estimated_amount || 0))"></option></template>
                    </x-ui.select>
                    <p class="mb-4 text-sm text-gray-600">Shipping address</p>
                    <x-ui.input name="billing_address" label="Address" :value="old('billing_address', $customer['billing_address'] ?? '')" />
                    <x-ui.input name="billing_address2" label="Address line 2" :value="old('billing_address2', $customer['billing_address2'] ?? '')" />
                    <div class="grid gap-x-6 sm:grid-cols-2">
                        <x-ui.input name="billing_city" label="Suburb / city" :value="old('billing_city', $customer['billing_city'] ?? '')" />
                        <x-ui.select name="billing_state" label="State">
                            <option value="">Choose state</option>
                            @foreach(['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'] as $state)<option @selected(old('billing_state', $customer['billing_state'] ?? '') === $state)>{{ $state }}</option>@endforeach
                        </x-ui.select>
                        <x-ui.input name="billing_postcode" label="Postcode" maxlength="4" :value="old('billing_postcode', $customer['billing_postcode'] ?? '')" />
                        <x-ui.input label="Country" value="Australia" disabled />
                    </div>
                    <div x-show="quote.shipping_quote?.offers_consolidation && method !== 'pickup' && method !== 'request_quote'" x-cloak>
                        <x-ui.checkbox name="consolidate_shipments" value="1" label="Send items together when all are available" :checked="$customer['consolidate_shipments'] ?? false" x-bind:disabled="!quote.shipping_quote?.offers_consolidation || method === 'pickup' || method === 'request_quote'" />
                    </div>
                    <div x-show="!quote.shipping_quote?.requires_manual_quote" class="my-4 space-y-3">
                        <template x-for="shipment in quote.shipping_quote?.shipments || []" :key="shipment.key">
                            @include('shop.partials.shipment-card', ['moneyFunction' => 'money'])
                        </template>
                    </div>
                    @if($summary['contains_preorder'] ?? false)
                        <x-ui.checkbox name="preorder_acknowledged" value="1" label="I understand that preordered equipment will be sent when available" :checked="$customer['preorder_acknowledged'] ?? false" />
                    @endif
                </x-finance.panel>
                <dl class="my-5 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>Tickets</dt><dd>{{ money($ticketAmount) }}</dd></div>
                    @foreach($lines as $line)<div class="flex justify-between gap-4"><dt>{{ $line->product->title }} × {{ $line->quantity }}</dt><dd>{{ money($line->line_price) }}</dd></div>@endforeach
                    <div class="flex justify-between gap-4"><dt>Delivery</dt><dd><span x-text="money(quote.shipping)">{{ money($summary['shipping']) }}</span></dd></div>
                    <div class="flex justify-between gap-4 border-t pt-2 font-semibold"><dt>Total (inc GST)</dt><dd x-text="quote.shipping_quote?.requires_manual_quote ? money(ticketAmount) + ' now; equipment quoted separately' : money(ticketAmount + Number(quote.total || 0))"></dd></div>
                </dl>
                    <p x-show="quote.shipping_quote?.requires_manual_quote" x-cloak class="mb-4 text-sm text-amber-700">This delivery requires a quote. Your tickets will be paid now; equipment and delivery will be quoted separately before you pay for them.</p>
            @endif
            <input type="hidden" name="confirmed_total" x-bind:value="quote.total || 0">
            <p class="text-sm text-red-600" x-show="quoteError" x-text="quoteError" role="alert"></p>
            <div class="mt-5 flex justify-end"><x-ui.button type="submit" name="action" value="continue" x-bind:disabled="loading || Boolean(quoteError)"><span x-text="loading ? 'Updating delivery…' : 'Continue'">Continue</span></x-ui.button></div>
        </form>
        </div>
        <div class="hidden md:block w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
