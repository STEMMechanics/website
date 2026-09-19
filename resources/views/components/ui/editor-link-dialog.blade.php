@once
<template id="sm-editor-link-dialog-template">
    <div class="text-left text-sm text-gray-900">
        <x-ui.select id="link-mode" name="link-mode" label="Link to">
            <option value="internal">An existing page</option>
            <option value="manual">A website or custom URL</option>
        </x-ui.select>
        <div data-link-internal>
            <x-ui.select id="link-internal-select" name="link-internal-select" label="Page" info="Choose a page within this site.">
                <option value="">Choose a page</option>
            </x-ui.select>
        </div>
        <div data-link-manual hidden>
            <x-ui.input id="link-manual-url" label="Link address" placeholder="https://example.com" info="Enter a website address or custom URL." />
        </div>
        <x-ui.checkbox id="link-new-window" label="Open in a new window" info="Enabled by default for external links." />
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
            <x-ui.button id="link-clear-button" type="button" color="danger-outline" class="px-4">Remove link</x-ui.button>
            <div class="ml-auto flex gap-3">
                <x-ui.button id="link-cancel-button" type="button" color="outline" class="px-4">Cancel</x-ui.button>
                <x-ui.button id="link-apply-button" type="button" class="px-4">Apply</x-ui.button>
            </div>
        </div>
    </div>
</template>
@endonce
