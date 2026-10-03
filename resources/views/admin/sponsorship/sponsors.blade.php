<x-layout>
    <x-admin.sponsorship-mast :title="$sponsor->company_name ?: $sponsor->contact_name" :description="$sponsor->email ?: 'Sponsor details and history'">
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.sponsorship.sponsor.manual-support.create', ['sponsor_id' => $sponsor->id]) }}">Add support</x-ui.button></x-slot:actions>
    </x-admin.sponsorship-mast>
    <x-container class="py-5 sm:py-8">
        @if(session('message'))<p class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('message') }}</p>@endif
        @error('cancellation')<p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">Sponsor details</h2>
                    <div class="flex flex-wrap gap-2">
                        @if($sponsor->user)
                            <x-ui.button color="outline" href="{{ route('admin.user.edit', $sponsor->user) }}">Edit linked user</x-ui.button>
                        @endif
                        @if($sponsor->organisation)
                            <x-ui.button color="outline" href="{{ route('admin.organisation.edit', $sponsor->organisation) }}">Edit organisation</x-ui.button>
                        @endif
                    </div>
                </div>
                @if(!$sponsor->user && !$sponsor->organisation)
                    <p class="mt-2 text-sm text-gray-600">This sponsorship is not linked to a website user or organisation record.</p>
                @else
                    <p class="mt-2 text-sm text-gray-600">Contact and organisation details are maintained on the linked record. Use the button above to edit them.</p>
                @endif
                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Contact</dt><dd class="mt-0.5 font-medium text-gray-900">{{ $sponsor->contact_name }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd class="mt-0.5 font-medium text-gray-900">{{ $sponsor->email ?: 'Not supplied' }}</dd></div>
                    <div><dt class="text-gray-500">Sponsor type</dt><dd class="mt-0.5 text-gray-900">{{ ucfirst($sponsor->sponsor_type) }}</dd></div>
                    <div><dt class="text-gray-500">Organisation</dt><dd class="mt-0.5 text-gray-900">{{ $sponsor->company_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Country</dt><dd class="mt-0.5 text-gray-900">{{ $sponsor->country ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Recognition</dt><dd class="mt-0.5 text-gray-900">{{ !$sponsor->isRecognitionPublic() ? 'Private' : ($sponsor->needsRecognitionApproval() ? 'Pending approval' : 'Approved') }}</dd></div>
                </dl>
                @if($sponsor->organisation)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-gray-900">Organisation record</h3>
                            <a href="{{ route('admin.organisation.edit', $sponsor->organisation) }}" class="text-sm font-medium text-primary-color hover:underline">Open organisation</a>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Business identity and billing details are stored with the organisation. Sponsorship contacts are linked to its existing contact records when available.</p>
                        @if($sponsor->organisation->contacts->isNotEmpty())
                            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-700">
                                @foreach($sponsor->organisation->contacts as $contact)
                                    <li><a href="{{ route('admin.user.edit', $contact) }}" class="hover:text-primary-color hover:underline">{{ $contact->getName() }}</a><span class="text-gray-500"> · {{ $contact->email }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-gray-500">No user contacts are linked to this organisation.</p>
                        @endif
                    </div>
                @endif
                <div class="mt-5 border-t border-gray-100 pt-4 text-sm">
                    <h3 class="font-medium text-gray-700">Billing and Square details</h3>
                    <div class="mt-3 grid gap-3 rounded-lg bg-gray-50 p-4 sm:grid-cols-2">
                        <p><span class="text-gray-500">Billing address:</span> {{ implode(', ', array_filter([$sponsor->billing_address, $sponsor->billing_address2, $sponsor->billing_city, $sponsor->billing_state, $sponsor->billing_postcode, $sponsor->country])) ?: 'Not supplied' }}</p>
                        <p><span class="text-gray-500">ABN:</span> {{ $sponsor->abn ?: 'Not supplied' }}</p>
                        <p><span class="text-gray-500">Overseas tax ID:</span> {{ $sponsor->foreign_tax_id ?: 'Not supplied' }}</p>
                        <p class="break-all"><span class="text-gray-500">Square customer ID:</span> {{ $sponsor->square_customer_id ?: 'Not created yet' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                @php($recognitionPending = $sponsor->needsRecognitionApproval())
                <h2 class="text-lg font-semibold text-gray-900">Recognition</h2>
                <p class="mt-1 text-sm text-gray-600">Approved display details appear on the public Sponsors page.</p>
                @if($recognitionPending)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                        Public recognition is waiting for approval. Review the name, website and logo before publishing it.
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.sponsorship.sponsor.recognition', $sponsor) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-4">
                    @csrf @method('PUT')
                    <x-ui.select name="sponsor_type" label="Sponsor type" class="mb-0">
                        <option value="individual" @selected($sponsor->sponsor_type === 'individual')>Individual</option>
                        <option value="organisation" @selected($sponsor->sponsor_type === 'organisation')>Organisation</option>
                    </x-ui.select>
                    <x-ui.input name="display_name" label="Name shown publicly" :value="$sponsor->display_name ?: ($sponsor->sponsor_type === 'organisation' ? $sponsor->company_name : '')" maxlength="255" placeholder="Individual or organisation name" info="Enter the individual or organisation name exactly as it should appear." class="mb-0" />
                    <x-ui.input type="text" inputmode="url" name="website_url" label="Website URL (optional)" :value="$sponsor->website_url" placeholder="example.com.au" maxlength="2048" class="mb-0" />
                    @if($sponsor->recognition_logo_path)<img src="{{ route('admin.sponsorship.sponsor.logo', $sponsor) }}" class="h-16 w-16 rounded-lg border border-gray-200 bg-white object-contain p-2" alt="Current sponsor logo">@endif
                    <x-ui.input name="logo" type="file" label="Replace Major Sponsor logo" accept="image/png,image/jpeg,image/webp" info="PNG, JPG or WebP. Logos are displayed for Major Sponsors only." class="mb-0" />
                    <div class="flex items-center">
                        <input type="hidden" name="recognition_public" value="0">
                        <x-ui.checkbox name="recognition_public" label="Publicly recognise this sponsor" :checked="$sponsor->recognition_public" class="mb-0" />
                    </div>
                    <div class="flex justify-end xl:block">
                        <x-ui.button type="submit" class="xl:w-full">{{ $recognitionPending ? 'Approve and publish' : 'Save recognition details' }}</x-ui.button>
                    </div>
                </form>
            </section>
        </div>

        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-xl font-semibold text-gray-900">Sponsorships</h2><p class="mt-1 text-sm text-gray-600">Payments support STEMMechanics as a whole. Referral source records which project or page brought the sponsor here.</p></div>
                <span class="text-sm text-gray-500">{{ $sponsor->sponsorships->count() }} records</span>
            </div>
            @forelse($sponsor->sponsorships as $sponsorship)
                <article class="mt-4 rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $sponsorship->frequency === 'monthly' ? 'Monthly sponsorship' : 'One-time sponsorship' }} · {{ $sponsorship->currency }} {{ number_format((float) $sponsorship->amount, 2) }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ str_replace('_', ' ', ucfirst($sponsorship->status)) }} · Started {{ $sponsorship->started_at?->format('j M Y') ?? 'not started' }} · Referral: {{ $sponsorship->referral_source ?: 'Direct' }}</p>
                            @if($sponsorship->isRecurring())<p class="mt-1 text-xs text-gray-500">Billing: {{ $sponsorship->billing_method === 'invoice' ? 'Monthly invoice by email' : 'STEMMechanics schedule using the saved Square card' }}{{ $sponsorship->next_payment_date ? ' · next payment '.$sponsorship->next_payment_date->format('j M Y') : '' }}</p>@endif
                        </div>
                        @if($sponsorship->isRecurring() && in_array($sponsorship->status, [\App\Models\Sponsorship::STATUS_ACTIVE, \App\Models\Sponsorship::STATUS_PAST_DUE], true))
                            <form method="POST" action="{{ route('admin.sponsorship.cancel', $sponsorship) }}" onsubmit="return confirm('Cancel this monthly sponsorship? No further monthly payments will be scheduled.')">@csrf<x-ui.button color="outline" type="submit">Cancel monthly sponsorship</x-ui.button></form>
                        @endif
                    </div>
                    <div class="mt-4 overflow-x-auto border-t border-gray-100 pt-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-800">Payment and invoice history</h4>
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="text-xs uppercase text-gray-500"><tr><th class="py-2">Date</th><th>Invoice</th><th>Amount</th><th>Tax treatment</th><th>Status</th><th>Payment method</th><th></th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($sponsorship->payments as $payment)
                                    <tr>
                                        <td class="py-3">{{ $payment->paid_at?->format('j M Y') ?? '—' }}</td>
                                        <td>{{ $payment->invoice_number ?: 'Pending' }}</td>
                                        <td>{{ $sponsorship->currency }} {{ number_format((float) $payment->total_amount, 2) }}<div class="text-xs text-gray-500">GST {{ number_format((float) $payment->gst_amount, 2) }}</div></td>
                                        <td>{{ str_replace('_', ' ', $payment->tax_treatment) }}<div class="text-xs text-gray-500">{{ $payment->sponsor_country }}</div></td>
                                        <td>{{ ucfirst($payment->status) }}</td>
                                        <td>{{ $payment->payment?->payment_method ? \App\Models\Payment::paymentMethodLabel($payment->payment->payment_method) : 'Square' }}</td>
                                        <td class="text-right">
                                            @if($payment->status === \App\Models\SponsorshipPayment::STATUS_COMPLETED && $payment->invoice_id)
                                                <div class="flex justify-end gap-3 whitespace-nowrap"><a class="text-primary-color hover:underline" href="{{ route('admin.sponsorship.payment.invoice', $payment) }}" target="_blank">Download</a><form method="POST" action="{{ route('admin.sponsorship.payment.resend', $payment) }}">@csrf<button type="submit" class="text-primary-color hover:underline">Resend</button></form></div>
                                            @else — @endif
                                        </td>
                                    </tr>
                                    @if($payment->square_payment_id || $payment->square_order_id)
                                        <tr class="bg-gray-50/70 text-[11px] text-gray-500"><td colspan="7" class="py-2">Square payment: <span class="break-all font-mono">{{ $payment->square_payment_id ?: '—' }}</span> · order: <span class="break-all font-mono">{{ $payment->square_order_id ?: '—' }}</span></td></tr>
                                    @endif
                                @empty
                                    <tr><td colspan="7" class="py-5 text-center text-sm text-gray-500">No payments have been recorded for this sponsorship.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <p class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">This sponsor does not have a card sponsorship record yet.</p>
            @endforelse
        </section>

        @if($sponsor->manualSupports->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Other support</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left text-sm"><thead class="text-xs uppercase text-gray-500"><tr><th class="py-2">Method</th><th>Public group</th><th>Value</th><th>Active dates</th><th></th></tr></thead><tbody class="divide-y divide-gray-100">
                        @foreach($sponsor->manualSupports as $support)
                            <tr><td class="py-3">{{ ['cheque' => 'Cheque', 'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'other_benefit' => 'Other benefit'][$support->support_method] ?? ucfirst($support->support_method) }}<div class="text-xs text-gray-500">{{ $support->support_description ?: '—' }}</div></td><td>{{ $support->recognitionLevel?->name ?: '—' }}</td><td>{{ $support->value_amount !== null ? '$'.number_format((float) $support->value_amount, 2).' AUD' : '—' }}</td><td>{{ $support->starts_on?->format('j M Y') }} – {{ $support->ends_on?->format('j M Y') ?? 'Ongoing' }}</td><td class="text-right"><a class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-primary-color hover:bg-sky-100" href="{{ route('admin.sponsorship.sponsor.manual-support.edit', $support) }}" aria-label="Edit support" title="Edit support"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            </section>
        @endif
    </x-container>
</x-layout>
