<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">Manual Duplicate Review</x-mast>

    <x-container class="py-5 sm:py-8">
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-sky-950">Review selected images</h2>
            <p class="mt-1 text-sm text-sky-900">These images were selected manually from the media list. The automatic scanner does not need to have flagged them before you compare or merge them.</p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <x-ui.badge color="sky">{{ $media->count() }} images selected</x-ui.badge>
                <x-ui.badge color="slate">Manual review</x-ui.badge>
            </div>
        </div>

        @if($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="mt-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            @include('admin.media._duplicate-group', [
                'group' => ['media' => $media],
                'mergeRoute' => route('admin.media.duplicates.merge-manual'),
            ])
        </div>
    </x-container>
</x-layout>
