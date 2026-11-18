<x-layout>
    <x-mast backRoute="admin.location.index" backTitle="Locations">{{ isset($location) ? 'Edit' : 'Create' }} Location</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.location.' . ( isset($location) ? 'update' : 'store'), $location ?? []) }}">
            @isset($location)
                @method('PUT')
            @endisset
            @csrf
            <div class="mb-4">
                <x-ui.input label="Name" name="name" value="{{ $location->name ?? '' }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Address" name="address" value="{{ $location->address ?? '' }}" />
            </div>
            <x-ui.grid class="mb-4 gap-4 sm:grid-cols-3">
                <x-ui.input class="mb-0" label="Suburb" name="suburb" value="{{ $location->suburb ?? '' }}" />
                <x-ui.input class="mb-0" label="State" name="state" value="{{ $location->state ?? '' }}" />
                <x-ui.input class="mb-0" label="Postcode" name="postcode" value="{{ $location->postcode ?? '' }}" />
            </x-ui.grid>
            <div class="w-full sm:w-1/2">
                <x-ui.input label="Default travel hours" name="default_travel_hours" type="number" min="0" max="2500" step="0.25" value="{{ $location->default_travel_hours ?? 0 }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Address URL" name="address_url" value="{{ $location->address_url ?? '' }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Venue URL" name="url" value="{{ $location->url ?? '' }}" />
            </div>

            <x-ui.editor-actions>
                @isset($location)
                    <x-ui.button type="button" color="outline" href="{{ route('admin.workshop.history', ['location_id' => $location->id]) }}">Workshop history</x-ui.button>
                    <x-ui.button data-editor-delete type="button" color="danger" x-data x-on:click.prevent="$SM.confirmDelete('{{ csrf_token() }}', 'Delete Location?', 'Are you sure you want to delete this location? This action cannot be undone', '{{ route('admin.location.destroy', $location) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
