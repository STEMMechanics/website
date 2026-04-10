@props(['image'])

<div class="{{ twMerge(['relative','w-full','h-96','mb-4'], $attributes->get('class')) }}">
<div class="w-screen relative left-1/2 -translate-x-1/2 h-96 flex items-center justify-center overflow-hidden">
    <!-- Background image -->
    <img src="{{ $image }}?lg" class="absolute inset-0 w-full h-96 object-cover" />

    <!-- Blur overlay -->
    <div class="absolute inset-0 backdrop-blur-md bg-white/50"></div>

    <!-- Centered foreground image -->
    <img src="{{ $image }}?lg" class="relative z-10 h-full w-auto object-cover origin-center
           scale-100 sm:scale-110 md:scale-125 lg:scale-150 xl:scale-175" />
</div>
</div>