@php
    $isMatrix = ($reportType ?? 'history') === 'matrix';
    $formRoute = $isMatrix ? route('admin.workshop.coverage') : route('admin.workshop.history');
@endphp

<form method="GET" action="{{ $formRoute }}" class="mb-6 overflow-visible rounded-b-xl border border-gray-200 bg-white shadow-sm">
    <div class="grid gap-x-5 gap-y-2 p-4 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.input label="Workshop search" name="search" value="{{ request('search') }}" placeholder="Title, organisation or contact" />
        <x-ui.select label="Hosted for" name="organisation_id">
            <option value="">All organisations</option>
            @foreach($organisations as $organisation)
                <option value="{{ $organisation->id }}" @selected(request('organisation_id') === (string) $organisation->id)>
                    {{ $organisation->parent ? $organisation->parent->name.' — ' : '' }}{{ $organisation->name }}
                </option>
            @endforeach
        </x-ui.select>
        <x-ui.select label="Requested by" name="requested_by_user_id">
            <option value="">All contacts</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}" @selected(request('requested_by_user_id') === (string) $contact->id)>{{ $contact->getName() }}{{ $contact->company ? ' — '.$contact->company : '' }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select label="Location" name="location_id">
            <option value="">All locations</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @selected(request('location_id') === (string) $location->id)>{{ $location->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select label="Category" name="category_id">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input type="date" label="From date" name="date_from" value="{{ request('date_from') }}" />
        <x-ui.input type="date" label="To date" name="date_to" value="{{ request('date_to') }}" />
        <div class="md:mt-7">
            <x-ui.checkbox label="Past workshops only" name="past_only" value="1" :checked="request()->boolean('past_only')" />
        </div>
        <x-ui.checkbox label="Include cancelled and drafts" name="include_cancelled" value="1" :checked="request()->boolean('include_cancelled')" />
        <x-ui.checkbox label="Include child organisations" name="include_children" value="1" :checked="request()->boolean('include_children')" />
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 rounded-b-xl">
        <x-ui.button color="outline" href="{{ $formRoute }}">Clear filters</x-ui.button>
        <x-ui.button type="submit">Apply filters</x-ui.button>
    </div>
</form>
