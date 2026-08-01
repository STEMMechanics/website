@php
    $formRoute = route('admin.workshop.history');
    $organisationOptions = $organisations->map(fn ($item) => ['id' => (string) $item->id, 'label' => ($item->parent ? $item->parent->name.' — ' : '').$item->name])->values();
    $contactOptions = $contacts->map(fn ($item) => ['id' => (string) $item->id, 'label' => $item->getName().($item->primaryOrganisation ? ' — '.$item->primaryOrganisation->name : '')])->values();
    $locationOptions = $locations->map(fn ($item) => ['id' => (string) $item->id, 'label' => $item->name])->values();
    $categoryOptions = $categories->map(fn ($item) => ['id' => (string) $item->id, 'label' => $item->name])->values();
    $selectedOrganisationIds = collect(request('organisation_ids', request()->filled('organisation_id') ? [request('organisation_id')] : []))->map('strval')->all();
    $selectedContactIds = collect(request('requested_by_user_ids', request()->filled('requested_by_user_id') ? [request('requested_by_user_id')] : []))->map('strval')->all();
    $selectedLocationIds = collect(request('location_ids', request()->filled('location_id') ? [request('location_id')] : []))->map('strval')->all();
    $selectedCategoryIds = collect(request('category_ids', request()->filled('category_id') ? [request('category_id')] : []))->map('strval')->all();
@endphp

<form method="GET" action="{{ $formRoute }}" class="mb-6 overflow-visible rounded-b-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-col gap-5 p-4">
        <div class="grid gap-4 md:grid-cols-2">
            @include('admin.workshop.partials.report-multi-picker', [
                'pickerId' => 'history_organisation_search', 'fieldName' => 'organisation_ids', 'label' => 'Hosted for',
                'placeholder' => 'Search and add organisations', 'options' => $organisationOptions, 'selectedIds' => $selectedOrganisationIds,
            ])
            @include('admin.workshop.partials.report-multi-picker', [
                'pickerId' => 'history_contact_search', 'fieldName' => 'requested_by_user_ids', 'label' => 'Requested by',
                'placeholder' => 'Search and add contacts', 'options' => $contactOptions, 'selectedIds' => $selectedContactIds,
            ])
            @include('admin.workshop.partials.report-multi-picker', [
                'pickerId' => 'history_location_search', 'fieldName' => 'location_ids', 'label' => 'Locations',
                'placeholder' => 'Search and add locations', 'options' => $locationOptions, 'selectedIds' => $selectedLocationIds,
            ])
            @include('admin.workshop.partials.report-multi-picker', [
                'pickerId' => 'history_category_search', 'fieldName' => 'category_ids', 'label' => 'Categories',
                'placeholder' => 'Search and add categories', 'options' => $categoryOptions, 'selectedIds' => $selectedCategoryIds,
            ])
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.input label="Workshop search" name="search" value="{{ request('search') }}" placeholder="Title, organisation or contact" />
            <x-ui.input type="date" label="From date" name="date_from" value="{{ request('date_from') }}" />
            <x-ui.input type="date" label="To date" name="date_to" value="{{ request('date_to') }}" />
            <div class="md:mt-7">
                <x-ui.checkbox label="Past workshops only" name="past_only" value="1" :checked="request()->boolean('past_only')" />
            </div>
            <x-ui.checkbox label="Include cancelled and drafts" name="include_cancelled" value="1" :checked="request()->boolean('include_cancelled')" />
            <x-ui.checkbox label="Include child organisations" name="include_children" value="1" :checked="request()->boolean('include_children')" />
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3 rounded-b-xl border-t border-gray-200 bg-gray-50 px-4 py-3">
        <x-ui.button color="outline" href="{{ $formRoute }}">Clear filters</x-ui.button>
        <x-ui.button type="submit">Apply filters</x-ui.button>
    </div>
</form>
