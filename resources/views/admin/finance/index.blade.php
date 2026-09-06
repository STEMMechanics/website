<x-layout>
    <x-mast title="Finance planning" description="Plan workshop costs, protect reserves and keep track of your time.">
        <x-slot:actions><x-ui.button href="{{ route('admin.bas.index') }}" color="mast">BAS report</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-6">
        <x-ui.dynamic-list name="finance-planning" :showPresets="false">
            <x-ui.preset-views :items="collect(['overview' => 'Funds', 'budgets' => 'Allocations', 'pricing' => 'Pricing & categories', 'suppliers' => 'Suppliers & expenses', 'time' => 'My time', 'drawings' => 'Drawings', 'gst' => 'GST', 'setup' => 'Setup'])->map(fn ($title, $key) => ['title' => $title, 'active' => $tab === $key, 'route' => route('admin.finance.index', ['tab' => $key])])->values()->all()" label="Finance sections" />
            @if($errors->any())
                <div role="alert" class="my-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if(session('success'))<p role="status" class="my-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</p>@endif
            <div class="mt-6 space-y-6">
                @include('admin.finance.'.$tab)
            </div>
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
