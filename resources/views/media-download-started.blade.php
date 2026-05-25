<x-layout title="File Download - {{ $media->title ?: $media->name }}">
    <x-mast>
        File Download
    </x-mast>

    <x-container class="max-w-3xl py-10 mx-auto">
        @php
            $downloadTitle = $media->title ?: $media->name;
            $thumbnailUrl = $media->thumbnail;
            $sizeLabel = $media->size !== null ? \App\Helpers::bytesToString((int) $media->size) : null;
        @endphp

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold text-gray-900">Your download is starting...</h1>
                        <p class="max-w-2xl text-sm leading-7 text-gray-600">
                            The file should begin downloading automatically. If it does not, use the Download Now button below.
                        </p>
                    </div>
                </div>

                <div class="flex gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 items-center">
                    <img
                        src="{{ $thumbnailUrl }}"
                        alt="{{ $downloadTitle }}"
                        class="h-16 w-16 rounded-lg border border-gray-200 bg-white object-contain p-2"
                    >
                    <div class="min-w-0">
                        <div class="text-base font-semibold text-gray-900">
                            {{ $downloadTitle }}
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                            @if($media->file_type)
                                <span>{{ $media->file_type }}</span>
                            @endif
                            @if($sizeLabel)
                                <span>{{ $sizeLabel }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 justify-end">
                    <x-ui.button href="{{ $downloadUrl }}">
                        <i class="fa-solid fa-download mr-2"></i>
                        Download Now
                    </x-ui.button>
                </div>
            </div>
        </section>
    </x-container>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var frame = document.createElement('iframe');
            frame.setAttribute('src', @json($downloadUrl));
            frame.setAttribute('title', 'File download');
            frame.style.display = 'none';
            document.body.appendChild(frame);
        });
    </script>
</x-layout>
