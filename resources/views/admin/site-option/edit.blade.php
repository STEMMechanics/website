@php($inputType = isset($siteOption) ? \App\Models\SiteOption::inputType((string) $siteOption->name) : 'textarea')

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
                placeholder="document.business-info"
                info="{{ isset($siteOption) ? 'Option names cannot be changed after creation.' : 'Lowercase letters, numbers, dots, hyphens and underscores only.' }}"
                :readonly="isset($siteOption)"
            />

            @if($inputType === 'boolean')
                <x-ui.select
                    label="Value"
                    name="value"
                    info="Enable or disable this option."
                >
                    <option value="1" {{ ($siteOption->value ?? '1') === '1' ? 'selected' : '' }}>Enabled</option>
                    <option value="0" {{ ($siteOption->value ?? '') === '0' ? 'selected' : '' }}>Disabled</option>
                </x-ui.select>
            @elseif($inputType === 'number')
                <x-ui.input
                    type="number"
                    label="Value"
                    name="value"
                    value="{{ $siteOption->value ?? '' }}"
                    info="Numeric value for this option."
                />
            @else
                <x-ui.input
                    type="textarea"
                    label="Value"
                    name="value"
                    rows="12"
                    value="{{ $siteOption->value ?? '' }}"
                    info="Supports multiple lines. Use value_to_html when rendering line breaks in templates."
                />
            @endif

            <div class="flex justify-end mt-8 gap-4">
                @if(isset($siteOption) && (string) $siteOption->name === 'minecraft.webhook-secret')
                    <x-ui.button
                        type="submit"
                        color="outline"
                        formaction="{{ route('admin.site_option.generate-secret', $siteOption) }}"
                        formmethod="POST"
                    >
                        Generate New Secret
                    </x-ui.button>
                @endif
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
