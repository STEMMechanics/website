<x-layout>
    <x-mast title="Owner contributions">
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.cost-centre.index') }}">Cost centres</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <p class="mb-2 text-lg font-semibold tabular-nums">Balance: {{ money($balance / 100) }}</p>
        <p class="mb-4 text-sm text-slate-600">Contributions and repayments across all owners. Pending repayments remain owing and have no effect on the balance until paid.</p>
        <x-ui.table variant="listing" mobileCards>
            <thead><tr><th>Date</th><th>Owner</th><th>Type</th><th>Reference</th><th>Status</th><th class="text-right">Balance change</th></tr></thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td data-label="Date">{{ $record->date }}</td>
                        <td data-mobile-primary>{{ $owners->get($record->user_id)?->getName() ?? 'Owner' }}</td>
                        <td data-label="Type">{{ $record->type }}</td>
                        <td data-label="Reference">{{ $record->reference ?: '—' }}</td>
                        <td data-label="Status">{{ $record->status }}</td>
                        <td data-label="Balance change" @class(['text-right whitespace-nowrap tabular-nums', 'text-red-600' => $record->cents < 0])>{{ money($record->cents / 100) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No owner contributions or repayments recorded.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        <x-ui.list-pagination :paginator="$records" label="transactions" />
    </x-container>
</x-layout>
