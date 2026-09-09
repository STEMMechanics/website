<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="\App\Support\WorkshopNavigation::tabs($workshop)">
        <x-slot:actions><x-ui.button color="mast" :href="route('workshop.show', $workshop)" target="_blank" rel="noopener noreferrer">View public page <i class="fa-solid fa-arrow-up-right-from-square ml-2" aria-hidden="true"></i></x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Cost centre allocation</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $allocation['version']->name }}</p>
            </div>
            @if($state['current'] || ! $state['ready'])
                <x-ui.badge :color="$state['current'] ? 'success' : 'gray'">{{ $state['status'] }}</x-ui.badge>
            @endif
        </div>
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
            <dl class="mb-8 grid gap-3 sm:grid-cols-3" aria-live="polite">
                <div class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 sm:block"><dt class="text-sm text-slate-600">Received excluding GST</dt><dd class="text-xl font-semibold tabular-nums sm:mt-2" x-text="money(total)"></dd></div>
                <div class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 sm:block"><dt class="text-sm text-slate-600">Allocation targets</dt><dd class="text-xl font-semibold tabular-nums sm:mt-2" x-text="money(allocated)"></dd></div>
                <div class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 sm:block" :class="remaining === 0 ? 'text-emerald-700' : 'text-amber-700'"><dt class="text-sm" x-text="remaining < 0 ? 'Shortfall' : 'Unallocated'"></dt><dd class="text-xl font-semibold tabular-nums sm:mt-2" x-text="money(Math.abs(remaining))"></dd></div>
            </dl>
            @csrf
            <input type="hidden" name="source_hash" value="{{ $state['hash'] }}">
            <input type="hidden" name="revision" value="{{ $allocation['budget'] ? hash('sha256', json_encode((array) $allocation['budget'])) : '' }}">
            @if($suppliable->isNotEmpty())
                <fieldset class="mb-8" @disabled(! $state['ready']) x-on:change="refreshWorkshopDefaults()">
                    <legend class="mb-2 text-sm font-semibold">Supplied items</legend>
                    @unless($state['ready'])
                        <p class="mb-3 text-sm text-slate-600">Supplied items can be changed after the workshop ends and payment outcomes are resolved.</p>
                    @endunless
                    <x-finance.supplied-options :rules="$rules" :categories="$allocation['categories']" :values="$selected" model="supplied" />
                </fieldset>
            @endif
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Allocation amounts</h3>
                @if($state['ready'])
                    <x-ui.checkbox :no-wrapper="true" name="override" value="1" label="Override defaults" x-model="enabled" x-on:change="refreshWorkshopDefaults()" />
                @endif
            </div>
            <x-finance.allocation-fields :categories="$allocation['categories']" prefix="targets" idPrefix="workshop-allocation" :exact="false" totalLabel="Received excluding GST" :shortfall="true" :show-totals="false" />
            @if($state['ready'])
                <div class="mt-8 flex justify-end">
                    <x-ui.button type="submit" :disabled="(bool) $state['current']" x-bind:disabled="{{ $state['current'] ? '!allocationChanged' : 'false' }}">{{ $state['current'] ? 'Update allocation' : 'Finalise allocation' }}</x-ui.button>
                </div>
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
