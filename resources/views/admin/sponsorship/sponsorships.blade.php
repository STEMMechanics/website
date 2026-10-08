@php
    $baseFilters = request()->except(['page', 'status', 'frequency']);
    $totalRecords = (int) ($statusCounts['all'] ?? 0);
    $pendingRecognitionSponsors = app(\App\Services\SponsorshipRecognitionService::class)->pendingApprovalSponsors();
    $listingTabs = [
        ['title' => 'Active', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['status' => 'active'])), 'active' => $selectedStatus === 'active' && !request()->filled('frequency'), 'count' => (int) ($statusCounts['active'] ?? 0)],
        ['title' => 'Past due', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['status' => 'past_due'])), 'active' => $selectedStatus === 'past_due', 'count' => (int) ($statusCounts['past_due'] ?? 0)],
        ['title' => 'Cancelled', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['status' => 'cancelled'])), 'active' => $selectedStatus === 'cancelled', 'count' => (int) ($statusCounts['cancelled'] ?? 0)],
        ['title' => 'Failed', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['status' => 'failed'])), 'active' => $selectedStatus === 'failed', 'count' => (int) ($statusCounts['failed'] ?? 0)],
        ['title' => 'One-time', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['frequency' => 'one_time'])), 'active' => request('frequency') === 'one_time', 'count' => (int) ($frequencyCounts['one_time'] ?? 0)],
        ['title' => 'All', 'route' => route('admin.sponsorship.index', array_merge($baseFilters, ['status' => 'all'])), 'active' => request('status') === 'all', 'count' => $totalRecords],
    ];
