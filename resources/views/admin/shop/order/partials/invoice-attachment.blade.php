<div x-data="SM.storeOrderInvoiceAttachment(@js([
    'optionsUrl' => route('admin.shop.order.invoice.options', $order),
    'reviewUrl' => route('admin.shop.order.invoice.attach', $order),
    'initialInvoiceId' => (int) request()->query('attach_invoice', old('invoice_id', '0')),
    'autoOpen' => request()->has('attach_invoice') || (int) old('invoice_id', '0') > 0,
]))" x-on:close.capture="dismiss()">
    <x-ui.button href="{{ route('admin.shop.order.invoice.attach', $order) }}" color="outline" x-ref="trigger" x-on:click.prevent="open($event.currentTarget)">Attach additional invoice</x-ui.button>
    <div x-ref="popup">
        <x-ui.list-dialog id="order-invoice-attachment" title="Attach additional invoice" kind="bulk" :centered="true">
            <div class="space-y-4 p-5">
                <p class="text-sm text-gray-600">Order {{ $order->order_number }} · {{ $order->billing_name }}@if($order->billing_company) · {{ $order->billing_company }}@endif</p>
                <p x-show="error" x-cloak x-text="error" class="whitespace-pre-line rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert"></p>
                <div x-show="recentLoading || reviewLoading" x-cloak class="flex justify-center py-6" role="status" aria-label="Loading invoices"><x-ui.loading-indicator class="text-4xl" /></div>
                <div x-show="!reviewHtml && !reviewLoading">
                    <div x-show="!searchOpen || search.trim() === ''" class="mb-4">
                        <div class="mb-3">
                            <h3 class="font-semibold text-gray-900">Latest invoices with store items</h3>
                            <p class="mt-1 text-sm text-gray-600">The latest five for this customer or company.</p>
                        </div>
                        <p x-show="!recentLoading && !invoices.length" x-cloak class="py-3 text-sm text-gray-500">No eligible invoices with store items for this customer or company.</p>
                        <div class="space-y-2">
                            <template x-for="invoice in invoices" :key="invoice.id">
                                <button type="button" x-on:click="review(invoice.id)" x-bind:disabled="reviewLoading" class="w-full rounded-xl border border-gray-200 p-3 text-left transition hover:border-blue-300 hover:bg-blue-50">
                                    <span class="flex justify-between gap-3 font-semibold"><span x-text="'Invoice ' + invoice.number"></span><span x-text="invoice.total"></span></span>
                                    <span class="mt-1 block text-xs text-gray-600" x-text="invoiceDetails(invoice)"></span>
                                    <span class="mt-1 block text-sm text-gray-700" x-text="invoice.items"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <button type="button" x-ref="searchToggle" x-on:click="toggleSearch()" x-bind:disabled="saving" x-bind:aria-expanded="searchOpen" aria-controls="order-invoice-search" class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
                        <span><i class="fa-solid fa-magnifying-glass mr-2" aria-hidden="true"></i>Search more invoices</span>
                        <i class="fa-solid fa-chevron-down transition-transform" x-bind:class="searchOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
                    </button>
                    <div id="order-invoice-search" x-show="searchOpen" x-cloak class="mb-4">
                        <label for="order-invoice-search-input" class="mb-1 mt-3 block text-sm font-medium">Search invoices</label>
                        <x-ui.input-control id="order-invoice-search-input" x-ref="searchInput" type="text" x-model="search" x-on:input="scheduleSearch()" x-on:keydown.arrow-down.prevent="moveSuggestion(1)" x-on:keydown.arrow-up.prevent="moveSuggestion(-1)" x-on:keydown.enter.prevent="chooseSuggestion()" x-on:keydown.escape.stop.prevent="toggleSearch()" autocomplete="off" maxlength="150" placeholder="Invoice number, item, customer or company" role="combobox" aria-autocomplete="list" aria-controls="order-invoice-suggestions" x-bind:aria-expanded="search.trim() !== '' && suggestions.length > 0" x-bind:aria-activedescendant="highlighted >= 0 ? 'order-invoice-suggestion-' + highlighted : null" />
                        <div x-show="search.trim() !== ''" class="mt-2">
                            <p x-show="searchLoading" class="py-3 text-sm text-gray-500" role="status">Searching invoices…</p>
                            <p x-show="!searchLoading && !suggestions.length" class="py-3 text-sm text-gray-500">No matching invoices with store items.</p>
                            <ul id="order-invoice-suggestions" role="listbox" aria-label="Invoice suggestions" class="space-y-2">
                                <template x-for="(invoice, index) in suggestions" :key="invoice.id">
                                    <li role="presentation">
                                        <button type="button" role="option" x-bind:id="'order-invoice-suggestion-' + index" x-bind:aria-selected="index === highlighted" x-bind:disabled="reviewLoading" x-on:mouseenter="highlighted = index" x-on:click="review(invoice.id)" class="w-full rounded-xl border p-3 text-left transition" x-bind:class="index === highlighted ? 'border-blue-300 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                                            <span class="flex justify-between gap-3 font-semibold"><span x-text="'Invoice ' + invoice.number"></span><span x-text="invoice.total"></span></span>
                                            <span class="mt-1 block text-xs text-gray-600" x-text="invoiceDetails(invoice)"></span>
                                            <span class="mt-1 block text-sm text-gray-700" x-text="invoice.items"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
                <div x-show="reviewHtml" x-cloak>
                    <x-ui.button color="outline" size="small" class="mb-4" x-on:click="back()" x-bind:disabled="saving">Choose another invoice</x-ui.button>
                    <div x-html="reviewHtml" x-on:submit.prevent="save($event.target)"></div>
                </div>
            </div>
        </x-ui.list-dialog>
    </div>
</div>
