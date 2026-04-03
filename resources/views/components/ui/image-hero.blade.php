@props(['image'])

<div class="relative">
    <div class="{{ twMerge(['relative','w-full','h-96','flex','items-center','justify-center','rounded-3xl','overflow-hidden'], $attributes->get('class')) }}">

        <!-- Background image -->
        <img src="{{ $image }}?lg" class="absolute inset-0 w-full h-full object-cover" />

        <!-- Blur overlay -->
        <div class="absolute inset-0 backdrop-blur-md bg-white/50"></div>

        <!-- Centered foreground image -->
        <img src="{{ $image }}?lg" class="relative z-10 max-h-full object-contain" />
    </div>
</div>