@endphp
<x-layout>
    <x-admin.sponsorship-mast title="Sponsorships" description="View financial and in-kind support together. Open a sponsor to see their full history.">
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.sponsorship.sponsor.manual-support.create') }}">Add sponsor</x-ui.button></x-slot:actions>
    </x-admin.sponsorship-mast>
    <x-container class="py-5 sm:py-8">
        @error('cancellation')<p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror
        @if(session('message'))<p class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('message') }}</p>@endif
        @if($pendingRecognitionSponsors->isNotEmpty())
            <section class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" aria-labelledby="recognition-review-heading">
                <h2 id="recognition-review-heading" class="font-semibold">Sponsor recognition needs review</h2>
                <p class="mt-1 leading-6">Review the public name, website and logo before they appear on the website.</p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                    @foreach($pendingRecognitionSponsors->take(3) as $pendingSponsor)
                        <a href="{{ route('admin.sponsorship.sponsor.show', $pendingSponsor) }}" class="font-medium text-amber-900 underline hover:no-underline">{{ $pendingSponsor->publicLabel() ?: $pendingSponsor->contact_name }}</a>
                    @endforeach
                    @if($pendingRecognitionSponsors->count() > 3)
                        <span class="text-amber-800">+{{ $pendingRecognitionSponsors->count() - 3 }} more</span>
                    @endif
                </div>
            </section>
        @endif

        <x-ui.dynamic-list name="admin-sponsorships" :showPresets="false">
        <x-ui.preset-views :items="$listingTabs" label="Sponsorship status" class="mb-5" />
        <x-ui.collection-controls class="mb-5" label="Search name, organisation or email" />

        <x-ui.table variant="listing" mobileCards>
                <thead>
                    <tr>
                        <x-ui.list-heading label="Sponsor" />
                        <x-ui.list-heading label="Sponsorships" />
                        <x-ui.list-heading class="text-center!" label="Status" />
                        <x-ui.list-heading label="Payment activity" />
                        <x-ui.list-heading class="text-center! whitespace-nowrap" label="Actions" />
                    </tr>
                </thead>
                <tbody data-list-results>
                    @forelse($sponsorships as $sponsorGroup)
                        @php
                            $sponsor = $sponsorGroup->sponsor;
                            $recordStatuses = $sponsorGroup->records->pluck('status')->unique();
                            $hasActiveSupport = false;
                            $hasUpcomingSupport = false;
                            foreach ($sponsorGroup->supports as $support) {
                                $supportActive = $support->starts_on && $support->starts_on->lte(today()) && (! $support->ends_on || $support->ends_on->gte(today()));
                                $hasActiveSupport = $hasActiveSupport || $supportActive;
                                $hasUpcomingSupport = $hasUpcomingSupport || (bool) ($support->starts_on?->gt(today()) ?? false);
                            }
                            $groupStatus = match (true) {
                                $hasActiveSupport || $recordStatuses->contains(\App\Models\Sponsorship::STATUS_ACTIVE) => ['label' => 'Active', 'color' => 'success'],
                                $recordStatuses->contains(\App\Models\Sponsorship::STATUS_PAST_DUE) => ['label' => 'Overdue', 'color' => 'warning'],
                                $hasUpcomingSupport || $recordStatuses->contains(\App\Models\Sponsorship::STATUS_PENDING) => ['label' => 'Pending', 'color' => 'warning'],
                                $recordStatuses->contains(\App\Models\Sponsorship::STATUS_FAILED) => ['label' => 'Failed', 'color' => 'danger'],
                                $recordStatuses->contains(\App\Models\Sponsorship::STATUS_CANCELLED) => ['label' => 'Cancelled', 'color' => 'slate'],
                                $recordStatuses->contains(\App\Models\Sponsorship::STATUS_COMPLETED) => ['label' => 'Completed', 'color' => 'success'],
                                $sponsorGroup->supports->isNotEmpty() => ['label' => 'Ended', 'color' => 'slate'],
                                default => ['label' => 'Unknown', 'color' => 'gray'],
                            };
                            $nextPaymentDate = $sponsorGroup->records
                                ->filter(fn (\App\Models\Sponsorship $record) => $record->frequency === 'monthly'
                                    && in_array($record->status, [\App\Models\Sponsorship::STATUS_PENDING, \App\Models\Sponsorship::STATUS_ACTIVE, \App\Models\Sponsorship::STATUS_PAST_DUE], true)
                                    && $record->next_payment_date)
                                ->pluck('next_payment_date')
                                ->sortBy(fn ($date) => $date->timestamp)
                                ->first();
                            $supportSummaries = $sponsorGroup->supports->groupBy(fn ($support) => implode('|', [
                                $support->support_method,
                                mb_strtolower(trim((string) ($support->support_description ?: 'In-kind support'))),
                                $support->starts_on?->format('Y-m-d') ?? '',
                                $support->ends_on?->format('Y-m-d') ?? '',
                            ]))->map(fn ($entries) => (object) ['support' => $entries->first(), 'count' => $entries->count()]);
                        @endphp
                        <tr>
                            <td data-mobile-primary><a class="font-semibold text-gray-900 hover:text-primary-color" href="{{ route('admin.sponsorship.sponsor.show', $sponsor) }}">{{ $sponsor?->company_name ?: $sponsor?->contact_name }}</a><div class="text-xs text-gray-500">{{ $sponsor?->email }} · {{ ucfirst($sponsor?->sponsor_type ?? 'individual') }}</div></td>
                            <td data-mobile-wide><div class="space-y-1">@foreach($sponsorGroup->records as $record)<div>{{ $record->frequency === 'monthly' ? 'Monthly' : 'One-time' }} · {{ $record->currency }} {{ number_format((float) $record->amount, 2) }}</div>@endforeach @foreach($supportSummaries as $summary)<div>Other support · {{ $summary->support->support_description ?: 'In-kind support' }}<div class="text-xs text-gray-500">{{ $summary->count > 1 ? $summary->count.' records · ' : '' }}{{ $summary->support->starts_on?->format('j M Y') }} – {{ $summary->support->ends_on?->format('j M Y') ?? 'Ongoing' }}</div></div>@endforeach</div></td>
                            <td class="text-center!"><x-ui.badge :color="$groupStatus['color']">{{ $groupStatus['label'] }}</x-ui.badge></td>
                            <td class="whitespace-nowrap">
                                <div class="space-y-1 text-sm">
                                    <div><span class="text-xs text-gray-500">Started:</span> {{ $sponsorGroup->latest_started_at?->format('j M Y') ?? '-' }}</div>
                                    <div><span class="text-xs text-gray-500">Next:</span> {{ $nextPaymentDate?->format('j M Y') ?? '-' }}</div>
                                    <div class="text-xs text-gray-500"><span>Payments:</span> {{ $sponsorGroup->payments_count }}</div>
                                </div>
                            </td>
                            <td data-mobile-actions class="text-center! whitespace-nowrap"><x-ui.row-actions :menu="false"><x-ui.row-action label="View" icon="fa-eye" tone="primary" href="{{ route('admin.sponsorship.sponsor.show', $sponsor) }}" /></x-ui.row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No sponsors match this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table>
        <div class="mt-5">{{ $sponsorships->links() }}</div>
        </x-ui.dynamic-list>

    </x-container>
</x-layout>
