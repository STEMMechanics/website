<x-layout>
    <x-mast backRoute="forum.category.show" :backRouteParams="[$category->slug]" backTitle="{{ $category->name }}">Create Thread</x-mast>

    <x-container class="py-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <form method="POST" action="{{ route('forum.topic.store', $category->slug) }}">
                @csrf

                <x-ui.input name="title" label="Thread Title" value="{{ old('title') }}" />
                <x-ui.editor name="body" value="{!! old('body') !!}" label="Opening Post" :allowHeadings="false" />

                <div class="mt-6 flex justify-end">
                    <x-ui.button type="submit">Create Thread</x-ui.button>
                </div>
            </form>
        </div>
    </x-container>
</x-layout>
