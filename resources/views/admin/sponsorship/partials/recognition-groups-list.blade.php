<x-ui.table variant="listing" mobileCards>
    <thead><tr><th>Public group</th><th data-column-align="center">Minimum sponsorship total</th><th data-column-align="center">Order</th><th data-column-align="center">Status</th><th data-column-align="center">Actions</th></tr></thead>
    <tbody data-list-results>
        @forelse($levels as $level)
            <tr>
                <td data-mobile-primary><span class="font-medium text-gray-900">{{ $level->name }}</span></td>
                <td data-label="Minimum total">AUD {{ number_format((float) $level->minimum_total, 2) }}</td>
                <td data-label="Order">{{ $level->sort_order }}</td>
                <td data-label="Status">@if($level->enabled)<x-ui.badge color="success">Active</x-ui.badge>@else<x-ui.badge color="gray">Disabled</x-ui.badge>@endif</td>
                <td data-mobile-actions class="whitespace-nowrap">
                    <x-ui.row-actions :menu="false">
                        <x-ui.row-action label="Edit business sponsor group" icon="fa-pen-to-square" tone="primary" data-record-editor data-record-title="Edit business sponsor group" href="{{ route('admin.sponsorship.group.edit', $level) }}" />
                        @if($level->enabled)
                            <form method="POST" action="{{ route('admin.sponsorship.group.destroy', $level) }}" x-data x-on:submit.prevent="SM.confirm('Remove public group?', 'Sponsors in this group will no longer appear under this recognition category. Existing sponsorship records are kept.', 'Remove group', confirmed => { if (confirmed) $el.submit() })">
                                @csrf @method('DELETE')
                                <x-ui.row-action type="submit" label="Remove business sponsor group" icon="fa-trash-can" tone="danger" />
                            </form>
                        @endif
                    </x-ui.row-actions>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-7 text-center text-gray-500">No business sponsor groups configured.</td></tr>
        @endforelse
    </tbody>
</x-ui.table>
