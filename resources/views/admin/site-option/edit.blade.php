<x-layout>
    <x-mast backRoute="admin.site_option.index" backTitle="Site Options">{{ isset($siteOption) ? 'Edit' : 'Create' }} Site Option</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.site_option.' . (isset($siteOption) ? 'update' : 'store'), $siteOption ?? []) }}">
            @isset($siteOption)
                @method('PUT')
            @endisset
            @csrf

            <x-ui.input
                label="Name"
                name="name"
                value="{{ $siteOption->name ?? '' }}"
                placeholder="document-business-info"
                info="Lowercase letters, numbers, dots, hyphens and underscores only."
            />

            <x-ui.input
                type="textarea"
                label="Value"
                name="value"
                rows="12"
                value="{{ $siteOption->value ?? '' }}"
                info="Supports multiple lines. Use value_to_html when rendering line breaks in templates."
            />

            <div class="flex justify-end mt-8 gap-4">
                @isset($siteOption)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete site option?', 'Are you sure you want to delete this site option?', '{{ route('admin.site_option.destroy', $siteOption) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
