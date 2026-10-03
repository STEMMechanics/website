@php
    $hero = \App\Support\HomeHero::content();
    $heroImageUrl = \App\Support\HomeHero::imageUrl($hero);
@endphp
<x-layout
    id="home"
    title="Home"
    description="Hands-on STEM workshops in Cairns and across Queensland, including coding, robotics, creative tech, and community programs."
    :canonical="route('index')"
>
    @pushOnce('head')
        <link rel="preload" as="image" href="{{ $heroImageUrl }}" fetchpriority="high" />
    @endPushOnce
    <style>
        @keyframes home-hero-blob {
            0%, 100% {
                border-radius: 40% 60% 28% 72% / 66% 30% 70% 34%;
            }
            33% {
                border-radius: 55% 45% 36% 64% / 48% 38% 62% 52%;
            }
            66% {
                border-radius: 34% 66% 42% 58% / 58% 44% 56% 42%;
            }
        }

        .home-hero-blob-bg {
            animation: home-hero-blob 18s ease-in-out infinite;
            will-change: border-radius;
        }

        @media (prefers-reduced-motion: reduce) {
            .home-hero-blob-bg {
                animation: none;
            }
        }
    </style>
    <x-home-hero :hero="$hero" :image-url="$heroImageUrl" />
    <section id="events" class="bg-gray-50">
        <x-container class="relative py-16">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="-ml-2 rounded-3xl bg-primary-color px-5 py-2 text-xl font-bold text-white">Upcoming workshops</h2>
                <x-ui.button href="{{ route('workshop.index') }}" color="outline" class="self-start hidden sm:block">View all workshops</x-ui.button>
            </div>
            @if($workshops->isEmpty())
                <x-on-holiday />
            @else
                <div class="grid w-full gap-8 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($workshops as $index => $workshop)
                        <x-panel-workshop :workshop="$workshop" class="{{ $index === 3 ? 'lg:hidden' : '' }}" />
                    @endforeach
                </div>
            @endif

            <div class="sm:hidden text-center">
                <a
                        href="{{ route('workshop.index') }}"
                        class="mt-4 ml-2 inline-flex items-center gap-2 font-semibold text-primary-color transition"
                >
                    View all workshops
                    <i class="fa-solid fa-arrow-right text-sm" aria-hidden="true"></i>
                </a>
            </div>
        </x-container>
    </section>
    <section id="audiences">
        <x-container class="relative bg-gray-50 px-12 py-16">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] mb-4">
                <div class="overflow-hidden sm:min-h-80 lg:order-2 lg:min-h-80 rounded-lg">
                    <img
                        src="{{ asset('home-schools-768.webp') }}"
                        srcset="{{ asset('home-schools-480.webp') }} 480w, {{ asset('home-schools-768.webp') }} 768w, {{ asset('home-schools-1024.webp') }} 1024w"
                        sizes="(min-width: 1024px) 45vw, 100vw" width="4032" height="3024"
                        alt="A workshop scene for schools and groups"
                        class="h-48 w-full object-cover object-center sm:h-64 lg:h-full"
                        loading="lazy"
                    >
                </div>

                <div class="order-2 px-6 sm:px-8 lg:order-1 lg:px-12 flex flex-col">
                    <p class="mt-4 text-sm font-semibold uppercase tracking-[0.22em] text-primary-color sm:mt-0">Workshops for groups</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">We run workshops for schools, organisations, and community groups.</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Planning a session for a school, organisation, OSHC program or community group? We can shape the workshop around your audience, venue and learning goals.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Choose from hands-on creative technology and STEM activities that are practical, engaging and easy to run.</p>
                    </div>

                    <div class="mt-8 mx-auto flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                        <x-ui.button href="{{ route('contact') }}" class="font-normal py-4" color="primary">Enquire about a workshop</x-ui.button>
                    </div>
                </div>
            </div>
        </x-container>
    </section>
{{--    <section id="news" class="py-12">--}}
{{--        <x-container>--}}
{{--            <h2 class="text-2xl font-bold mb-6">Latest Posts</h2>--}}
{{--            @if($posts->isEmpty())--}}
{{--                <x-none-found item="posts" message="No posts have been published at this time" title="" />--}}
{{--            @else--}}
{{--                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">--}}
{{--                    @foreach($posts as $index => $post)--}}
{{--                        <x-panel-post :post="$post" class="{{ $index === 3 ? 'lg:hidden' : '' }}" />--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </x-container>--}}
{{--    </section>--}}
    <section id="skills">
        <x-container class="bg-gray-50 px-12 py-16">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] mb-4">
                <div class="overflow-hidden order-0 sm:min-h-80 lg:min-h-80 rounded-lg">
                    <img
                            src="{{ asset('home-green-screen-480.webp') }}" width="600" height="600"
                            alt="Children building and learning together in a workshop"
                            class="h-48 w-full object-cover object-center sm:h-64 lg:h-full"
                            loading="lazy"
                    >
                </div>

                <div class="order-1 px-6 sm:px-8 lg:px-12 flex flex-col">
                    <p class="mt-4 text-sm font-semibold uppercase tracking-[0.22em] text-primary-color sm:mt-0">Skill development</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Build skills by making, testing and solving problems.</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Workshops combine coding, robotics, creative making and practical problem-solving. Learners build confidence by creating something, testing it and improving it.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">The pace stays friendly and hands-on, with room for teamwork, curiosity and the small experiments that make ideas stick.</p>
                    </div>

                    <div class="mt-8 mx-auto flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                        <x-ui.button color="primary" href="{{ route('workshop.index') }}" class="font-normal py-4">Browse workshops</x-ui.button>
                    </div>
                </div>
            </div>
        </x-container>
    </section>
    <section id="minecraft" class="relative overflow-hidden bg-no-repeat bg-center bg-cover" style="background-image:url({{asset('home-minecraft.webp')}})">
        <x-container class="relative py-48 px-12">
            <div class="rotate-180 absolute top-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 80" class="block w-full h-20" preserveAspectRatio="none">
                    <defs>
                        <pattern id="blocks-random-top" width="480" height="80" patternUnits="userSpaceOnUse">
                            <path d="M0 40 H40 V20 H80 V40 H120 V20 H160 V60 H200 V40 H240 V20 H280 V60 H320 V40 H360 V80 H400 V60 H440 V40 H480 V80 H0 Z" fill="#F9FAFB" />
                        </pattern>
                    </defs>
                    <rect width="1440" height="80" fill="url(#blocks-random-top)" />
                </svg>
            </div>

            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-100">STEMCraft</p>
            <h2 class="my-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl flex items-center gap-2"><img src="{{ asset('home-minecraft-edu.webp') }}" alt="Minecraft Education" class="h-12 shrink-0" />Keep building between workshops.</h2>
            <div class="min-w-0">
                <p class="max-w-none text-base leading-7 text-amber-50">STEMCraft is the online extension of STEMMechanics, giving young makers a place to continue creative building between workshops. Start with the <a href="{{ route('stemcraft.join') }}" class="link text-amber-500! hover:text-white">join guide</a>, read the <a href="{{ route('stemcraft.rules') }}" class="link text-amber-500! hover:text-white">community expectations</a>, or browse the <a href="{{ route('stemcraft.faqs') }}" class="link text-amber-500! hover:text-white">FAQs</a>.</p>
                <p class="mt-4 max-w-none text-base leading-7 text-amber-50">Participants can experiment, create and keep learning with support from the STEMMechanics team.</p>
            </div>

            <div class="flex flex-col items-center mt-3">
                <div class="mt-6">
                    <img src="{{ asset('home-minecraft-address.webp') }}" alt="play.stemcraft.com.au" class="h-12 brightness-110" />
                </div>

                <div class="mt-8 flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                    <x-ui.button color="yellow" href="{{ route('stemcraft.index') }}" class="font-normal py-4">Explore STEMCraft</x-ui.button>
                </div>
            </div>

            <div class="absolute -bottom-1 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 80" class="block w-full h-20" preserveAspectRatio="none">
                    <defs>
                        <pattern id="blocks-random-bottom" width="480" height="80" patternUnits="userSpaceOnUse">
                            <path d="M0 40 H40 V20 H80 V40 H120 V20 H160 V60 H200 V40 H240 V20 H280 V60 H320 V40 H360 V80 H400 V60 H440 V40 H480 V80 H0 Z" fill="#F9FAFB" />
                        </pattern>
                    </defs>
                    <rect width="1440" height="80" fill="url(#blocks-random-bottom)" />
                </svg>
            </div>
        </x-container>
    </section>
    <section id="support" class="relative">
        <x-container class="bg-gray-50 px-12 py-32">
            <div class="grid gap-y-6 lg:gap-0 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] mb-4">
                <div class="order-2 px-6 sm:px-8 lg:order-1 lg:px-12 flex flex-col">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-primary-color">Stay connected</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Keep exploring after the workshop.</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Keep working on projects at home, share what you make in Discord or email us with a question.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">We are happy to help you work through the next step.</p>
                    </div>

                    <div class="mt-8 flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                        <x-ui.button color="primary" href="https://discord.gg/yNzk4x7mpD" class="font-normal py-4">Join Discord</x-ui.button>
                        <x-ui.button color="primary-outline" href="{{ route('contact') }}" class="font-normal py-4">Contact us</x-ui.button>
                    </div>
                </div>

                <div class="order-1 min-h-48 overflow-hidden rounded-lg bg-no-repeat bg-center bg-cover sm:min-h-80 lg:order-2" style="background-image:url({{ asset('home-discord.webp') }})"></div>
            </div>
            <div class="absolute -bottom-1 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 120" class="block w-full h-3" preserveAspectRatio="none">
                    <path
                            d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                            fill="#0069a8"
                    />
                </svg>
            </div>
        </x-container>
    </section>
    <section id="subscribe">
        <x-container class="pt-16 pb-24 px-12 -mb-12 bg-sky-700 relative" inner-class="flex justify-center">
            <div class="max-w-208">
                <h2 class="mb-0 text-3xl text-white">Get workshop updates</h2>
                <p class="mb-6 text-left text-white">Sign up for updates on new workshops, special sessions and what’s happening around STEMMechanics.</p>
                <livewire:email-subscribe />
            </div>
        </x-container>
    </section>
</x-layout>
