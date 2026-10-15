@if($notice['image_url'] !== '' || $notice['text'] !== '')
    <div @class(['mt-6', 'border-t border-gray-300 pt-5' => $notice['show_divider']]) data-shop-shipping-notice>
        @if($notice['image_url'] !== '')
            <img src="{{ $notice['image_url'] }}" alt="{{ $notice['image_alt'] }}" class="mx-auto block h-auto w-full max-w-72" loading="lazy" decoding="async">
        @else
            <div class="px-3">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-primary-color ring-1 ring-gray-200">
                        <i class="fa-solid fa-truck-fast text-sm" aria-hidden="true"></i>
                    </span>
                    <h3 class="text-sm font-semibold leading-snug text-gray-900">Delivery information</h3>
                </div>
                <p class="mt-3 whitespace-pre-line break-words text-pretty text-sm leading-relaxed text-gray-700">{{ $notice['text'] }}</p>
            </div>
        @endif
    </div>
@endif
