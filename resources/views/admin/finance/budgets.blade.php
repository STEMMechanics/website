<x-finance.panel title="Preview allocations">
    <p class="mb-4 text-sm text-slate-600">Choose historical or current rates. Workshop previews combine linked ticket invoices and count fixed costs once. Invoice-only previews apply the entered breakdown to each invoice. Existing allocations are skipped.</p>
    <form method="POST" action="{{ route('admin.finance.preview') }}">@csrf
        <div class="grid gap-x-5 md:grid-cols-3">
            <x-ui.input name="from" label="Workshop date from" type="date" :value="now()->startOfYear()->toDateString()" required />
            <x-ui.input name="to" label="Workshop date to" type="date" :value="now()->endOfYear()->toDateString()" required />
            <x-ui.select name="version_id" label="Cost model">@foreach($versions as $version)<option value="{{ $version->id }}">{{ $version->name }} ({{ $version->effective_from }})</option>@endforeach</x-ui.select>
            <x-ui.input name="participants" label="Participants override" type="number" min="0" placeholder="Use workshop ticket count" />
            <x-ui.input name="hours" label="Duration override (hours)" type="number" min="0" step="0.25" placeholder="Use workshop duration" />
            <x-ui.input name="travel_minutes" label="One-way travel (minutes)" type="number" min="0" placeholder="0" />
            <x-ui.select name="venue_supplied" label="Venue supplied"><option value="">Use workshop organisation</option><option value="1">Yes</option><option value="0">No</option></x-ui.select>
            <x-ui.input name="invoice_numbers" label="Or allocate invoice numbers" placeholder="INV-100001, INV-100002" class="md:col-span-2" />
        </div>
        <x-finance.save>Generate preview</x-finance.save>
    </form>
</x-finance.panel>
@if($preview)
<x-finance.panel title="Review before applying">
    <p class="mb-4">Edit category targets where needed. These are internal budget amounts; customer invoices and their GST stay unchanged.</p>
    <form method="POST" action="{{ route('admin.finance.apply') }}">@csrf<input type="hidden" name="token" value="{{ $preview['token'] }}">
        @forelse($preview['rows'] as $key => $row)
            <details class="mb-3 rounded-xl border border-slate-200 p-4" open x-data="{ targets: @js(array_map(fn ($cents) => $cents / 100, $row['targets'])), net: {{ $row['income']['net'] / 100 }}, total() { return Object.values(this.targets).reduce((sum, value) => sum + (Number(value) || 0), 0); }, money(value) { return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value); } }">
                <summary class="cursor-pointer font-semibold">{{ $row['name'] }} · {{ $row['date'] }}</summary>
                @if($row['warning'])<p class="mt-3 text-amber-800">{{ $row['warning'] }}</p>@else
                    <x-ui.checkbox name="selected[]" :id="'allocate-'.$key" :value="$key" :checked="true" label="Apply this allocation" class="my-3" />
                    <p class="my-3 text-sm">{{ $row['assumptions']['participants'] }} participants · {{ $row['assumptions']['hours'] }} hours · {{ $row['assumptions']['venue_supplied'] ? 'Venue supplied' : 'Venue charged' }} · {{ $row['assumptions']['travel_minutes'] }} minutes one-way travel</p>
                    @if($row['suggested_price_cents'] !== null)<p class="my-3 text-sm">Suggested customer total at this version’s rates: {{ money($row['suggested_price_cents'] / 100) }} including GST. Existing invoices are not repriced.</p>@endif
                    <p class="my-3">Received excluding GST: {{ money($row['income']['net'] / 100) }} · Target: <span x-text="money(total())">{{ money(array_sum($row['targets']) / 100) }}</span> · Funding shortfall: <span x-text="money(Math.max(0, total() - net))"></span> · Unallocated: <span x-text="money(Math.max(0, net - total()))"></span></p>
                    <div class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($row['targets'] as $id => $amount)<x-ui.input :name="'targets['.$key.']['.$id.']'" x-model.number="targets[{{ $id }}]" :label="$categories->firstWhere('id', $id)?->name ?? 'Category'" type="number" step="0.01" min="0" :value="number_format($amount / 100, 2, '.', '')" />@endforeach
                    </div>
                @endif
            </details>
        @empty<p>No workshops or invoices matched.</p>@endforelse
        @if(collect($preview['rows'])->contains(fn ($row) => !$row['warning']))<x-finance.save>Apply selected allocations</x-finance.save>@endif
    </form>
