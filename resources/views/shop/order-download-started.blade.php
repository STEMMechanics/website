<x-layout title="Download Starting - Order {{ $order->order_number }}">
    <x-mast :back-url="$backUrl" backTitle="Back to Order">
        Download Starting
    </x-mast>

    <x-container class="max-w-3xl py-10 mx-auto">
        @php
            $downloadTitle = $download->title ?: ($download->media?->title ?: $download->media_name);
            $downloadExtension = strtolower((string) pathinfo((string) $download->media_name, PATHINFO_EXTENSION));
            $fallbackThumbnail = asset('/thumbnails/' . ($downloadExtension !== '' ? $downloadExtension : 'unknown') . '.webp');
            $thumbnailUrl = $download->media?->thumbnail ?: $fallbackThumbnail;
            $sizeLabel = ($download->media && $download->media->size !== null)
                ? \App\Helpers::bytesToString((int) $download->media->size)
                : null;
        @endphp

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <div class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500">Download File</div>
                        <h1 class="text-3xl font-bold text-gray-900">Your Download Is Starting</h1>
                        <p class="max-w-2xl text-sm leading-7 text-gray-600">
                            The file should begin downloading automatically. If it does not, use the Download Now button below.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <i class="fa-solid fa-download text-base"></i>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-emerald-950">Download Ready</div>
                            <div class="text-xs leading-5 text-emerald-800">The file is ready to open.</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 sm:grid-cols-[auto_1fr] sm:items-center">
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
                            @if($download->media?->file_type)
                                <span>{{ $download->media?->file_type }}</span>
                            @endif
                            @if($sizeLabel)
                                <span>{{ $sizeLabel }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-4">
                    <div class="text-sm leading-6 text-gray-600">
                        If the browser blocks the automatic download, use the button below.
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button color="outline" href="{{ $backUrl }}">Back to Order</x-ui.button>
                        <x-ui.button href="{{ $downloadUrl }}">
                            <i class="fa-solid fa-download mr-2"></i>
                            Download Now
                        </x-ui.button>
                    </div>
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
