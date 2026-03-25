<x-layout>
    <x-mast description="Professional STEM workshops and creative technology programs delivered with a practical, human approach.">About STEMMechanics</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="overflow-hidden rounded-3xl border border-gray-100 bg-gray-100">
                    <img
                        src="{{ asset('about.webp') }}"
                        alt="STEMMechanics workshop space"
                        class="h-72 w-full object-cover sm:h-80"
                    />
                </div>

                <div class="mt-8 max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Independent and based in Cairns</div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Practical STEM learning, delivered with care, clarity, and real-world experience.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">STEMMechanics is an education studio created by James Collins to deliver STEM workshops and creative technology programs for schools, libraries, community organisations, and events. The aim is to make technology learning feel structured, approachable, and genuinely engaging, with programs that are well planned without becoming rigid or impersonal.</p>
                    <p class="mt-4 text-base leading-7 text-gray-600">That approach comes from years of experience delivering digital literacy programs, workshop series, ICT support, eSports events, media projects, and regional STEM initiatives across Queensland. STEMMechanics brings that experience together in a way that is organised enough for partners to rely on and human enough for learners to feel comfortable participating.</p>
                    <p class="mt-4 text-base leading-7 text-gray-600">Whether the format is a one-off workshop, a multi-day program, or a custom community project, the focus stays the same: practical learning, clear outcomes, and real room for curiosity.</p>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Built for participation</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Programs are designed so participants are making, testing, and solving problems, not just watching from the sidelines.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Adapted to context</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Each program can be shaped to suit the setting, age group, goals, and practical constraints of the people involved.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Tested in-house</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Activities, kits, and supporting tools are developed and refined in a dedicated private workshop before delivery.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">What STEMMechanics does</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">Programs are tailored to the setting, but they usually sit across three connected areas.</p>
                    <div class="mt-4 space-y-4 text-sm leading-6 text-gray-600">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">STEM workshops</div>
                            <p class="mt-1">Coding, robotics, electronics, Micro:bit, mechanical motion, cardboard engineering, and other build-based learning experiences.</p>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Creative technology</div>
                            <p class="mt-1">Stop motion, digital storytelling, filmmaking, and media projects that connect technical skills with creative outcomes.</p>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Community programs</div>
                            <p class="mt-1">Short sessions, multi-day intensives, and project blocks delivered with schools, councils, libraries, and local organisations.</p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">How the work is delivered</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-600">The goal is to give organisers confidence in the delivery while making the experience feel welcoming and hands-on for participants.</p>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600">
                            <li>Clear structure without losing warmth or flexibility</li>
                            <li>Hands-on learning with room for curiosity and experimentation</li>
                            <li>Practical activities suited to mixed confidence levels</li>
                            <li>Programs shaped around local needs and real outcomes</li>
                        </ul>
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Based in Far North Queensland</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-600">STEMMechanics operates from Cairns and works across Queensland, with experience supporting both metropolitan and regional communities.</p>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Where possible, programs are planned to make travel efficient by grouping visits, reducing unnecessary trips, and making the most of each regional run.</p>
                        <div class="mt-5 flex flex-col gap-3">
                            <x-ui.button href="{{ route('contact') }}">Talk about a program</x-ui.button>
                            <x-ui.button href="{{ route('workshop.index') }}" color="primary-outline">Browse workshops</x-ui.button>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <section class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="max-w-4xl">
                <div class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-600">The overall approach</div>
                <h2 class="mt-4 text-2xl font-semibold text-gray-900">At the centre of it all is a simple idea: people understand technology better when they can explore it for themselves.</h2>
                <p class="mt-4 text-base leading-7 text-gray-600">That is why STEMMechanics leans so heavily into direct experience. When learners can build, test, troubleshoot, and revise their ideas, technology becomes less abstract and far more meaningful. It also helps confidence grow in a way that feels earned rather than forced.</p>
                <p class="mt-4 text-base leading-7 text-gray-600">For the schools, organisations, and communities that book these programs, that philosophy translates into delivery that is thoughtful, practical, and reliable. For participants, it means workshops that feel active, creative, and welcoming. If that sounds like the right fit, the contact page is the best next step for bookings, collaborations, or support questions.</p>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="flex-shrink-0">
                        <div class="h-28 w-28 overflow-hidden rounded-full border border-gray-200 bg-gray-100">
                            <img src="{{ asset('profile-james.png') }}" alt="James Collins" class="h-full w-full object-cover" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Founder</div>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900">James Collins</h2>
                        <p class="mt-4 text-sm leading-7 text-gray-600">James has experience delivering hands-on STEM programs across libraries, schools, and community settings, working with young people from primary through to teens. His work spans creative technology, mechanical builds, and digital skills, along with supporting digital inclusion initiatives in regional and remote communities, including Indigenous Knowledge Centres. He also develops the systems and platforms that support these programs, from online communities to custom learning environments.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="flex-shrink-0">
                        <div class="h-28 w-28 overflow-hidden rounded-full border border-gray-200 bg-gray-100">
                            <img src="{{ asset('profile-alex.png') }}" alt="Alex Rivera" class="h-full w-full object-cover" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Team member</div>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900">Alex Rivera</h2>
                        <p class="mt-4 text-sm leading-7 text-gray-600">Alex brings experience delivering hands-on STEM workshops across school and community settings, with a focus on practical, engaging learning for young people. Their work includes supporting programs in creative technology, digital media, and build-based projects, helping participants turn ideas into working outcomes. Alex has also worked alongside community coordinators to assist with program delivery and participant support, particularly in regional settings. They have a strong interest in creating inclusive, approachable learning environments and contribute to the ongoing development of workshop content and resources.</p>
                    </div>
                </div>
            </div>
        </section>
    </x-container>
</x-layout>
