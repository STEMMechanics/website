<x-layout title="Verify Download Access - Order {{ $order->order_number }}">
    <x-mast :back-url="$backUrl" backTitle="Back to Order">
        Verify Download Access
    </x-mast>

    <x-container class="max-w-2xl py-10 mx-auto">
        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm md:p-8">
            <div class="space-y-3">
                <div class="text-sm uppercase tracking-[0.16em] text-gray-500">Download File</div>
                <h1 class="text-3xl font-bold text-gray-900">Confirm the order email</h1>
                <p class="text-sm leading-7 text-gray-600">
                    To protect digital files, downloads require the email address linked to order <span class="font-bold">{{ $order->order_number }}</span>.
                    The emailed download link expires after 15 minutes.
                </p>
            </div>

            @php
                $downloadTitle = $download->title ?: ($download->media?->title ?: $download->media_name);
                $downloadExtension = strtolower((string) pathinfo((string) $download->media_name, PATHINFO_EXTENSION));
                $fallbackThumbnail = asset('/thumbnails/' . ($downloadExtension !== '' ? $downloadExtension : 'unknown') . '.webp');
                $thumbnailUrl = $download->media?->thumbnail ?: $fallbackThumbnail;
                $sizeLabel = ($download->media && $download->media->size !== null)
                    ? \App\Helpers::bytesToString((int) $download->media->size)
                    : null;
            @endphp
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

            <form method="POST" action="{{ $verifyActionUrl }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="guest-download-email" class="mb-2 block text-sm font-medium text-gray-700">Order email address</label>
                    <input
                        id="guest-download-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="block w-full rounded-xl border border-gray-300 px-4 py-3 text-base text-gray-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    >
                    @error('email')
                        <div class="mt-2 text-sm text-rose-700">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3 justify-between">
                    <x-ui.button color="outline" href="{{ $backUrl }}">Back to Order</x-ui.button>
                    <x-ui.button type="submit">Unlock Download</x-ui.button>
                </div>
            </form>
        </section>
    </x-container>
</x-layout>
