<x-layout>
    <x-admin.sponsorship-mast :title="$level->exists ? 'Edit business sponsor group' : 'Add business sponsor group'" />
    <x-container class="py-5 sm:py-8">
        <form method="POST" action="{{ $level->exists ? route('admin.sponsorship.group.update', $level) : route('admin.sponsorship.group.store') }}" class="mx-auto max-w-2xl" data-record-form>
            @csrf
            @if($level->exists) @method('PUT') @endif
            <x-ui.input name="name" label="Public group name" :value="$level->name" maxlength="100" required />
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-ui.input name="minimum_total" label="Minimum sponsorship total (AUD)" type="number" min="0" max="10000000" step="0.01" :value="old('minimum_total', $level->minimum_total ?? 0)" required />
                <x-ui.input name="sort_order" label="Display order" type="number" min="0" max="100000" :value="old('sort_order', $level->sort_order ?? 0)" />
                <div class="sm:col-span-2">
                    <input type="hidden" name="enabled" value="0">
                    <x-ui.checkbox name="enabled" label="Use this group for public recognition" :checked="old('enabled', $level->exists ? $level->enabled : true)" class="mb-0" />
                </div>
            </div>
            <x-ui.editor-actions class="justify-end gap-3">
                <x-ui.button color="outline" type="button" data-close-dialog>Cancel</x-ui.button>
                <x-ui.button type="submit">Save group</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
