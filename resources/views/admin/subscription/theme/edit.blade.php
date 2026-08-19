<x-layout>
    <x-mast backRoute="admin.subscription.theme.index" backTitle="Subscription Store Themes">{{ $theme->exists ? 'Edit' : 'Create' }} Store Theme</x-mast>
    <x-container class="mt-4">
        <form class="w-full" method="POST" action="{{ $theme->exists ? route('admin.subscription.theme.update', $theme) : route('admin.subscription.theme.store') }}" class="max-w-4xl" x-data="{ matchType: @js(old('match_type', $theme->match_type ?: 'random')) }">
            @csrf
            @if($theme->exists) @method('PUT') @endif
            <div class="grid gap-x-5 md:grid-cols-2">
                <x-ui.input name="name" label="Theme name" :value="$theme->name" info="Shown to administrators in the newsletter theme selector." />
                <x-ui.input name="title" label="Email section heading" :value="$theme->title" />
            </div>
            <x-ui.input type="textarea" name="intro" label="Email introductory text" :value="$theme->intro" rows="3" maxlength="400" />
            <x-ui.select name="category_slugs[]" label="Product categories" multiple :value="$theme->category_slugs ?? []" :options="$categories->map(fn ($category) => ['value' => $category->slug, 'label' => $category->name])->all()" />
            <div class="grid gap-x-5 md:grid-cols-2">
                <x-ui.select name="match_type" label="Product matching" :value="$theme->match_type" x-model="matchType">
                    @foreach($matchTypes as $value => $label)<option value="{{ $value }}" @selected(old('match_type', $theme->match_type) === $value)>{{ $label }}</option>@endforeach
                </x-ui.select>
                <div x-show="['created_within', 'updated_within', 'restocked_within'].includes(matchType)">
                    <x-ui.input type="number" name="match_days" label="Within the last (days)" :value="$theme->match_days ?? 7" min="1" max="365" />
                </div>
            </div>
            <div class="grid gap-x-5 md:grid-cols-2">
                <x-ui.input type="number" name="sort_order" label="Sort order" :value="$theme->sort_order ?? 0" min="0" />
                <x-ui.checkbox name="is_active" label="Available for newsletters" class="mt-0 md:mt-7" :checked="old('is_active', $theme->is_active)" />
            </div>
            <div class="flex gap-3 justify-between flex-row-reverse">
                <x-ui.button type="submit">Save Theme</x-ui.button>
                @if($theme->exists)<x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete store theme?', 'This removes the theme from future newsletters.', '{{ route('admin.subscription.theme.destroy', $theme) }}')">Delete</x-ui.button>@endif
            </div>
        </form>
    </x-container>
</x-layout>
