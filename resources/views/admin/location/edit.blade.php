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
            <div class="mb-4">
                <x-ui.input label="Address URL" name="address_url" value="{{ $location->address_url ?? '' }}" />
            </div>

            <div class="flex justify-end mt-8 gap-4">
                @isset($location)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete Location?', 'Are you sure you want to delete this location? This action cannot be undone', '{{ route('admin.location.destroy', $location) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
