<x-layout>
    <x-mast description="View sponsorships, payments and invoices.">Manage sponsorships</x-mast>
    @php
        $monthlySponsorships = $sponsor->sponsorships->where('frequency', 'monthly');
    @endphp
    <x-container class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl" x-data>
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
                @error('cancellation')<p class="mb-5 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror

                @if($monthlySponsorships->isNotEmpty())
                <section>
                    <h2 class="text-xl font-semibold text-gray-900">Monthly sponsorships</h2>
                    <div class="mt-4">
                    <x-ui.table variant="listing" mobileCards>
                        <thead>
                            <tr><th>Sponsorship</th><th class="text-center">Amount</th><th class="text-center">Started</th><th class="text-center">Next payment / invoice</th><th class="text-center">Status</th><th class="text-center">Actions</th></tr>
                        </thead>
                        <tbody>
                            @foreach($monthlySponsorships as $sponsorship)
                                @php
                                    $canEditRecognition = in_array($sponsorship->id, $recognitionSponsorshipIds, true);
                                    $canCancel = in_array($sponsorship->status, ['active', 'past_due'], true)
                                        || ($sponsorship->billing_method === 'invoice' && $sponsorship->status === 'pending');
                                    $statusColor = match($sponsorship->status) {
                                        'active', 'completed' => 'success',
                                        'past_due', 'pending' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    };
                                @endphp
                                <tr>
                                    <td data-mobile-primary>
                                        <div class="font-medium text-gray-900">{{ $sponsorship->option?->label ?: 'STEMMechanics sponsorship' }}</div>
                                        <div class="text-xs text-gray-600">{{ $sponsorship->frequency === 'monthly' ? 'Monthly' : 'One-time' }} · STEMMechanics</div>
                                        @if($sponsorship->square_cancel_at)<div class="mt-1 text-xs text-gray-500">Billing scheduled to stop after {{ $sponsorship->square_cancel_at->format('j M Y') }}.</div>@endif
                                    </td>
                                    <td data-label="Amount" class="text-center! whitespace-nowrap">${{ number_format((float) $sponsorship->amount, 2) }} {{ $sponsorship->currency }}{{ $sponsorship->frequency === 'monthly' ? ' / month' : '' }}</td>
                                    <td data-label="Started" class="text-center! whitespace-nowrap">{{ $sponsorship->started_at?->format('j M Y') ?? $sponsorship->created_at?->format('j M Y') ?? '—' }}</td>
                                    <td data-label="Next payment / invoice" class="text-center! whitespace-nowrap">
                                        @if($sponsorship->status === 'active' && $sponsorship->next_payment_date)
                                            {{ $sponsorship->billing_method === 'invoice' ? 'Invoice ' : '' }}{{ $sponsorship->next_payment_date->format('j M Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-label="Status" class="text-center!">
                                        <x-ui.badge :color="$statusColor">{{ str_replace('_', ' ', ucfirst($sponsorship->status)) }}</x-ui.badge>
                                    </td>
                                    <td data-label="Actions" data-mobile-actions class="text-center!">
                                        @if($canEditRecognition || $canCancel)
                                            <x-ui.action-menu :id="'sponsorship-actions-'.$sponsorship->id" title="Actions">
                                                @if($canEditRecognition)
                                                    <x-ui.row-action label="Public recognition" icon="fa-solid fa-pen-to-square" tone="neutral" x-on:click="$refs.recognitionDialog.showModal()" />
                                                @endif
                                                @if($canCancel)
                                                    <form method="POST" action="{{ route('sponsor.manage.cancel', $sponsorship->id) }}" class="contents" x-on:submit.prevent="SM.confirm(@js($sponsorship->billing_method === 'invoice' ? 'Cancel future monthly sponsorship invoices?' : 'Cancel future monthly sponsorship payments?'), @js($sponsorship->billing_method === 'invoice' ? 'No new invoices will be sent. Any invoice already issued can still be paid.' : 'No new recurring payments will be scheduled after the current billing period.'), 'Cancel sponsorship', confirmed => { if (confirmed) $el.submit() })">
                                                        @csrf
                                                        <x-ui.row-action type="submit" label="Cancel" icon="fa-solid fa-ban" tone="danger" />
                                                    </form>
                                                @endif
                                            </x-ui.action-menu>
                                        @else
                                            <span class="text-gray-300" aria-hidden="true">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    </div>
                </section>
                @endif

                <section class="{{ $monthlySponsorships->isNotEmpty() ? 'mt-9 border-t border-gray-200 pt-8' : '' }}">
                <h2 class="text-xl font-semibold text-gray-900">Payment history</h2>
                <div class="mt-4">
                    <x-ui.table variant="listing" mobileCards>
                        <thead>
                            <tr><th>Date</th><th>Sponsorship</th><th class="text-center">Status</th><th class="text-center">Total</th><th class="text-center">Invoice</th><th class="text-center">Actions</th></tr>
                        </thead>
                        <tbody>
                            @forelse($paymentHistory as $item)
                                @php
                                    $payment = $item['payment'];
                                    $record = $item['sponsorship'];
                                    $paymentStatusColor = match($payment->status) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'gray',
                                        default => 'warning',
                                    };
                                    $canEditRecognition = $record->frequency !== 'monthly'
                                        && in_array($record->id, $recognitionSponsorshipIds, true)
                                        && $payment->status === 'completed';
                                @endphp
                                <tr>
                                    <td data-mobile-primary class="whitespace-nowrap">{{ $payment->paid_at?->format('j M Y') ?? $payment->created_at?->format('j M Y') ?? '—' }}</td>
                                    <td data-label="Sponsorship">
                                        <div class="font-medium text-gray-900">{{ $record->option?->label ?: 'STEMMechanics sponsorship' }}</div>
                                        <div class="text-xs text-gray-600">{{ $record->frequency === 'monthly' ? 'Monthly' : 'One-time' }}</div>
                                    </td>
                                    <td data-label="Status" class="text-center!">
                                        <x-ui.badge :color="$paymentStatusColor">{{ ucfirst($payment->status) }}</x-ui.badge>
                                    </td>
                                    <td data-label="Total" class="text-center! whitespace-nowrap">{{ $payment->status === 'completed' ? '$'.number_format((float) $payment->total_amount, 2).' '.$record->currency : '—' }}</td>
                                    <td data-label="Invoice" class="text-center! whitespace-nowrap">
                                        @if($payment->status === 'completed' && $payment->invoice_id)
                                            <a class="font-medium text-primary-color hover:underline" href="{{ route('sponsor.manage.invoice', $payment->id) }}">{{ $payment->invoice_number }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-label="Actions" data-mobile-actions class="text-center!">
                                        @if($canEditRecognition)
                                            <x-ui.action-menu :id="'sponsorship-payment-actions-'.$payment->id" title="Actions">
                                                <x-ui.row-action label="Public recognition" icon="fa-solid fa-pen-to-square" tone="neutral" x-on:click="$refs.recognitionDialog.showModal()" />
                                            </x-ui.action-menu>
                                        @else
                                            <span class="text-gray-300" aria-hidden="true">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-7 text-center text-gray-500">No payment history yet.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
                </section>
            </section>

            @if($recognitionSponsorshipIds !== [])
                <dialog x-ref="recognitionDialog" class="m-auto max-h-[90vh] w-[min(42rem,calc(100%-2rem))] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/50" x-init="$nextTick(() => { if (@js($errors->hasAny(['recognition_public', 'sponsor_type', 'display_name', 'website_url', 'logo']))) $refs.recognitionDialog.showModal() })">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 sm:p-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Public recognition</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600">Choose whether and how STEMMechanics may recognise this sponsorship publicly.</p>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close" x-on:click="$refs.recognitionDialog.close()"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <form method="POST" action="{{ route('sponsor.manage.recognition') }}" enctype="multipart/form-data" class="grid gap-x-4 p-5 sm:grid-cols-2 sm:p-6">
                        @csrf @method('PATCH')
                        <input type="hidden" name="recognition_public" value="0">
                        <div class="mb-4 sm:col-span-2">
                            <x-ui.checkbox name="recognition_public" label="Show my sponsorship publicly" :checked="old('recognition_public', $sponsor->recognition_public)" class="mb-0" />
                            @error('recognition_public')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <x-ui.select name="sponsor_type" label="Sponsor type" class="mb-0">
                            <option value="individual" @selected(old('sponsor_type', $sponsor->sponsor_type) === 'individual')>Individual</option>
                            <option value="organisation" @selected(old('sponsor_type', $sponsor->sponsor_type) === 'organisation')>Organisation</option>
                        </x-ui.select>
                        <x-ui.input name="display_name" label="Name shown publicly" :value="old('display_name', $sponsor->display_name ?: ($sponsor->sponsor_type === 'organisation' ? $sponsor->company_name : ''))" maxlength="255" info="Enter the individual or organisation name exactly as it should appear." />
                        <x-ui.input type="text" inputmode="url" name="website_url" label="Website URL (optional)" placeholder="example.com.au" :value="old('website_url', $sponsor->website_url)" maxlength="2048" />
                        @if($recognitionLogoAvailable)
                            <x-ui.input type="file" name="logo" label="Logo or avatar (optional)" accept="image/png,image/jpeg,image/webp" info="Supported formats: PNG, JPEG or WebP." />
                        @endif
                        @foreach(['sponsor_type', 'display_name', 'website_url', 'logo'] as $field)@error($field)<p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p>@enderror @endforeach
                        <div class="flex justify-end gap-3 sm:col-span-2">
                            <x-ui.button type="button" color="outline" x-on:click="$refs.recognitionDialog.close()">Cancel</x-ui.button>
                            <x-ui.button type="submit">Save recognition</x-ui.button>
                        </div>
                    </form>
                </dialog>
            @endif
        </div>
    </x-container>
</x-layout>
