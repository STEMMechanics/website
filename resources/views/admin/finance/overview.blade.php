@if(!$cash['settings']->opening_date)
    <x-finance.panel title="Set your starting point">
        <p>Historical allocations are ready to explore. Set a reconciled opening bank balance and reserves before preparing drawings.</p>
        <x-ui.button href="{{ route('admin.finance.index', ['tab' => 'setup']) }}" class="mt-4">Set opening balances</x-ui.button>
    </x-finance.panel>
@endif
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach(['Recorded cash' => $cash['cash'], 'GST reserved' => max(0, $cash['gst']), 'Other protected funds' => $cash['protected'], 'Available before time target' => $cash['available']] as $title => $amount)
        <x-finance.panel :title="$title"><p class="text-2xl font-semibold">{{ money($amount / 100) }}</p></x-finance.panel>
    @endforeach
</div>
<p class="text-sm text-slate-600">Cash starts with your opening balance and follows recorded payments, expenses, GST settlements and drawings. It is not a live bank balance. Pending drawings and the business buffer are excluded from available cash.</p>
@if($cash['unallocated_income'])<p class="rounded-xl border border-amber-200 bg-amber-50 p-4">{{ money($cash['unallocated_income'] / 100) }} of received income still needs a budget allocation. This is protected from drawings until reviewed.</p>@endif
<x-finance.panel title="Category reserves">
    <x-ui.table variant="listing"><thead><tr><th>Category</th><th class="text-center">Priority</th><th class="text-center">Balance</th></tr></thead><tbody>
        @foreach($categories as $category)<tr><td>{{ $category->name }} @if(!$category->active)<x-ui.badge color="slate">Archived</x-ui.badge>@endif</td><td class="text-center">{{ $category->priority }}</td><td class="text-center whitespace-nowrap">{{ money(($cash['reserves'][$category->id] ?? 0) / 100) }}</td></tr>@endforeach
    </tbody></x-ui.table>
    <p class="mt-3 text-sm text-slate-600">Lower priority numbers receive funding first. Negative balances show spending beyond that category’s funding. Owner remuneration is a planning target; drawings are recorded separately.</p>
    @if($cash['uncategorised'])<p class="mt-3">{{ money($cash['uncategorised'] / 100) }} of expenses since the opening date need categorisation. Their payments are already deducted from cash.</p>@endif
</x-finance.panel>

<x-finance.panel title="Move money between funds">
    <p class="mb-4 text-sm text-slate-600">Cover a category shortfall with available business cash or another uncommitted reserve. A linked workshop keeps its original income and revenue shortfall visible.</p>
    <form method="POST" action="{{ route('admin.finance.transfer') }}">@csrf
        <div class="grid gap-x-4 sm:grid-cols-2"><x-ui.select name="from_category_id" label="From"><option value="">Available business cash</option>@foreach($categories->where('kind', 'cost') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select><x-ui.select name="category_id" label="To category">@foreach($categories->where('kind', 'cost') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select><x-ui.input name="amount" label="Amount" type="number" min="0.01" step="0.01" required /><x-ui.input name="reason" label="Reason" required /></div>
        <x-ui.select name="budget_id" label="Workshop / invoice (optional)"><option value="">General reserve</option>@foreach(\Illuminate\Support\Facades\DB::table('finance_budgets')->orderByDesc('date')->get() as $budget)<option value="{{ $budget->id }}">{{ $budget->name }}</option>@endforeach</x-ui.select><x-finance.save>Transfer funds</x-finance.save>
    </form>
    @foreach(\Illuminate\Support\Facades\DB::table('finance_fund_transfers')->orderByDesc('id')->limit(30)->get() as $transfer)<p class="mt-3 text-sm">{{ $transfer->created_at }} · {{ money($transfer->cents / 100) }} · {{ $categories->firstWhere('id', $transfer->from_category_id)?->name ?? 'Business cash' }} → {{ $categories->firstWhere('id', $transfer->category_id)?->name }} · {{ $transfer->reason }}</p>@endforeach
</x-finance.panel>
