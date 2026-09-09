<x-layout><x-mast title="Transfer funds" backRoute="admin.cost-centre.index" backTitle="Cost centres" /><x-container class="py-5">
    <form x-data="{ source: @js(old('from_category_id', $from) ?? '') }" method="POST" action="{{ route('admin.cost-centre.transfer') }}" data-record-form>@csrf
        <input type="hidden" name="token" value="{{ old('token', (string) \Illuminate\Support\Str::uuid()) }}">
        <x-ui.select name="from_category_id" label="From" x-model="source"><option value="">Available business cash</option><option value="remuneration">My remuneration — {{ money($remunerationAvailable / 100) }} available to forgo</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('from_category_id', $from) === (string) $category->id)>{{ $category->name }}{{ !$category->active ? ' (archived)' : '' }}</option>@endforeach</x-ui.select>
        <p x-show="source === 'remuneration'" x-cloak class="mb-4 text-sm text-slate-600">You are giving up this amount of unpaid remuneration and allocating it to the selected cost centre. It will no longer be available to draw as pay. No bank payment is recorded.</p>
        <x-ui.select name="category_id" label="To cost centre"><option value="">Choose a cost centre</option>@foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select>
        <x-ui.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
        <x-ui.input name="reason" label="Reason" maxlength="255" required />
        <p class="text-sm text-slate-600">Moves allocated funds and records a transfer. Original invoices, expenses and allocations retain their history.</p>
        <x-finance.save>Transfer funds</x-finance.save>
    </form>
</x-container></x-layout>
