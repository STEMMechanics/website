<x-layout>
    <x-admin.settings-mast active="homepage" title="Homepage settings">
    </x-admin.settings-mast>
    <x-container class="mt-5">
        <form method="POST" action="{{ route('admin.site_option.hero.update') }}"
            x-data="{ hero: @js(array_replace($hero, array_intersect_key(old(), $hero))), heroImageUrl: @js($heroImageUrl) }"
            x-on:sm-media-updated.window="if ($event.detail.name === 'hero_image') heroImageUrl = $event.detail.details.thumbnail"
            x-on:click="if ($event.target.closest('button')?.id.startsWith('hero_image_clear_')) heroImageUrl = @js(asset('home-hero-1024.webp'))">
            @csrf
            @method('PUT')
            <h2 class="mb-3 text-xl font-bold text-gray-900">Hero</h2>
            <div class="grid gap-6 rounded-2xl border border-gray-200 bg-white p-5 lg:grid-cols-2">
                <div>
                    <x-ui.media name="hero_image" label="Hero image" :value="$hero['image']" allow_uploads="true" public_usable_only="true" passwordless_only="true" />
                    <x-ui.input name="caption" label="Photo caption" :value="old('caption', $hero['caption'])" x-model="hero.caption" maxlength="200" />
                    <x-ui.input name="eyebrow" label="Small heading" :value="old('eyebrow', $hero['eyebrow'])" x-model="hero.eyebrow" maxlength="80" />
                </div>
                <div>
                    <x-ui.input name="heading" label="Main heading" :value="old('heading', $hero['heading'])" x-model="hero.heading" maxlength="220" required />
                    <x-ui.input type="textarea" name="body" label="Text" :value="old('body', $hero['body'])" x-model="hero.body" rows="18" maxlength="2400" info="Leave a blank line between paragraphs." />
                </div>
            </div>
            <div class="mt-6 mb-3 flex flex-wrap items-center gap-2"><h2 class="text-lg font-bold">Live preview</h2><span class="text-sm text-slate-500">Changes go live when you save.</span></div>
            <div class="overflow-hidden rounded-2xl border border-slate-200"><x-home-hero :hero="$hero" :image-url="$heroImageUrl" :preview="true" /></div>
            <x-ui.editor-actions><x-ui.button type="submit">Save homepage settings</x-ui.button></x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
