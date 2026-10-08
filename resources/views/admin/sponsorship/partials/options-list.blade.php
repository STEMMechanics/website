@php
    $activeOptions = $options->where('enabled', true);
    $archivedOptions = $options->where('enabled', false);
@endphp

<x-ui.table variant="listing" mobileCards>
    <thead>
        <tr><th>Checkout option</th><th class="text-center!">Shown in</th><th class="text-center!">Frequency</th><th class="text-center!">Amount</th><th class="text-center!">Recognition</th><th class="text-center!">Status</th><th class="text-center!">Actions</th></tr>
    </thead>
    <tbody data-list-results>
        <tr>
            <td data-mobile-primary><span class="font-medium text-gray-900">Custom one-time amount</span></td>
            <td data-label="Shown in" class="text-center!">Business Sponsorship</td>
            <td data-label="Frequency" class="text-center!">One-time</td>
            <td data-label="Amount" class="text-center!">{{ $project->currency }} {{ number_format((float) $project->custom_amount_min, 2) }}–{{ number_format((float) $project->custom_amount_max, 2) }}</td>
            <td data-label="Recognition" class="text-center!"><x-ui.badge color="gray">Not available</x-ui.badge></td>
            <td data-label="Status" class="text-center!">@if($project->allow_custom_amount)<x-ui.badge color="success">Available</x-ui.badge>@else<x-ui.badge color="gray">Disabled</x-ui.badge>@endif</td>
            <td data-mobile-actions class="text-center!"><x-ui.row-actions :menu="false"><x-ui.row-action label="Edit checkout settings" icon="fa-pen-to-square" tone="primary" data-record-editor data-record-title="Checkout settings" href="{{ route('admin.sponsorship.checkout.edit') }}" /></x-ui.row-actions></td>
        </tr>
        @forelse($activeOptions as $option)
            <tr>
                <td data-mobile-primary><span class="font-medium text-gray-900">{{ $option->label }}</span></td>
                <td data-label="Shown in" class="text-center!">{{ [\App\Models\SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT => 'Community Support', 'business' => 'Business Sponsorship', 'both' => 'Both'][$option->checkout_group] ?? 'Both' }}</td>
                <td data-label="Frequency" class="text-center!">{{ $option->frequency === 'monthly' ? 'Monthly' : 'One-time' }}</td>
                <td data-label="Amount" class="text-center!">{{ $project->currency }} {{ number_format((float) $option->amount, 2) }}</td>
                <td data-label="Recognition" class="text-center!">@if($option->recognition_enabled)<x-ui.badge color="success">Available</x-ui.badge>@else<x-ui.badge color="gray">Not available</x-ui.badge>@endif</td>
                <td data-label="Status" class="text-center!">
                    <x-ui.badge color="success">Available</x-ui.badge>
                </td>
                <td data-mobile-actions class="text-center! whitespace-nowrap">
                    <x-ui.row-actions :menu="false">
                        <x-ui.row-action label="Edit" icon="fa-pen-to-square" tone="primary" data-record-editor data-record-title="Edit sponsorship amount" href="{{ route('admin.sponsorship.option.edit', $option) }}" />
                        <form method="POST" action="{{ route('admin.sponsorship.option.destroy', $option) }}" x-data x-on:submit.prevent="SM.confirm('Archive sponsorship amount?', 'This removes it from checkout and keeps existing sponsorship records intact. You can restore it later.', 'Archive amount', confirmed => { if (confirmed) $el.submit() })">
                            @csrf @method('DELETE')
                            <x-ui.row-action type="submit" label="Archive" icon="fa-trash-can" tone="danger" />
                        </form>
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-7 text-center text-gray-500">No preset sponsorship amounts are currently available. Add an amount or restore one from the archive below.</td></tr>
        @endforelse
    </tbody>
</x-ui.table>

@if($archivedOptions->isNotEmpty())
    <details class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Archived sponsorship amounts ({{ $archivedOptions->count() }})</summary>
        <p class="mt-2 text-sm leading-6 text-gray-600">These amounts are no longer offered at checkout. Amounts linked to sponsorship history are kept; unused archived amounts can be deleted permanently.</p>
        <div class="mt-4">
            <x-ui.table variant="listing" mobileCards>
                <thead>
                    <tr><th>Checkout option</th><th class="text-center!">Shown in</th><th class="text-center!">Frequency</th><th class="text-center!">Amount</th><th class="text-center!">Recognition</th><th class="text-center!">Status</th><th class="text-center!">Actions</th></tr>
                </thead>
                <tbody data-list-results>
                    @foreach($archivedOptions as $option)
                        <tr>
                            <td data-mobile-primary><span class="font-medium text-gray-900">{{ $option->label }}</span></td>
                            <td data-label="Shown in" class="text-center!">{{ [\App\Models\SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT => 'Community Support', 'business' => 'Business Sponsorship', 'both' => 'Both'][$option->checkout_group] ?? 'Both' }}</td>
                            <td data-label="Frequency" class="text-center!">{{ $option->frequency === 'monthly' ? 'Monthly' : 'One-time' }}</td>
                            <td data-label="Amount" class="text-center!">{{ $project->currency }} {{ number_format((float) $option->amount, 2) }}</td>
                            <td data-label="Recognition" class="text-center!">@if($option->recognition_enabled)<x-ui.badge color="success">Available</x-ui.badge>@else<x-ui.badge color="gray">Not available</x-ui.badge>@endif</td>
                            <td data-label="Status" class="text-center!"><x-ui.badge color="gray">Archived</x-ui.badge></td>
                            <td data-mobile-actions class="text-center! whitespace-nowrap">
                                <x-ui.row-actions :menu="false">
                                    <x-ui.row-action label="Edit" icon="fa-pen-to-square" tone="primary" data-record-editor data-record-title="Edit or restore sponsorship amount" href="{{ route('admin.sponsorship.option.edit', $option) }}" />
                                    @if((int) $option->sponsorships_count === 0)
                                        <form method="POST" action="{{ route('admin.sponsorship.option.destroy', $option) }}" x-data x-on:submit.prevent="SM.confirm('Delete archived amount permanently?', 'This amount has no sponsorship history and will be removed permanently.', 'Delete amount', confirmed => { if (confirmed) $el.submit() })">
                                            @csrf @method('DELETE')
                                            <x-ui.row-action type="submit" label="Delete" icon="fa-trash-can" tone="danger" />
                                        </form>
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    </details>
@endif
