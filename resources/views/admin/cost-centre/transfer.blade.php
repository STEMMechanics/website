<x-layout><x-mast title="Transfer funds" backRoute="admin.cost-centre.index" backTitle="Cost centres" /><x-container class="py-5">
    <form method="POST" action="{{ route('admin.cost-centre.transfer') }}" data-record-form>@csrf
        <x-ui.select name="from_category_id" label="From"><option value="">Available business cash</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('from_category_id', $from) === (string) $category->id)>{{ $category->name }}{{ !$category->active ? ' (archived)' : '' }}</option>@endforeach</x-ui.select>
        <x-ui.select name="category_id" label="To cost centre"><option value="">Choose a cost centre</option>@foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select>
        <x-ui.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
        <x-ui.input name="reason" label="Reason" maxlength="255" required />
        <p class="text-sm text-slate-600">Moves allocated funds and records a transfer. Original invoices, expenses and allocations retain their history.</p>
        <x-finance.save>Transfer funds</x-finance.save>
    </form>
</x-container></x-layout>
