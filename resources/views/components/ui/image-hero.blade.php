@props(['image'])

<div class="relative">
    <div class="z-20 absolute bottom-0 left-0 w-full overflow-hidden leading-none">
        <svg viewBox="0 0 1440 120" class="block w-full h-2" preserveAspectRatio="none">
            <path
                    d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                    fill="#f6f7f8"
            />
        </svg>
    </div>

    <div class="{{ twMerge(['relative','w-full','h-96','flex','items-center','justify-center',], $attributes->get('class')) }}">

        <!-- Background image -->
        <img src="{{ $image }}?lg" class="absolute inset-0 w-full h-full object-cover" />

        <!-- Blur overlay -->
        <div class="absolute inset-0 backdrop-blur-md bg-white/50"></div>

        <!-- Centered foreground image -->
        <img src="{{ $image }}?lg" class="relative z-10 h-full w-auto object-cover" />
    </div>
</div>