</x-finance.panel>
@endif
<x-finance.panel title="Workshop and invoice budgets">
    <form method="GET" action="{{ route('admin.finance.index') }}" class="mb-4 flex items-end gap-3"><input type="hidden" name="tab" value="budgets"><x-ui.input name="q" label="Search allocations" :value="request('q')" class="mb-0 flex-1" /><x-ui.button type="submit">Search</x-ui.button></form>
    <x-ui.table variant="listing"><thead><tr>
        @foreach(['name' => 'Workshop / invoice', 'date' => 'Date'] as $sort => $label)<th data-dynamic-link><a href="{{ request()->fullUrlWithQuery(['sort' => $sort, 'direction' => request('direction') === 'asc' ? 'desc' : 'asc', 'page' => null]) }}">{{ $label }} <i class="fa-solid fa-sort" aria-hidden="true"></i></a></th>@endforeach
        <th class="text-center">Target</th><th class="text-center">Received ex GST</th><th class="text-center">Shortfall</th><th class="text-center">Unallocated</th><th class="text-center">Breakdown</th>
    </tr></thead><tbody>
        @forelse($reports as $report)<tr><td>{{ $report['budget']->name }}</td><td class="text-center whitespace-nowrap">{{ $report['budget']->date }}</td><td class="text-center whitespace-nowrap">{{ money(array_sum($report['targets']) / 100) }}</td><td class="text-center whitespace-nowrap">{{ money($report['income']['net'] / 100) }}</td><td class="text-center whitespace-nowrap"><x-ui.badge :color="$report['funding']['shortfall'] ? 'amber' : 'green'">{{ money($report['funding']['shortfall'] / 100) }}</x-ui.badge></td><td class="text-center whitespace-nowrap">{{ money($report['funding']['surplus'] / 100) }}</td>
        <td class="text-center whitespace-nowrap" ><x-ui.button color="outline" :data-open-dialog="'finance-budget-'.$report['budget']->id">View</x-ui.button>
            <x-ui.list-dialog :id="'finance-budget-'.$report['budget']->id" :title="$report['budget']->name" kind="editor">
            <x-ui.table variant="listing"><thead><tr><th>Category</th><th class="text-center">Target</th><th class="text-center">From income</th><th class="text-center">Support</th><th class="text-center">Spent</th></tr></thead><tbody>@foreach($report['targets'] as $id => $target)<tr><td>{{ $categories->firstWhere('id', $id)?->name }}</td><td class="text-center whitespace-nowrap">{{ money($target / 100) }}</td><td class="text-center whitespace-nowrap">{{ money(($report['funding']['categories'][$id] ?? 0) / 100) }}</td><td class="text-center whitespace-nowrap">{{ money(($report['support'][$id] ?? 0) / 100) }}</td><td class="text-center whitespace-nowrap">{{ money(($report['spent'][$id] ?? 0) / 100) }}</td></tr>@endforeach</tbody></x-ui.table>
            </x-ui.list-dialog>
        </td></tr>@empty<tr><td colspan="7">No allocations yet. Generate a preview above to get started.</td></tr>@endforelse
    </tbody></x-ui.table>
    <x-ui.list-pagination :paginator="$budgets" label="allocations" />
</x-finance.panel>
<x-finance.panel title="Recent allocation batches">
    @forelse($batches as $batch)<details class="mb-3 border-b border-slate-200 pb-3"><summary class="cursor-pointer">Batch {{ $batch->id }} · {{ $batch->created_at }} · {{ $batch->reversed_at ? 'Reversed' : 'Applied' }}</summary><ul class="my-3">@foreach($planner->decode($batch->snapshot) as $row)<li>{{ $row['name'] }} — {{ money(array_sum($row['targets']) / 100) }}</li>@endforeach</ul>
        @if(!$batch->reversed_at)<form method="POST" action="{{ route('admin.finance.reverse', $batch->id) }}">@csrf<x-ui.button type="submit" color="danger">Reverse this batch</x-ui.button></form>@endif
    </details>@empty<p>No batches applied.</p>@endforelse
</x-finance.panel>
