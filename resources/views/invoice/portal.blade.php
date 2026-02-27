<x-layout>
    <x-mast backRoute="{{ $isPublic ? '' : 'account.invoice.index' }}" backTitle="My Invoices">{{ $isPublic ? 'Invoice Payment' : ('Invoice '.$invoice->invoice_number) }}</x-mast>

    <x-container class="max-w-4xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="flex text-lg gap-2">
                        <div class="font-bold">Invoice #:</div>
                        <div>{{ $invoice->invoice_number }}</div>
                    </div>
                    <div class="flex">
                        <div class="font-bold w-28">Status:</div>
                        <div class="flex-1 text-right md:text-left">{{ \App\Models\Invoice::statusLabel((string) $invoice->status) }}</div>
                    </div>
                    <div class="flex">
                        <div class="font-bold w-28">Issue Date:</div>
                        <div class="flex-1 text-right md:text-left">{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</div>
                    </div>
                    <div class="flex">
                        <div class="font-bold w-28">Due Date:</div>
                        <div class="flex-1 text-right md:text-left">{{ $invoice->due_date?->format('M j, Y') ?? '-' }}</div>
                    </div>
                </div>
                <div class="md:text-right">
                    @php
                        $grossPaidAmount = (float) ($grossAllocatedAmount ?? $allocatedAmount ?? 0);
                        $refundedPaidAmount = (float) ($refundedAllocatedAmount ?? 0);
                        $netPaidAmount = (float) ($netAllocatedAmount ?? max(0, $grossPaidAmount - $refundedPaidAmount));
                    @endphp
                    @if($adjustmentTotalAmount !== null && abs((float) $adjustmentTotalAmount) > 0.0001)
                    <div class="flex">
                        <div class="flex-1 font-bold">Sub Total:</div>
                        <div class="w-24 text-right">${{ number_format((float) $invoice->total_amount, 2) }}</div>
                    </div>
                    <div class="flex">
                        <div class="flex-1 font-bold">Adjustments:</div>
                        <div class="w-24 text-right">{{ ((float) ($adjustmentTotalAmount ?? 0)) < 0 ? '-$' : '$' }}{{ number_format(abs((float) ($adjustmentTotalAmount ?? 0)), 2) }}</div>
                    </div>
                    @endif
                    <div class="flex">
                        <div class="flex-1 font-bold">GST Included:</div>
                        <div class="w-24 text-right">${{ number_format((float) ($adjustedGstAmount ?? $invoice->gst_amount), 2) }}</div>
                    </div>
                    <div class="flex">
                        <div class="flex-1 font-bold">Invoice Total:</div>
                        <div class="w-24 text-right">${{ number_format((float) ($adjustedTotalAmount ?? $invoice->total_amount), 2) }}</div>
                    </div>
                    <div class="flex border-t border-gray-200 mt-2 pt-2">
                        <div class="flex-1 font-bold">Paid:</div>
                        <div class="w-24 text-right">${{ number_format($grossPaidAmount, 2) }}</div>
                    </div>
                    @if($refundedPaidAmount > 0.0001)
                    <div class="flex text-red-700">
                        <div class="flex-1 font-bold">Refunded:</div>
                        <div class="w-24 text-right">-${{ number_format($refundedPaidAmount, 2) }}</div>
                    </div>
                    <div class="flex">
                        <div class="flex-1 font-bold">Net Paid:</div>
                        <div class="w-24 text-right">${{ number_format($netPaidAmount, 2) }}</div>
                    </div>
                    @endif
                    <div class="flex text-lg">
                        <div class="flex-1 font-bold">Outstanding:</div>
                        <div class="w-24 text-right">${{ number_format((float) $outstandingAmount, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                @if($isAccountView)
                <x-ui.button type="link" href="{{ route('account.invoice.pdf', $invoice) }}" target="_blank">Open Invoice PDF</x-ui.button>
                @elseif(!$isPublic)
                <x-ui.button type="link" href="{{ route('invoice.magic.pdf', ['invoice' => $invoice, 'token' => $accessToken]) }}" target="_blank">Open Invoice PDF</x-ui.button>
                @endif
            </div>
        </div>

        @if(!$isPublic)
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 mb-6">
            <h2 class="text-xl font-bold mb-4">Line Items</h2>
            @php
            $combinedLineItems = collect();

            foreach ($invoice->lines as $line) {
            $combinedLineItems->push([
            'kind' => 'invoice',
            'label' => 'Invoice '.$invoice->invoice_number,
            'description' => (string) ($line->description ?? ''),
            'notes' => (string) ($line->notes ?? ''),
            'quantity' => (float) ($line->quantity ?? 0),
            'unit_price_ex_tax' => (float) ($line->unit_price_ex_tax ?? 0),
            'tax_amount' => (float) ($line->tax_amount ?? 0),
            'line_total_inc_tax' => (float) ($line->line_total_inc_tax ?? 0),
            'created_at' => optional($line->created_at)->timestamp ?? 0,
            ]);
            }

            foreach (($invoice->taxAdjustments ?? collect())->sortBy(fn ($adjustment) => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0) as $adjustment) {
            foreach (($adjustment->lines ?? collect()) as $line) {
            $combinedLineItems->push([
            'kind' => 'adjustment',
            'label' => 'Tax Adjustment Note '.$adjustment->adjustment_number,
            'description' => (string) ($line->description ?? ''),
            'notes' => (string) ($line->notes ?? ''),
            'quantity' => abs((float) ($line->quantity ?? 0)),
            'unit_price_ex_tax' => -1 * abs((float) ($line->unit_price_ex_tax ?? 0)),
            'tax_amount' => -1 * abs((float) ($line->tax_amount ?? 0)),
            'line_total_inc_tax' => -1 * abs((float) ($line->line_total_inc_tax ?? 0)),
            'created_at' => optional($adjustment->issue_date)->timestamp ?? optional($adjustment->created_at)->timestamp ?? 0,
            ]);
            }
            }
            @endphp
            @if($combinedLineItems->isEmpty())
            <div class="text-sm text-gray-600">No line items on this invoice.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-gray-200">
                            <th class="py-2 pr-3">Description</th>
                            <th class="py-2 pr-3 text-right">Qty</th>
                            <th class="py-2 pr-3 text-right">Unit (ex GST)</th>
                            <th class="py-2 pr-3 text-right">GST</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($combinedLineItems as $line)
                        <tr class="border-b border-gray-100 align-top">
                            <td class="py-2 pr-3">
                                {{ $line['description'] ?: '-' }}
                                <div class="text-xs mt-1 {{ $line['kind'] === 'adjustment' ? 'text-red-600' : 'text-gray-500' }}">{{ $line['label'] }}</div>
                                @if(trim((string) ($line['notes'] ?? '')) !== '')
                                <div class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ $line['notes'] }}</div>
                                @endif
                            </td>
                            @php
                            $quantity = (float) $line['quantity'];
                            $displayQuantity = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
                            if ($displayQuantity === '') {
                                $displayQuantity = '0';
                            }
                            @endphp
                            <td class="py-2 pr-3 text-right">{{ $displayQuantity }}</td>
                            <td class="py-2 pr-3 text-right">${{ number_format((float) $line['unit_price_ex_tax'], 2) }}</td>
                            <td class="py-2 pr-3 text-right">${{ number_format((float) $line['tax_amount'], 2) }}</td>
                            <td class="py-2 text-right">${{ number_format((float) $line['line_total_inc_tax'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <h2 class="text-xl font-bold mb-4">Pay by Credit Card</h2>

            @if((float) $outstandingAmount <= 0.0001)
                <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm">
                This invoice is fully paid.
        </div>
        @if(!$isPublic && (session('payment_receipt_view_url') || session('payment_receipt_download_url')))
        <div class="mt-3 flex flex-wrap gap-3">
            @if(session('payment_receipt_view_url'))
            <a
                href="{{ session('payment_receipt_view_url') }}"
                target="_blank"
                class="inline-flex items-center rounded-md bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark">View Receipt</a>
            @endif
            @if(session('payment_receipt_download_url'))
            <a
                href="{{ session('payment_receipt_download_url') }}"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download Receipt</a>
            @endif
        </div>
        @endif
        @if($isPublic)
        <div class="mt-3 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('invoice.public.email-documents', $invoice) }}">
                @csrf
                <x-ui.button type="submit">Email Invoice & Receipts</x-ui.button>
            </form>
        </div>
        @elseif(!empty($receiptLinks))
        <div class="mt-3 flex flex-wrap gap-3">
            @if($isAccountView && !empty($accountReceiptsUrl))
            <a
                href="{{ $accountReceiptsUrl }}"
                class="inline-flex items-center rounded-md bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark">View All Receipts</a>
            @else
            @foreach($receiptLinks as $receiptLink)
            <a
                href="{{ $receiptLink['view_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center rounded-md bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark">View Receipt #{{ $receiptLink['payment_id'] }}</a>
            <a
                href="{{ $receiptLink['download_url'] }}"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download Receipt #{{ $receiptLink['payment_id'] }}</a>
            @endforeach
            @endif
        </div>
        @endif
        @else
        <form method="POST"
            action="{{ $isAccountView ? route('account.invoice.pay', $invoice) : ($isPublic ? route('invoice.public.pay.process', $invoice) : route('invoice.magic.pay', ['invoice' => $invoice, 'token' => $accessToken])) }}"
            x-data="invoicePaymentPage({
                        squareEnabled: @js($squareEnabled),
                        squareApplicationId: @js($squareApplicationId),
                        squareLocationId: @js($squareLocationId),
                        squareEnvironment: @js($squareEnvironment),
                      })"
            x-on:submit.prevent="submitForm($event)">
            @csrf
            @if(!$isAccountView && !$isPublic)
            <input type="hidden" name="token" value="{{ $accessToken }}">
            @endif

            <div x-init="initSquareCard()">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm">Card Details</label>
                    <a href="https://squareup.com/au/en" target="_blank" class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs text-blue-700" rel="noopener noreferrer">
                        Secure payment by Square
                    </a>
                </div>
                <div class="relative">
                    <div
                        x-ref="squareCardContainer"
                        class="bg-white min-h-[88px] transition"
                        x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                    <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                        <img src="/loading.gif" alt="Loading card form" width="56" height="56" />
                    </div>
                </div>
                <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">
                <div x-show="errorMessage" class="text-xs text-red-600 mt-2" x-text="errorMessage"></div>
                @error('source_id')
                <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" x-bind:disabled="isSubmitting || isCardLoading">
                    <span x-show="!isSubmitting">Pay ${{ number_format((float) $outstandingAmount, 2) }}</span>
                    <span x-show="isSubmitting" x-cloak>Processing...</span>
                </x-ui.button>
            </div>
        </form>
        @endif
        </div>
    </x-container>
</x-layout>

@if($squareEnabled)
<script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function invoicePaymentPage(config) {
        return {
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            squareCard: null,
            sourceId: '',
            errorMessage: '',
            isSubmitting: false,
            isCardLoading: false,

            canUseCreditCard() {
                return this.squareEnabled && this.squareApplicationId !== '' && this.squareLocationId !== '';
            },

            async initSquareCard() {
                if (!this.canUseCreditCard()) {
                    this.errorMessage = 'Credit card payments are not available right now.';
                    return false;
                }

                this.isCardLoading = true;
                const ready = await this.waitForSquareSdk();
                if (!ready) {
                    this.errorMessage = 'Square SDK did not load.';
                    this.isCardLoading = false;
                    return false;
                }

                if (this.squareCard) {
                    this.isCardLoading = false;
                    return true;
                }

                try {
                    const payments = window.Square.payments(this.squareApplicationId, this.squareLocationId);
                    this.squareCard = await payments.card();
                    await this.squareCard.attach(this.$refs.squareCardContainer);
                    this.isCardLoading = false;
                    return true;
                } catch (e) {
                    this.errorMessage = e?.message || 'Unable to load card payment form.';
                    this.isCardLoading = false;
                    return false;
                }
            },

            async submitForm(event) {
                if (this.isSubmitting) {
                    return;
                }
                this.errorMessage = '';
                this.isSubmitting = true;
                const ready = await this.initSquareCard();
                if (!ready) {
                    this.isSubmitting = false;
                    return;
                }

                try {
                    const result = await this.squareCard.tokenize();
                    if (result.status !== 'OK') {
                        const errs = Array.isArray(result.errors) ? result.errors.map((err) => err.message).filter(Boolean) : [];
                        this.errorMessage = errs.join(' | ') || 'Card validation failed.';
                        this.isSubmitting = false;
                        return;
                    }
                    this.sourceId = result.token;
                    if (this.$refs.sourceIdInput) {
                        this.$refs.sourceIdInput.value = this.sourceId;
                    }
                } catch (e) {
                    this.errorMessage = e?.message || 'Unable to tokenize card.';
                    this.isSubmitting = false;
                    return;
                }

                const form = event.target.tagName === 'FORM' ? event.target : event.target.closest('form');
                if (!form) {
                    this.errorMessage = 'Unable to submit payment form.';
                    this.isSubmitting = false;
                    return;
                }
                const sourceIdInput = form.querySelector('input[name=\"source_id\"]');
                if (sourceIdInput) {
                    sourceIdInput.value = this.sourceId || '';
                }
                form.submit();
            },

            async waitForSquareSdk(maxWaitMs = 8000) {
                const started = Date.now();
                while (Date.now() - started < maxWaitMs) {
                    if (window.Square) {
                        return true;
                    }
                    await new Promise((resolve) => setTimeout(resolve, 150));
                }
                return false;
            }
        };
    }
</script>
