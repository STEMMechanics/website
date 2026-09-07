<x-layout>
    <x-mast title="Cost centre" backRoute="admin.cost-centre.index" backTitle="Cost centres" />
    <x-container class="py-5">
        <form method="POST" action="{{ route('admin.cost-centre.store') }}" data-record-form>@csrf
            @if($centre)<input type="hidden" name="id" value="{{ $centre->id }}">@endif
            <x-ui.input name="name" label="Name" :value="$centre->name ?? null" required />
            <x-ui.input name="priority" label="Funding priority" info="Lower numbers receive funding first when income is limited." type="number" min="1" max="1000" :value="$centre->priority ?? 80" required />
            @if($centre && $centre->kind !== 'cost')
                <input type="hidden" name="active" value="1">
                <p class="mb-4 text-sm text-slate-600">This system cost centre is always active.</p>
            @else
                <x-ui.select name="active" label="Status"><option value="1" @selected(old('active', $centre->active ?? true))>Active</option><option value="0" @selected(!old('active', $centre->active ?? true))>Archived</option></x-ui.select>
                <p class="mb-4 text-sm text-slate-600">Archiving retains balances and history. Existing workshop budgets keep their allocations; new pricing versions must use active cost centres.</p>
            @endif
            <x-finance.save>Save cost centre</x-finance.save>
        </form>
    </x-container>
</x-layout>
