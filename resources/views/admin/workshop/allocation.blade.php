<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops"><x-slot:actions><x-ui.button color="mast" :href="route('admin.workshop.edit', $workshop)">View workshop</x-ui.button></x-slot:actions></x-mast>
    <x-container class="py-5">
        <x-finance.panel title="Cost centre allocation">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><span class="text-sm text-slate-500">{{ $allocation['version']->name }}</span><x-ui.badge :color="$state['current'] ? 'success' : 'warning'">{{ $state['status'] }}</x-ui.badge></div>
            @php
                $values = $allocation['categories']->mapWithKeys(fn ($category) => [$category->id => number_format(($allocation['targets'][$category->id] ?? 0) / 100, 2, '.', '')])->all();
                $defaultValues = $allocation['categories']->mapWithKeys(fn ($category) => [$category->id => number_format(($allocation['suggestedTargets'][$category->id] ?? 0) / 100, 2, '.', '')])->all();
            @endphp
            <form method="POST" action="{{ route('admin.workshop.allocation.store', $workshop) }}" x-data="SM.allocationTally(@js(['values' => $values, 'total' => $allocation['total'], 'exact' => false, 'enabled' => $state['ready'] && (bool) ($allocation['budget']->manual ?? false)]))">
                @csrf
                <input type="hidden" name="source_hash" value="{{ $state['hash'] }}">
                <input type="hidden" name="revision" value="{{ $allocation['budget'] ? hash('sha256', json_encode((array) $allocation['budget'])) : '' }}">
                @if($state['ready'])<x-ui.checkbox name="override" value="1" label="Override defaults" x-model="enabled" x-on:change="if (!enabled) values = JSON.parse($el.dataset.defaults)" :data-defaults="json_encode($defaultValues)" />@endif
                <x-finance.allocation-fields :categories="$allocation['categories']" prefix="targets" idPrefix="workshop-allocation" :exact="false" totalLabel="Received excluding GST" :shortfall="true" />
                @if($state['ready'])
                    <x-ui.checkbox class="mt-5" name="outcomes_reviewed" value="1" label="Workshop complete; attendance, cancellations and payment outcomes reviewed" required />
                    <x-ui.button class="mt-4" type="submit">Finalise allocation</x-ui.button>
                @else
                    <p class="mt-4 text-sm text-slate-600">Finalise after the workshop ends and payment outcomes are resolved.</p>
                @endif
            </form>
            @if($allocation['budget']?->finalised_at)
                <p class="mt-4 text-xs text-slate-500">Last finalised {{ $allocation['budget']->finalised_at }}. Previous revisions are retained.</p>
            @endif
        </x-finance.panel>
    </x-container>
</x-layout>
