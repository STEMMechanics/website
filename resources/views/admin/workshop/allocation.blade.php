<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="\App\Support\WorkshopNavigation::tabs($workshop)">
        <x-slot:actions><x-ui.button color="mast" :href="route('workshop.show', $workshop)" target="_blank" rel="noopener noreferrer">View public page <i class="fa-solid fa-arrow-up-right-from-square ml-2" aria-hidden="true"></i></x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-finance.workshop-review-notice :workshop="$workshop" />
        <h2 class="mb-2 text-lg font-semibold">Cost centre allocation</h2>
        <p class="mb-5 text-sm text-slate-500">{{ $allocation['version']->name }}</p>
        @if($state['current'] || ! $state['ready'])
            <x-ui.badge class="mb-4" :color="$state['current'] ? 'success' : 'secondary'">{{ $state['status'] }}</x-ui.badge>
        @endif
        @unless($state['status'] === 'No allocation required')
        @php
            $values = $allocation['categories']->mapWithKeys(fn ($category) => [$category->id => number_format(($allocation['targets'][$category->id] ?? 0) / 100, 2, '.', '')])->all();
            $rules = json_decode($allocation['version']->rules, true);
            $suppliable = collect($rules)->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')->unique('category_id');
            $selected = $suppliable->mapWithKeys(fn ($rule) => [$rule['category_id'] => (bool) ($allocation['assumptions']['supplied_categories'][$rule['category_id']] ?? ((($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour') && $allocation['assumptions']['venue_supplied']))])->all();
            $planner = app(\App\Services\Finance\FinancePlanner::class);
            $workshopDefaults = [
                'selected' => $selected,
                'supplied' => $planner->targets($rules, array_replace($allocation['assumptions'], ['supplied_categories' => array_fill_keys(array_keys($selected), true)])),
                'notSupplied' => $planner->targets($rules, array_replace($allocation['assumptions'], ['supplied_categories' => array_fill_keys(array_keys($selected), false)])),
            ];
        @endphp
        <form method="POST" action="{{ route('admin.workshop.allocation.store', $workshop) }}" x-data="SM.allocationTally(@js(['values' => $values, 'workshopDefaults' => $workshopDefaults, 'total' => $allocation['total'], 'exact' => false, 'enabled' => $state['ready'] && (bool) ($allocation['budget']->manual ?? false)]))">
            <dl class="mb-6 grid gap-4 sm:grid-cols-3" aria-live="polite">
                <div><dt class="text-sm text-slate-600">Received excluding GST</dt><dd class="text-xl font-semibold tabular-nums" x-text="money(total)"></dd></div>
                <div><dt class="text-sm text-slate-600">Allocation targets</dt><dd class="text-xl font-semibold tabular-nums" x-text="money(allocated)"></dd></div>
                <div :class="remaining === 0 ? 'text-emerald-700' : 'text-amber-700'"><dt class="text-sm" x-text="remaining < 0 ? 'Shortfall' : 'Unallocated'"></dt><dd class="text-xl font-semibold tabular-nums" x-text="money(Math.abs(remaining))"></dd></div>
            </dl>
            @csrf
            <input type="hidden" name="source_hash" value="{{ $state['hash'] }}">
            <input type="hidden" name="revision" value="{{ $allocation['budget'] ? hash('sha256', json_encode((array) $allocation['budget'])) : '' }}">
            @if($suppliable->isNotEmpty())
                <fieldset @disabled(! $state['ready']) x-on:change="refreshWorkshopDefaults()">
                    <legend class="mb-2 text-sm font-semibold">Supplied items</legend>
                    <p class="mb-3 text-sm text-slate-600">Tick items provided at no cost to us. Untick to include their cost in the default allocation.</p>
                    <x-finance.supplied-options :rules="$rules" :categories="$allocation['categories']" :values="$selected" model="supplied" />
                </fieldset>
            @endif
            @if($state['ready'])<x-ui.checkbox name="override" value="1" label="Override defaults" x-model="enabled" x-on:change="refreshWorkshopDefaults()" />@endif
            <x-finance.allocation-fields :categories="$allocation['categories']" prefix="targets" idPrefix="workshop-allocation" :exact="false" totalLabel="Received excluding GST" :shortfall="true" :show-totals="false" />
            @if($state['ready'])
                <x-ui.checkbox class="mt-5" name="outcomes_reviewed" value="1" label="Workshop complete; attendance, cancellations and payment outcomes reviewed" required />
                <x-ui.button class="mt-4" type="submit">Finalise allocation</x-ui.button>
            @elseif($state['status'] !== 'No allocation required')
                <p class="mt-4 text-sm text-slate-600">Finalise after the workshop ends and payment outcomes are resolved.</p>
            @endif
        </form>
        @if($allocation['budget']?->finalised_at)
            <p class="mt-4 text-xs text-slate-500">Last finalised {{ $allocation['budget']->finalised_at }}. Previous revisions are retained.</p>
        @endif
        @endunless
    </x-container>
</x-layout>
