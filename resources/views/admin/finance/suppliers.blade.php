<x-finance.panel title="Supplier defaults">
    <p class="mb-4 text-sm text-slate-600">Enter the supplier name as it appears on expenses. A single/default category uses 100%; mixed suppliers can split between categories. Defaults apply to uncategorised expenses, including historical records.</p>
    <form method="POST" action="{{ route('admin.finance.supplier') }}">@csrf
        <div class="grid gap-x-5 sm:grid-cols-2"><x-ui.input name="supplier" label="Supplier" required :suggestions="$suppliers->pluck('supplier')->all()" /><x-ui.select name="mode" label="Allocation behaviour"><option value="default">Default category (editable)</option><option value="single">Single category (explicit exception required)</option><option value="split">Default percentage split</option></x-ui.select></div>
        <div class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($categories->where('kind', 'cost')->where('active', true) as $category)<x-ui.input :name="'splits['.$category->id.']'" :label="$category->name.' (%)'" type="number" step="0.01" min="0" max="100" value="0" />@endforeach</div><x-finance.save>Save supplier defaults</x-finance.save>
    </form>
    @foreach($suppliers as $supplier)<p class="mt-3"><strong>{{ $supplier->supplier }}</strong> · {{ $supplier->mode }} · @foreach($planner->decode($supplier->splits) as $id => $percent){{ $categories->firstWhere('id', $id)?->name }} {{ $percent }}% @endforeach</p>@endforeach
</x-finance.panel>
<x-finance.panel title="Expense allocations">
    <form method="GET" action="{{ route('admin.finance.index') }}" class="mb-4 flex items-end gap-3"><input type="hidden" name="tab" value="suppliers"><x-ui.input name="q" label="Search supplier" :value="request('q')" class="mb-0 flex-1" /><x-ui.button type="submit">Search</x-ui.button></form>
    @forelse($expenses as $expense)
        @php($splits = $planner->expenseSplits($expense))
        <details class="mb-3 rounded-xl border border-slate-200 p-4"><summary class="cursor-pointer">{{ $expense->supplier }} · {{ $expense->paid_on?->format('j M Y') }} · {{ money((float) $expense->total_amount - (float) $expense->gst_amount) }} ex GST · {{ $splits ? 'Allocated' : 'Needs allocation' }}</summary>
            <p class="my-3">{{ $expense->description }}</p><form method="POST" action="{{ route('admin.finance.expense', $expense) }}">@csrf
                <div class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($categories->where('kind', 'cost') as $category)<x-ui.input :name="'splits['.$category->id.']'" :label="$category->name" type="number" step="0.01" min="0" :value="($splits[$category->id] ?? 0) / 100" />@endforeach</div>
                <x-ui.select name="budget_id" label="Workshop / invoice budget (optional)"><option value="">General business expense</option>@foreach(\Illuminate\Support\Facades\DB::table('finance_budgets')->orderByDesc('date')->get() as $budget)<option value="{{ $budget->id }}" @selected(\Illuminate\Support\Facades\DB::table('finance_expense_splits')->where('expense_id', $expense->id)->value('budget_id') === $budget->id)>{{ $budget->name }}</option>@endforeach</x-ui.select>
                <x-ui.checkbox name="override" value="1" :id="'override-'.$expense->id" label="Allow an exception to a supplier’s single category rule" /><x-finance.save>Save expense allocation</x-finance.save>
            </form>
        </details>
    @empty<p>No expenses found.</p>@endforelse
    <x-ui.list-pagination :paginator="$expenses" label="expenses" />
</x-finance.panel>

<x-finance.panel title="Upcoming commitments">
    <p class="mb-4 text-sm text-slate-600">Reserve unpaid bills and delivery commitments, including GST. Cash protection uses the higher of each category’s reserve or commitments, so the same money is not reserved twice.</p>
    <form method="POST" action="{{ route('admin.finance.commitment') }}">@csrf
        <div class="grid gap-x-4 sm:grid-cols-2"><x-ui.input name="description" label="Commitment" required /><x-ui.select name="category_id" label="Category">@foreach($categories->where('kind', 'cost') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select><x-ui.input name="due_on" label="Due date" type="date" required /><x-ui.input name="amount" label="Amount including GST" type="number" min="0.01" step="0.01" required /></div><x-finance.save>Reserve commitment</x-finance.save>
    </form>
    @foreach(\Illuminate\Support\Facades\DB::table('finance_commitments')->where('status', 'open')->orderBy('due_on')->get() as $commitment)
        <details class="mt-4 rounded-xl border border-slate-200 p-4"><summary>{{ $commitment->description }} · {{ $commitment->due_on }} · {{ money($commitment->cents / 100) }}</summary>
        <form method="POST" action="{{ route('admin.finance.closeCommitment', $commitment->id) }}" class="mt-3">@csrf<x-ui.select name="status" label="Resolution"><option value="paid">Paid — link an expense</option><option value="cancelled">Cancelled — release reserve</option></x-ui.select><x-ui.input name="expense_id" label="Expense ID (required when paid)" type="number" min="1" /><x-finance.save>Resolve commitment</x-finance.save></form></details>
    @endforeach
</x-finance.panel>
