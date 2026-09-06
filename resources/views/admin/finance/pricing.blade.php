@php($latest = $versions->first())
@php($rules = $planner->decode($latest->rules))
@php($prices = $planner->decode($latest->prices))
<x-finance.panel title="Create a pricing version">
    <p class="mb-4 text-sm text-slate-600">Start from {{ $latest->name }}. Saving creates a dated version; existing workshop targets stay unchanged. Budget rates are internal funding targets. Public and organisation prices below include GST.</p>
    <form method="POST" action="{{ route('admin.finance.pricing') }}" x-data="{ extra: 0 }">@csrf
        <div class="grid gap-x-5 sm:grid-cols-2"><x-ui.input name="name" label="Version name" required /><x-ui.input name="effective_from" label="Effective from" type="date" :value="now()->toDateString()" required /></div>
        <x-ui.table variant="listing"><thead><tr><th>Category</th><th>Basis</th><th class="text-center">Amount</th><th class="text-center">Additional venue hour</th></tr></thead><tbody>
            @foreach($rules as $key => $rule)<tr><td><x-ui.select :name="'rules['.$key.'][category_id]'" label="Category" :noLabel="true">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($category->id === $rule['category_id'])>{{ $category->name }}</option>@endforeach</x-ui.select></td><td><x-ui.select :name="'rules['.$key.'][basis]'" label="Basis" :noLabel="true">@foreach(['workshop' => 'Per workshop', 'participant' => 'Per participant', 'hour' => 'Per delivery hour', 'venue' => 'Venue: first hour', 'travel' => 'Per billable travel unit'] as $value => $label)<option value="{{ $value }}" @selected($value === $rule['basis'])>{{ $label }}</option>@endforeach</x-ui.select></td><td><x-ui.input :name="'rules['.$key.'][rate]'" label="Amount" :noLabel="true" type="number" min="0" step="0.01" :value="$rule['rate_cents'] / 100" required /></td><td><x-ui.input :name="'rules['.$key.'][extra]'" label="Additional venue hour" :noLabel="true" type="number" min="0" step="0.01" :value="($rule['extra_cents'] ?? 0) / 100" /></td></tr>@endforeach
        </tbody></x-ui.table>
        <template x-for="i in extra" :key="i"><div class="mt-4 grid gap-x-4 sm:grid-cols-3">
            <x-ui.select x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][category_id]'" label="Extra category">@foreach($categories->where('active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</x-ui.select>
            <x-ui.select x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][basis]'" label="Basis"><option value="workshop">Per workshop</option><option value="participant">Per participant</option><option value="hour">Per delivery hour</option><option value="travel">Per billable travel unit</option></x-ui.select>
            <x-ui.input x-bind:name="'rules[' + ({{ count($rules) }} + i - 1) + '][rate]'" label="Amount" type="number" min="0" step="0.01" value="0" />
        </div></template>
        <x-ui.button color="outline" class="my-4" x-on:click="extra++">Add cost rule</x-ui.button>
        <p class="mb-4 text-sm text-slate-600">Set an amount to zero to omit a cost in this version. Travel uses one-way minutes after the first 30 minutes, rounded up to 15-minute units.</p>
        <h3 class="mb-3 text-lg font-semibold">Price per participant, including GST</h3>
        <div class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-4">@foreach(['public' => 'Public', 'organisation' => 'School / library / council'] as $type => $label)@foreach($prices[$type] as $i => $price)<x-ui.input :name="$type.'['.$i.']'" :label="$label.' · '.($i + 1).' hour(s)'" type="number" min="0" step="0.01" :value="$price / 100" required />@endforeach@endforeach</div>
        <div class="grid gap-x-4 sm:grid-cols-2"><x-ui.input name="travel_price" label="Customer travel price per 15 minutes (including GST)" type="number" min="0" step="0.01" :value="($prices['travel_cents'] ?? 3400) / 100" /><x-ui.input name="travel_free_minutes" label="Free one-way travel minutes" type="number" min="0" :value="$prices['travel_free_minutes'] ?? 30" /></div>
        <x-finance.save>Create version</x-finance.save>
    </form>
</x-finance.panel>
<x-finance.panel title="Categories and funding order">
    <p class="mb-4 text-sm text-slate-600">Lower numbers receive funding first. Changing priority changes how available receipts fund targets. Archiving hides a category from new choices; historical allocations remain.</p>
    @foreach($categories as $category)<form method="POST" action="{{ route('admin.finance.category') }}" class="mb-4 grid items-end gap-x-4 sm:grid-cols-4">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><x-ui.input name="name" label="Category" :value="$category->name" required /><x-ui.input name="priority" label="Priority" type="number" min="1" :value="$category->priority" required /><x-ui.select name="active" label="Status"><option value="1" @selected($category->active)>Active</option><option value="0" @selected(!$category->active)>Archived</option></x-ui.select><div class="mb-4 flex justify-end"><x-ui.button type="submit" color="outline">Save</x-ui.button></div></form>@endforeach
    <form method="POST" action="{{ route('admin.finance.category') }}" class="grid items-end gap-x-4 sm:grid-cols-3">@csrf<input type="hidden" name="active" value="1"><x-ui.input name="name" label="New category" required /><x-ui.input name="priority" label="Priority" type="number" min="1" value="80" required /><div class="mb-4 flex justify-end"><x-ui.button type="submit">Add category</x-ui.button></div></form>
</x-finance.panel>
<x-finance.panel title="Version history"><ul class="space-y-2">@foreach($versions as $version)<li>{{ $version->name }} · {{ $version->effective_from }} · Version {{ $version->id }}</li>@endforeach</ul></x-finance.panel>
