<x-ui.list-dialog id="product-stock-adjustment" title="Adjust stock" kind="edit" size="small" :centered="true">
    <form data-product-stock-form method="POST">
        @csrf
        @method('PATCH')
        <div class="space-y-3 p-4">
            <p class="text-sm font-semibold text-slate-900" data-stock-product-title></p>
            <p class="text-sm text-slate-600" data-stock-loading role="status">Loading current stock…</p>
            <x-ui.feedback />
            <fieldset data-stock-fields class="hidden space-y-3" disabled>
                <div data-stock-variant-field>
                    <x-ui.select name="variant_id" label="Variant" id="product-stock-variant" class="mb-0" data-stock-variant />
                </div>
                <p id="product-stock-unit-info" class="text-xs text-slate-600" data-stock-unit-info></p>
                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="min-w-0">
                        <span class="block text-xs text-slate-600">Current</span>
                        <strong class="mt-1 block break-words py-2 text-sm tabular-nums" data-stock-current>—</strong>
                    </div>
                    <div>
                        <x-ui.field-label for="product-stock-change" label="Change" class="justify-center pl-0 text-xs text-slate-600" />
                        <div class="mt-1 flex items-center gap-1">
                            <x-ui.button size="small" color="secondary" class="h-10 w-8 px-0" data-stock-change="-1" aria-label="Remove one from stock">−1</x-ui.button>
                            <x-ui.input-control name="change" id="product-stock-change" type="number" min="-1000000" max="1000000" step="1" value="-1" required class="h-10 w-18 px-1 text-center tabular-nums" data-stock-change-input aria-describedby="product-stock-unit-info product-stock-preview-message" />
                            <x-ui.button size="small" color="secondary" class="h-10 w-8 px-0" data-stock-change="1" aria-label="Add one to stock">+1</x-ui.button>
                        </div>
                    </div>
                    <div class="min-w-0 text-right">
                        <span class="block text-xs text-slate-600">After</span>
                        <strong class="mt-1 block break-words py-2 text-sm tabular-nums" data-stock-result role="status" aria-label="Stock after adjustment" aria-live="polite" aria-atomic="true">—</strong>
                    </div>
                </div>
                <p id="product-stock-preview-message" class="hidden text-xs text-slate-600" data-stock-preview-message role="status"></p>
                <div class="flex items-center gap-3">
                    <x-ui.field-label for="product-stock-reason" label="Reason" info="(optional)" class="shrink-0 pl-0" />
                    <x-ui.input-control name="reason" id="product-stock-reason" maxlength="255" placeholder="Opened for workshop use" class="flex-1" />
                </div>
            </fieldset>
            <x-ui.button href="#" size="small" color="primary-outline" data-stock-editor-link class="hidden">Open product editor</x-ui.button>
        </div>
        <div class="sm-dialog-footer">
            <x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button>
            <x-ui.button type="submit" data-stock-save disabled>Save adjustment</x-ui.button>
        </div>
    </form>
</x-ui.list-dialog>
