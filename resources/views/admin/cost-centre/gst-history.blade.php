<x-finance.panel title="BAS / GST history">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">{{ $historyStart->format('M Y') }}–{{ $month->format('M Y') }} · GST component only</p>
        <div class="flex gap-2">
            <x-ui.button color="outline" :href="route('admin.cost-centre.gst', ['month' => $month->copy()->subYear()->format('Y-m')])" aria-label="Previous 12 months"><i class="fa-solid fa-chevron-left mr-2" aria-hidden="true"></i>Earlier</x-ui.button>
            @if($month->copy()->startOfMonth()->lt(now()->startOfMonth()))
                <x-ui.button color="outline" :href="route('admin.cost-centre.gst', ['month' => min($month->copy()->addYear()->format('Y-m'), now()->format('Y-m'))])" aria-label="Next 12 months">Later<i class="fa-solid fa-chevron-right ml-2" aria-hidden="true"></i></x-ui.button>
            @endif
        </div>
    </div>
    <x-ui.table variant="listing" mobileCards>
        <thead><tr><th>Month</th><th class="text-right">Sales GST</th><th class="text-right">Purchase credits</th><th class="text-right">Calculated net GST</th><th class="text-right">GST settled</th><th>Paid / received</th><th>Receipt / note</th></tr></thead>
        <tbody>
            @foreach($history as $row)
                <tr>
                    <td data-label="Month" data-mobile-primary>
                        <a class="font-semibold text-primary-color underline whitespace-nowrap" href="{{ route('admin.cost-centre.gst', ['month' => $row['month']->format('Y-m')]) }}">{{ $row['month']->format('M Y') }}</a>
                        <div class="mt-1"><x-ui.badge :color="$row['settlement'] ? 'success' : 'gray'">{{ $row['settlement'] ? 'Recorded' : 'Not recorded' }}</x-ui.badge></div>
                    </td>
                    <td data-label="Sales GST" class="text-right whitespace-nowrap">{{ money($row['sales'] / 100) }}</td>
                    <td data-label="Purchase credits" class="text-right whitespace-nowrap">{{ money($row['credits'] / 100) }}</td>
                    <td data-label="Calculated net GST" class="text-right whitespace-nowrap font-semibold">{{ money($row['net'] / 100) }}</td>
                    <td data-label="GST settled" class="text-right whitespace-nowrap">{{ $row['settlement'] ? money($row['settlement']->cents / 100) : '—' }}</td>
                    <td data-label="Paid / received" class="whitespace-nowrap">{{ $row['settlement']?->paid_on ?? '—' }}</td>
                    <td data-label="Receipt / note" class="max-w-xs break-words">{{ $row['settlement']?->reference ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
    <p class="mt-3 text-sm text-slate-600">Calculated GST follows the current transaction records. GST settled is the amount you recorded from your BAS; negative amounts represent refunds. This is not a lodgement status.</p>
</x-finance.panel>
