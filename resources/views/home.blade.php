<x-layout
    id="home"
    title="Home"
    description="Hands-on STEM workshops in Cairns and across Queensland, including coding, robotics, creative tech, and community programs."
    :canonical="route('index')"
>
    <section id="banner" class="bg-center bg-no-repeat bg-cover" style="background-image:linear-gradient(to right, rgba(0,0,0,.7),rgba(0,0,0,.2)),url({{asset('home-hero.webp')}})">
        <x-container class="py-32 relative">
            <h2 class="text-3xl text-white font-bold mb-4">Join the fun!</h2>
            <p class="text-white max-w-2xl mb-3">To keep up with our ever-changing world, it's important to encourage and support a new generation of curious minds who love science, engineering, art, and leadership.</p>
            <p class="text-white max-w-2xl">Our fun and exciting workshops can unlock countless opportunities for new ideas and improvements, giving kids the skills and tools they need to solve any problem that comes their way.</p>
            <p class="absolute bottom-3 right-5 bg-black bg-opacity-75 text-white text-xs px-3 py-1 rounded">Steady Hand Game in Ravenshoe</p>
        </x-container>
    </section>
    <section id="events" class="bg-gray-50">
        <x-container class="relative py-16">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="-ml-2 text-2xl font-bold bg-sky-500 text-white px-5 py-2 rounded-3xl">Upcoming workshops</h2>
                <x-ui.button href="{{ route('workshop.index') }}" color="outline" class="self-start">View all workshops</x-ui.button>
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

            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 120" class="block w-full h-3" preserveAspectRatio="none">
                    <path
                            d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                            fill="#fff1f2"
                    />
                </svg>
            </div>
        </x-container>
    </section>
    <section id="audiences">
        <x-container class="relative py-16 px-12 bg-rose-50">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] mb-4">
                <div class="overflow-hidden sm:min-h-80 lg:order-2 lg:min-h-80 rounded-lg">
                    <img
                        src="{{ asset('home-schools.webp') }}"
                        alt="A workshop scene for schools and groups"
                        class="h-48 w-full object-cover object-center sm:h-64 lg:h-full"
                        loading="lazy"
                    >
                </div>

                <div class="order-2 px-6 sm:px-8 lg:order-1 lg:px-12 flex flex-col">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-rose-500 mt-4 sm:mt-0">Workshops for groups</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">We run workshops for schools, organisations, and community groups.</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Whether you are planning something for a school, an organisation, an OSHC program, or another group setting, we can tailor a workshop to suit the audience, the venue, and the learning goals you want to achieve.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Tell us what you are looking for and we can shape the session around your group, from hands-on creative tech to STEM activities that are practical, engaging, and easy to run.</p>
                    </div>

                    <div class="mt-8">
                        <x-ui.button href="{{ route('contact') }}" class="font-normal">Enquire about a workshop</x-ui.button>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 120" class="block w-full h-3" preserveAspectRatio="none">
                    <path
                            d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                            fill="#ecfdf5"
                    />
                </svg>
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
        <x-container class="py-16 px-12 bg-emerald-50">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] mb-4">
                <div class="overflow-hidden order-0 sm:min-h-80 lg:min-h-80 rounded-lg">
                    <img
                            src="{{ asset('home-green-screen.webp') }}"
                            alt="Children building and learning together in a workshop"
                            class="h-48 w-full object-cover object-center sm:h-64 lg:h-full"
                            loading="lazy"
                    >
                </div>

                <div class="order-1 px-6 sm:px-8 lg:px-12 flex flex-col">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-emerald-500 mt-4 sm:mt-0">Skill development</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Build skills while having a great time.</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Each workshop blends coding, robotics, creative making, and practical problem-solving so learners can build confidence while creating something they are proud of. Activities are set up to be approachable first, then stretched with just enough challenge to keep everyone engaged.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">We keep the pace friendly and hands-on, with room for curiosity, teamwork, and the kind of experimentation that helps ideas stick. That usually means plenty of trying, tweaking, and celebrating the small wins along the way.</p>
                    </div>

                    <div class="mt-8">
                        <x-ui.button color="success" href="{{ route('workshop.index') }}" class="font-normal">Explore Workshops</x-ui.button>
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
                            <path d="M0 40 H40 V20 H80 V40 H120 V20 H160 V60 H200 V40 H240 V20 H280 V60 H320 V40 H360 V80 H400 V60 H440 V40 H480 V80 H0 Z" fill="#ecfdf5" />
                        </pattern>
                    </defs>
                    <rect width="1440" height="80" fill="url(#blocks-random-top)" />
                </svg>
            </div>

            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-100">Minecraft</p>
            <h2 class="my-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl flex items-center gap-2"><img src="{{ asset('home-minecraft-edu.webp') }}" alt="Minecraft Education" class="h-12 shrink-0" />Play on STEMCraft.</h2>
            <div class="min-w-0">
                <p class="max-w-none text-base leading-7 text-amber-50">STEMCraft is our Minecraft space for collaborative builds, weekly challenges, and family-friendly play. Start with the <a href="{{ route('stemcraft.join') }}" class="link text-amber-500! hover:text-white">join guide</a>, read the <a href="{{ route('stemcraft.rules') }}" class="link text-amber-500! hover:text-white">rules</a>, or browse the <a href="{{ route('stemcraft.punishments') }}" class="link text-amber-500! hover:text-white">public punishments log</a>.</p>
                <p class="mt-4 max-w-none text-base leading-7 text-amber-50">We also run workshops on the server, both online and offline, where players experiment, build together, and learn playful mechanics beyond vanilla Minecraft.</p>
            </div>

            <div class="flex flex-col items-center mt-3">
                <div class="mt-6">
                    <img src="{{ asset('home-minecraft-address.webp') }}" alt="play.stemcraft.com.au" class="h-12 brightness-110" />
                </div>

                <div class="mt-8 flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                    <x-ui.button color="yellow" href="{{ route('stemcraft.index') }}">STEMCraft Overview</x-ui.button>
                    <x-ui.button color="yellow-outline" href="{{ route('stemcraft.join') }}">How to Join</x-ui.button>
                    <x-ui.button color="yellow-outline" href="{{ route('stemcraft.rules') }}">Server Rules</x-ui.button>
                </div>
            </div>

            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 80" class="block w-full h-20" preserveAspectRatio="none">
                    <defs>
                        <pattern id="blocks-random-bottom" width="480" height="80" patternUnits="userSpaceOnUse">
                            <path d="M0 40 H40 V20 H80 V40 H120 V20 H160 V60 H200 V40 H240 V20 H280 V60 H320 V40 H360 V80 H400 V60 H440 V40 H480 V80 H0 Z" fill="#f5f3ff" />
                        </pattern>
                    </defs>
                    <rect width="1440" height="80" fill="url(#blocks-random-bottom)" />
                </svg>
            </div>
        </x-container>
    </section>
    <section id="support" class="relative">
        <x-container class="py-32 px-12 bg-violet-50">
            <div class="grid gap-y-6 lg:gap-0 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] mb-4">
                <div class="order-2 px-6 sm:px-8 lg:order-1 lg:px-12 flex flex-col">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-violet-500">Stay connected</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">And the support doesn't stop!</h2>
                    <div class="flex-1">
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">Though the workshop has come to a close, we remain available to assist you via email and Discord with any projects you undertake at home. We are always happy to help.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">If you get stuck, contact us and we’ll help you work through it.</p>
                    </div>

                    <div class="mt-8 flex gap-3 flex-col w-full sm:flex-row sm:justify-center">
                        <x-ui.button color="purple" href="https://discord.gg/yNzk4x7mpD" class="font-normal">Join Discord</x-ui.button>
                        <x-ui.button color="purple-outline" href="{{ route('forum.index') }}" class="font-normal">View Discussions</x-ui.button>
                    </div>
                </div>

                <div class="order-1 min-h-48 overflow-hidden rounded-lg bg-no-repeat bg-center bg-cover sm:min-h-80 lg:order-2" style="background-image:url({{ asset('home-discord.webp') }})"></div>
            </div>
            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
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
                <h2 class="text-3xl mb-0 text-white">Want to know what’s coming up?</h2>
                <p class="mb-6 text-left text-white">Sign up and we’ll send you updates on new workshops, special sessions and what’s happening around STEMMechanics.</p>
                <livewire:email-subscribe />
            </div>
            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 120" class="block w-full h-3" preserveAspectRatio="none">
                    <path
                            d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                            fill="#000000"
                    />
                </svg>
            </div>
        </x-container>
    </section>
</x-layout>
