@php
    $topicSort = trim((string) request()->query('topicSort', ''));
    $topicSortQuery = $topicSort !== '' ? ['topicSort' => $topicSort] : [];
@endphp

<x-layout>
    <x-mast backRoute="forum.category.show" :backRouteParams="array_merge(['categorySlug' => $category->slug], $topicSortQuery)" backTitle="{{ $category->name }}">Create Thread</x-mast>

    <x-container class="py-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <form method="POST" action="{{ route('forum.topic.store', array_merge(['categorySlug' => $category->slug], $topicSortQuery)) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <x-ui.input
                    name="title"
                    label="Thread Title"
                    value="{{ old('title') }}"
                    info="Supports *italic*, **bold**, and ~~strikethrough~~."
                />
                <x-ui.editor name="body" value="{!! old('body') !!}" label="Opening Post" :allowHeadings="false" />

                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4">
                    <label for="topic-attachments" class="block text-sm font-medium text-gray-700">Attachments</label>
                    <input
                        id="topic-attachments"
                        type="file"
                        name="attachments[]"
                        multiple
                        class="mt-3 block w-full text-sm text-gray-700 file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-color hover:file:bg-gray-100"
                    />
                    <p class="mt-2 text-xs text-gray-500">
                        You can upload multiple files to include with the thread post.
                        Max size per file: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize(auth()->user())) }}
                    </p>
                    @if($errors->has('attachments') || $errors->has('attachments.*'))
                        <p class="mt-2 text-xs text-red-600">{{ $errors->first('attachments') ?: $errors->first('attachments.*') }}</p>
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <x-ui.button type="submit">Create Thread</x-ui.button>
                </div>
            </form>
        </div>
    </x-container>
</x-layout>
