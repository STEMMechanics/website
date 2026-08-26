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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.input label="Suburb" name="suburb" value="{{ $location->suburb ?? '' }}" />
                <x-ui.input label="State" name="state" value="{{ $location->state ?? '' }}" />
                <x-ui.input label="Postcode" name="postcode" value="{{ $location->postcode ?? '' }}" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.input label="Latitude" name="latitude" type="number" step="0.0000001" value="{{ $location->latitude ?? '' }}" />
                <x-ui.input label="Longitude" name="longitude" type="number" step="0.0000001" value="{{ $location->longitude ?? '' }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Address URL" name="address_url" value="{{ $location->address_url ?? '' }}" />
            </div>
            <div class="mb-4">
                <x-ui.input label="Venue URL" name="url" value="{{ $location->url ?? '' }}" />
            </div>

            <div class="flex justify-end mt-8 gap-4">
                @isset($location)
                    <x-ui.button type="button" color="outline" href="{{ route('admin.workshop.history', ['location_id' => $location->id]) }}">Workshop history</x-ui.button>
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete Location?', 'Are you sure you want to delete this location? This action cannot be undone', '{{ route('admin.location.destroy', $location) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
