<x-layout>
    <div
        x-data="{
            replacementDialogOpen: false,
            replacementDialogData: null,
            replacementDialogSelectedCandidateId: null,
            openReplacementDialogFromEncoded(dialogEncoded) {
                if (!dialogEncoded) {
                    return;
                }

                try {
                    this.openReplacementDialog(JSON.parse(decodeURIComponent(dialogEncoded)));
                } catch (error) {
                    console.error('Unable to open payment replacement dialog.', error);
                }
            },
            openReplacementDialog(dialogData = null) {
                const nextData = dialogData || null;
                if (!nextData || !Array.isArray(nextData.candidates) || nextData.candidates.length === 0) {
                    return;
                }

                this.replacementDialogData = nextData;
                this.replacementDialogSelectedCandidateId = String(nextData.candidates[0]?.id || '');
                this.replacementDialogOpen = true;
            },
            closeReplacementDialog() {
                this.replacementDialogOpen = false;
            },
            selectedReplacementCandidate() {
                if (!this.replacementDialogData || !Array.isArray(this.replacementDialogData.candidates)) {
                    return null;
                }

                const selectedId = String(this.replacementDialogSelectedCandidateId || '');
                return this.replacementDialogData.candidates.find((candidate) => String(candidate?.id || '') === selectedId)
                    || this.replacementDialogData.candidates[0]
                    || null;
            },
        }"
    >
    <x-mast title="Payments"><x-slot:actions><x-ui.button color="mast" href="{{ route('admin.payment.create') }}">Record payment</x-ui.button></x-slot:actions></x-mast>

    <x-container class="mt-4">
        <x-ui.dynamic-list name="admin-payment-index">

        <x-ui.collection-controls class="my-5" />

        @if($customerPayments->isEmpty())
        <x-none-found item="payments" search="{{ request()->get('search') }}" />
        @else
            <div data-list-results class="space-y-4 md:hidden">
                @foreach ($customerPayments as $customerPayment)
                    @php
                        $allocated = (float) ($customerPayment->allocated_amount_sum ?? 0);
                        $unallocatedBeforeRefund = max(0, round(((float) $customerPayment->total_amount) - $allocated, 2));
                        $unallocated = max(0, round($unallocatedBeforeRefund - (float) $customerPayment->refunds->sum('total_amount'), 2));
                        $typeLabel = \App\Models\Payment::paymentMethodLabel((string) ($customerPayment->payment_method ?? ''));
                        $statusLabel = $customerPayment->isRefund()
                            ? 'Refund'
                            : $customerPayment->clearanceStatusLabel();
                        $statusTone = $customerPayment->isRefund()
                            ? 'slate'
                            : $customerPayment->clearanceStatusTone();
                        $allocatedInvoices = $customerPayment->allocations
                            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0 && $allocation->invoice)
                            ->map(fn ($allocation) => $allocation->invoice)
                            ->unique('id')
                            ->values();
                        $replacementDialogData = $paymentReplacementDialogDataById[(string) $customerPayment->id] ?? null;
                        $receiptViewUrl = route('admin.payment.receipt', $customerPayment);
                        $receiptDownloadUrl = route('admin.payment.receipt', ['payment' => $customerPayment, 'download' => 1]);
                    @endphp
                    <article class="{{ $customerPayment->isPendingBankTransfer() ? 'border-amber-200 bg-amber-50/80' : 'border-gray-200 bg-white' }} rounded-2xl border p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('admin.payment.edit', $customerPayment) }}" class="font-semibold text-gray-900 hover:text-primary-color whitespace-nowrap">#{{ $customerPayment->id }}</a>
                                <div class="mt-1 text-xs text-gray-600">{{ $customerPayment->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="text-xs text-gray-600">
                                    @if($customerPayment->user)
                                        <a href="{{ route('admin.user.edit', $customerPayment->user) }}" class="hover:text-primary-color hover:underline">
                                            {{ $customerPayment->user->getName() }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-950">{{ money((float) $customerPayment->total_amount) }}</div>
                                <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div class="text-gray-600">{{ $typeLabel }}</div>
                            <div class="text-gray-600">
                                @if($allocatedInvoices->isNotEmpty())
                                    {{ $allocatedInvoices->map(fn ($invoice) => 'Invoice #'.$invoice->invoice_number)->implode(', ') }}
                                @else
                                    -
                                @endif
                            </div>
                            <div class="text-gray-600">Alloc: {{ money((float) $allocated) }} · Unalloc: {{ money($unallocated) }}</div>
                        </div>

                        <x-ui.row-actions class="mt-4">
                            <x-ui.row-action label="Edit payment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $customerPayment) }}" />
                            @if(! empty($replacementDialogData['candidates'] ?? []))
                                <x-ui.row-action label="Review matches" icon="fa-solid fa-right-left" tone="neutral"
                                    type="button"
                                    data-dialog="{{ rawurlencode(json_encode($replacementDialogData, JSON_UNESCAPED_UNICODE)) }}"
                                    x-on:click.prevent="openReplacementDialogFromEncoded($el.dataset.dialog)"
                                 />
                            @endif
                            <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $receiptViewUrl }}" target="_blank" />
                            <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ $receiptDownloadUrl }}" />
                        </x-ui.row-actions>
                    </article>

                    @foreach($customerPayment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0) as $refund)
                        @php
                            $refundViewUrl = route('admin.payment.receipt', $refund);
                            $refundDownloadUrl = route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]);
                        @endphp
                        <article class="ml-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.payment.edit', $refund) }}" class="font-semibold text-gray-900 hover:text-primary-color whitespace-nowrap">↳ #{{ $refund->id }}</a>
                                    <div class="mt-1 text-xs text-gray-600">{{ $refund->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                    <div class="text-xs text-gray-600">
                                        @if($refund->user)
                                            <a href="{{ route('admin.user.edit', $refund->user) }}" class="hover:text-primary-color hover:underline">
                                                {{ $refund->user->getName() }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-950">{{ money(-((float) $refund->total_amount)) }}</div>
                                    <x-ui.badge color="slate" size="xs">Refund</x-ui.badge>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-gray-600">Refund</div>
                            <x-ui.row-actions class="mt-4">
                                <x-ui.row-action label="Edit refund" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $refund) }}" />
                                <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $refundViewUrl }}" target="_blank" />
                                <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ $refundDownloadUrl }}" />
                            </x-ui.row-actions>
                        </article>
                    @endforeach
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading field="id" label="ID" />
                        <x-ui.list-heading field="received_on" label="Details" />
                        <x-ui.list-heading class="text-center!" label="Amount" field="total_amount" suffix="(incl GST)" />
                        <x-ui.list-heading field="payment_method" class="hidden md:table-cell text-center!" label="Type" />
                        <x-ui.list-heading class="hidden md:table-cell text-center!" label="Status" />
                        <x-ui.list-heading class="hidden lg:table-cell" label="Allocated" />
                        <x-ui.list-heading class="hidden lg:table-cell" label="Unallocated" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($customerPayments as $customerPayment)
                            @php
                                $allocated = (float) ($customerPayment->allocated_amount_sum ?? 0);
                                $unallocatedBeforeRefund = max(0, round(((float) $customerPayment->total_amount) - $allocated, 2));
                                $unallocated = max(0, round($unallocatedBeforeRefund - (float) $customerPayment->refunds->sum('total_amount'), 2));
                                $typeLabel = \App\Models\Payment::paymentMethodLabel((string) ($customerPayment->payment_method ?? ''));
                                $statusLabel = $customerPayment->isRefund()
                                    ? 'Refund'
                                    : $customerPayment->clearanceStatusLabel();
                                $statusTone = $customerPayment->isRefund()
                                    ? 'slate'
                                    : $customerPayment->clearanceStatusTone();
                                $allocatedInvoices = $customerPayment->allocations
                                    ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0 && $allocation->invoice)
                                    ->map(fn ($allocation) => $allocation->invoice)
                                    ->unique('id')
                                    ->values();
                                $replacementDialogData = $paymentReplacementDialogDataById[(string) $customerPayment->id] ?? null;
                                $receiptViewUrl = route('admin.payment.receipt', $customerPayment);
                                $receiptDownloadUrl = route('admin.payment.receipt', ['payment' => $customerPayment, 'download' => 1]);
                            @endphp
                            <tr class="{{ $customerPayment->isPendingBankTransfer() ? 'bg-amber-50/80' : '' }}">
                                <td class="text-center!">
                                    <a href="{{ route('admin.payment.edit', $customerPayment) }}" class="font-semibold text-gray-900 hover:text-primary-color whitespace-nowrap">{{ $customerPayment->id }}</a>
                                </td>
                                <td class="">
                                    <div><x-ui.date-time>{{ $customerPayment->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                    <div class="text-xs text-gray-600">
                                        @if($customerPayment->user)
                                            <a href="{{ route('admin.user.edit', $customerPayment->user) }}" class="hover:text-primary-color hover:underline">
                                                {{ $customerPayment->user->getName() }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-600 md:hidden">{{ $typeLabel }}</div>
                                    <div class="mt-1 md:hidden">
                                        <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    </div>
                                    <div class="text-xs text-gray-600 lg:hidden mt-1">
                                        Alloc: {{ money((float) $allocated) }} · Unalloc: {{ money($unallocated) }}
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        @if($allocatedInvoices->isNotEmpty())
                                            @foreach($allocatedInvoices as $index => $invoice)
                                                @if($index > 0)
                                                    <span>, </span>
                                                @endif
                                                <a href="{{ route('admin.invoice.edit', $invoice) }}" class="text-primary-color hover:underline">
                                                    Invoice #{{ $invoice->invoice_number }}
                                                </a>
                                            @endforeach
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center!">{{ money((float) $customerPayment->total_amount) }}</td>
                                <td class="hidden md:table-cell text-center!">{{ $typeLabel }}</td>
                                <td class="hidden md:table-cell text-center!">
                                    <x-ui.badge :color="$statusTone">{{ $statusLabel }}</x-ui.badge>
                                </td>
                                <td class="hidden lg:table-cell">
                                    {{ money((float) $allocated) }}
                                    <div class="text-xs text-gray-600">
                                        @if($allocatedInvoices->isNotEmpty())
                                            @foreach($allocatedInvoices as $index => $invoice)
                                                @if($index > 0)
                                                    <span>, </span>
                                                @endif
                                                <a href="{{ route('admin.invoice.edit', $invoice) }}" class="text-primary-color hover:underline">
                                                    Invoice #{{ $invoice->invoice_number }}
                                                </a>
                                            @endforeach
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell">{{ money($unallocated) }}</td>
                                <td class="text-center!">
                                    <x-ui.row-actions class="whitespace-nowrap text-sm">
                                        <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $customerPayment) }}" />
                                        @if(! empty($replacementDialogData['candidates'] ?? []))
                                            <x-ui.row-action label="Review matches" icon="fa-solid fa-right-left" tone="neutral"
                                                type="button"
                                                data-dialog="{{ rawurlencode(json_encode($replacementDialogData, JSON_UNESCAPED_UNICODE)) }}"
                                                x-on:click.prevent="openReplacementDialogFromEncoded($el.dataset.dialog)"
                                             />
                                        @endif
                                        <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $receiptViewUrl }}" target="_blank" />
                                        <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ $receiptDownloadUrl }}" />
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                            @foreach($customerPayment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0) as $refund)
                                @php
                                    $refundViewUrl = route('admin.payment.receipt', $refund);
                                    $refundDownloadUrl = route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]);
                                @endphp
                                <tr class="bg-gray-50">
                                    <td class="text-center!">
                                        <a href="{{ route('admin.payment.edit', $refund) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $refund->id }}</a>
                                    </td>
                                    <td>
                                        <div>↳ <x-ui.date-time>{{ $refund->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                    <div class="text-xs text-gray-600">
                                        @if($refund->user)
                                            <a href="{{ route('admin.user.edit', $refund->user) }}" class="hover:text-primary-color hover:underline">
                                                {{ $refund->user->getName() }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                        <div class="text-xs text-gray-600 md:hidden">Refund</div>
                                    </td>
                                    <td class="text-center!">{{ money(-((float) $refund->total_amount)) }}</td>
                                    <td class="hidden md:table-cell text-center!">Refund</td>
                                    <td class="hidden md:table-cell text-center!">
                                        <x-ui.badge color="slate">Refund</x-ui.badge>
                                    </td>
                                    <td class="hidden lg:table-cell">-</td>
                                    <td class="hidden lg:table-cell">-</td>
                                    <td class="w-28">
                                        <x-ui.row-actions class="whitespace-nowrap text-sm">
                                            <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $refund) }}" />
                                            <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $refundViewUrl }}" target="_blank" />
                                            <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ $refundDownloadUrl }}" />
                                        </x-ui.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

        <x-ui.list-pagination :paginator="$customerPayments" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
    @include('admin.payment.partials.replacement-dialog')
    </div>
</x-layout>
