<x-layout title="Download Starting - Order {{ $order->order_number }}">
    <x-mast :back-url="$backUrl" backTitle="Back to Order">
        Download Starting
    </x-mast>

    <x-container class="max-w-2xl py-10 mx-auto">
        @php
            $downloadTitle = $download->title ?: ($download->media?->title ?: $download->media_name);
            $downloadExtension = strtolower((string) pathinfo((string) $download->media_name, PATHINFO_EXTENSION));
            $fallbackThumbnail = asset('/thumbnails/' . ($downloadExtension !== '' ? $downloadExtension : 'unknown') . '.webp');
            $thumbnailUrl = $download->media?->thumbnail ?: $fallbackThumbnail;
            $sizeLabel = ($download->media && $download->media->size !== null)
                ? \App\Helpers::bytesToString((int) $download->media->size)
                : null;
        @endphp

        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm md:p-8">
            <div class="space-y-3">
                <div class="text-sm uppercase tracking-[0.16em] text-gray-500">Download File</div>
                <h1 class="text-3xl font-bold text-gray-900">Your download is starting</h1>
                <p class="text-sm leading-7 text-gray-600">
                    The file should begin downloading automatically. If it does not, click here to download it now.
                </p>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                <div class="flex items-center gap-4">
                    <img
                        src="{{ $thumbnailUrl }}"
                        alt="{{ $downloadTitle }}"
                        class="h-14 w-14 rounded-2xl border border-gray-200 bg-white object-contain p-2"
                    >
                    <div class="min-w-0">
                        <div class="text-base font-semibold text-gray-900">
                            {{ $downloadTitle }}
                        </div>
                        @if($download->media?->file_type)
                            <div class="mt-1 text-sm text-gray-500">{{ $download->media?->file_type }}</div>
                        @endif
                        @if($sizeLabel)
                            <div class="mt-1 text-sm text-gray-500">{{ $sizeLabel }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-7 text-emerald-950">
                Download link ready. If the download does not start in a moment, click here to download it now.
            </div>

            <div class="mt-6 flex flex-wrap justify-between gap-3">
                <x-ui.button color="outline" href="{{ $backUrl }}">Back to Order</x-ui.button>
                <x-ui.button href="{{ $downloadUrl }}">click here to download it now</x-ui.button>
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
