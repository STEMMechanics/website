<x-layout>
    @php
        $tabs = [
            ['title' => 'Overview', 'route' => route('stemcraft.index')],
            ['title' => 'Join', 'route' => route('stemcraft.join')],
            ['title' => 'Rules', 'route' => route('stemcraft.rules')],
            ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ];

        $activities = [
            'Continue creative building after a STEMMechanics workshop',
            'Experiment with ideas, mechanisms and digital design',
            'Practise respectful collaboration in shared spaces',
            'Try small challenges connected to making, coding and problem solving',
        ];

        $monthlyChallenge = [
            'title' => \App\Models\SiteOption::value('stemcraft.monthly-challenge.title', 'Build your dream treehouse'),
            'description' => \App\Models\SiteOption::value('stemcraft.monthly-challenge.description', 'Create a treehouse with at least two levels and use redstone to add one moving or interactive feature.'),
            'prompt' => \App\Models\SiteOption::value('stemcraft.monthly-challenge.prompt', 'Think about access, storage, lighting and what would make your treehouse unique.'),
            'image' => \App\Models\SiteOption::mediaUrl('stemcraft.monthly-challenge.image', '/stemcraft-technical-build.webp', 'lg'),
            'image_alt' => \App\Models\SiteOption::value('stemcraft.monthly-challenge.image-alt', 'A STEMCraft build showing a creative engineering challenge'),
        ];

        $communityBuilds = collect([1, 2, 3])->map(fn (int $index): array => [
            'title' => \App\Models\SiteOption::value("stemcraft.community-builds.{$index}.title", ['Castle Build', 'Creative City', 'Working Machine'][$index - 1]),
            'description' => \App\Models\SiteOption::value("stemcraft.community-builds.{$index}.description", [
                'A detailed medieval castle designed with towers, bridges and spaces to explore.',
                'A growing shared city filled with streets, homes, public spaces and imaginative details.',
                'A redstone-powered creation that combines engineering, experimentation and problem-solving.',
            ][$index - 1]),
            'image' => \App\Models\SiteOption::mediaUrl("stemcraft.community-builds.{$index}.image", [
                '/stemcraft-calm-build.webp',
                '/community-minecraft.webp',
                '/stemcraft-workshop-map.webp',
            ][$index - 1], 'md'),
            'image_alt' => \App\Models\SiteOption::value("stemcraft.community-builds.{$index}.image-alt", [
                'A detailed castle-style build in STEMCraft',
                'A creative city build made by STEMCraft participants',
                'A working STEMCraft mechanism and build area',
            ][$index - 1]),
        ])->all();

        $faqs = \App\Support\StemcraftFaqs::indexItems();

        $renderMarkdown = static function (?string $value): string {
            $normalized = \App\Support\EmailMessageFormatter::normalizeForMarkdown((string) ($value ?? ''));
            if ($normalized === '') {
                return '';
            }

            return (string) \Illuminate\Mail\Markdown::parse($normalized);
        };
    @endphp

    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs" description="The online world of STEMMechanics">STEMCraft</x-mast>

    <x-container class="pt-12">
        <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_32rem] lg:items-center">
            <div class="flex h-full flex-col">
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">Continue Building Beyond the Workshop</h1>
                <p class="mt-5 max-w-3xl text-lg text-gray-600 flex-1">STEMCraft is the online world of STEMMechanics, giving young makers a place to continue building, experimenting and creating long after a workshop ends.</p>
                <p class="mt-5 max-w-3xl text-lg text-gray-600 flex-1">Built around Minecraft, STEMCraft lets young makers explore engineering ideas, solve design challenges and create imaginative projects together in a safe, respectful environment.</p>
                <p class="mt-5 max-w-3xl text-lg text-gray-600 flex-1">Rather than focusing on competition or rankings, STEMCraft encourages creativity, curiosity and practical problem-solving through play.</p>
                <div class="mt-8 flex justify-center">
                    <x-ui.button href="{{ route('stemcraft.join') }}">Join STEMCraft</x-ui.button>
                </div>
            </div>
            <img src="{{ asset('stemcraft-hero.webp') }}" alt="A creative Minecraft-style STEMCraft build" class="h-full min-h-72 w-full rounded-lg object-cover shadow-sm">
        </section>
    </x-container>

    <section id="what-you-can-do">
        <div class="mt-14 h-4 bg-[linear-gradient(to_bottom_right,transparent_50%,var(--color-amber-200)_50%)]"></div>
        <div class="bg-amber-200">
            <div class="flex gap-16 mx-auto max-w-6xl py-18">
                <div>
                    <div class="max-w-2xl">
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-black sm:text-4xl">Build, explore and keep learning</h2>
                        <p class="mt-4 text-lg text-gray-600">STEMCraft gives young makers room to try ideas, solve problems and create at their own pace.</p>
                    </div>

                    <div class="mt-8 flex flex-col gap-5">
                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                                    <i class="fa-solid fa-hammer" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">Build Amazing Worlds</h3>
                            </div>
                            <p class="mt-2 text-base text-gray-600">Create structures, machines and imaginative worlds using your own ideas.</p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                                    <i class="fa-solid fa-compass" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">Explore Together</h3>
                            </div>
                            <p class="mt-2 text-base text-gray-600">Discover community builds, new locations and different ways to solve challenges.</p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">Create New Ideas</h3>
                            </div>

                            <p class="mt-2 text-base text-gray-600">Personalise your builds and turn simple ideas into something uniquely yours.</p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900">Learn Through Play</h3>
                            </div>

                            <p class="mt-2 text-base text-gray-600">Experiment with engineering, redstone and problem-solving through practical building.</p>
                        </article>
                    </div>
                </div>

                <div>
                    <x-stemcraft.server-status-card class="max-w-96" />
                </div>
            </div>
        </div>
    </section>

    <x-container class="bg-amber-200">
        <section id="monthly-challenge" class="overflow-hidden rounded-2xl bg-linear-to-br from-violet-600 to-primary-color text-white shadow-lg shadow-black/30 border-violet-300 border">
            <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="p-6 sm:p-8 lg:p-12">
                    <div>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold uppercase tracking-wide text-white ring-1 ring-white/20">
                            This month’s challenge
                        </span>
                    </div>

                    <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ $monthlyChallenge['title'] }}
                    </h2>

                    <div class="mt-4 max-w-2xl text-lg text-white/90 [&_a]:font-semibold [&_a]:text-white [&_a]:underline [&_li]:ml-5 [&_li]:list-disc [&_p+p]:mt-3 [&_ul]:mt-3">
                        {!! $renderMarkdown($monthlyChallenge['description']) !!}
                    </div>

                    <div class="mt-6 rounded-lg bg-white/10 p-5 ring-1 ring-white/15">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-lightbulb mt-1 text-amber-300" aria-hidden="true"></i>

                            <div class="text-sm leading-6 text-white/90 [&_a]:font-semibold [&_a]:text-white [&_a]:underline [&_li]:ml-4 [&_li]:list-disc [&_p+p]:mt-2 [&_ul]:mt-2">
                                {!! $renderMarkdown($monthlyChallenge['prompt']) !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="min-h-64 bg-black/10">
                    <img
                            src="{{ $monthlyChallenge['image'] }}"
                            alt="{{ $monthlyChallenge['image_alt'] }}"
                            class="h-full min-h-64 w-full object-cover"
                    >
                </div>
            </div>
        </section>
    </x-container>

    <section id="community-builds" class="-mt-48">
        <div class="h-8 bg-[linear-gradient(to_bottom_right,var(--color-amber-200)_50%,var(--color-orange-500)_50%)]"></div>
        <div class="bg-orange-500 p-4 pb-8">
            <div class="mx-auto max-w-6xl pt-48 pb-18">
                <div class="max-w-2xl">
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">See what young makers are creating</h2>
                    <p class="mt-4 text-lg text-gray-100">Explore imaginative builds, shared projects and creative ideas from across the STEMCraft world.</p>
                </div>

                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    @foreach($communityBuilds as $communityBuild)
                        <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <img
                                    src="{{ $communityBuild['image'] }}"
                                    alt="{{ $communityBuild['image_alt'] }}"
                                    class="aspect-4/3 w-full object-cover"
                            >

                            <div class="p-5">
                                <h3 class="text-xl font-semibold text-gray-900">{{ $communityBuild['title'] }}</h3>
                                <p class="mt-2 line-clamp-3 text-base text-gray-600">{{ $communityBuild['description'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="h-4 bg-[linear-gradient(to_top_left,transparent_50%,var(--color-orange-500)_50%)]"></div>
    </section>

    <x-container class="py-12">
        <div class="space-y-16">
            <section id="how-it-works">
                <div class="mx-auto max-w-6xl">
                    <div class="max-w-2xl">
                        <div>
                <span class="rounded-full bg-violet-600 px-2 py-1 text-sm font-semibold uppercase text-white">
                    How it works
                </span>
                        </div>

                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
                            From workshop ideas to an online world
                        </h2>

                        <p class="mt-4 text-lg text-gray-600">
                            Joining STEMCraft is simple. Start with an idea, connect to the world and keep creating.
                        </p>
                    </div>

                    <div class="relative mt-10">
                        <ol class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5 list-none">
                            <li class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="absolute left-0 top-0 z-0 h-24 w-18 rounded-br-full bg-violet-50"></div>
                                <div class="relative z-10 flex items-center">
                                    <div class="flex items-center text-4xl w-16 shrink-0 font-semibold text-violet-700">1</div>
                                    <h3 class="text-lg font-semibold text-gray-900 leading-6">Attend a workshop</h3>
                                </div>
                                <p class="relative z-10 mt-8 text-sm text-gray-600">
                                    Discover new ideas, activities and creative challenges through STEMMechanics.
                                </p>
                            </li>

                            <li class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="absolute left-0 top-0 z-0 h-24 w-18 rounded-br-full bg-violet-50"></div>
                                <div class="relative z-10 flex items-center">
                                    <div class="flex items-center text-4xl w-16 shrink-0 font-semibold text-violet-700">2</div>
                                    <h3 class="text-lg font-semibold text-gray-900 leading-6">Join STEMCraft</h3>
                                </div>
                                <p class="relative z-10 mt-8 text-sm text-gray-600">Follow the joining guide and connect using the current server details.</p>
                            </li>

                            <li class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="absolute left-0 top-0 z-0 h-24 w-18 rounded-br-full bg-violet-50"></div>
                                <div class="relative z-10 flex items-center">
                                    <div class="flex items-center text-4xl w-16 shrink-0 font-semibold text-violet-700">3</div>
                                    <h3 class="text-lg font-semibold text-gray-900 leading-6">Start Building</h3>
                                </div>
                                <p class="relative z-10 mt-8 text-sm text-gray-600">Explore the world, develop your own projects and experiment with new ideas.</p>
                            </li>

                            <li class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="absolute left-0 top-0 z-0 h-24 w-18 rounded-br-full bg-violet-50"></div>
                                <div class="relative z-10 flex items-center">
                                    <div class="flex items-center text-4xl w-16 shrink-0 font-semibold text-violet-700">4</div>
                                    <h3 class="text-lg font-semibold text-gray-900 leading-6">Try new challenges</h3>
                                </div>
                                <p class="relative z-10 mt-8 text-sm text-gray-600">Take part in optional building, engineering and problem-solving prompts.</p>
                            </li>

                            <li class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="absolute left-0 top-0 z-0 h-24 w-18 rounded-br-full bg-violet-50"></div>
                                <div class="relative z-10 flex items-center">
                                    <div class="flex items-center text-4xl w-16 shrink-0 font-semibold text-violet-700">5</div>
                                    <h3 class="text-lg font-semibold text-gray-900 leading-6">Share ideas</h3>
                                </div>
                                <p class="relative z-10 mt-8 text-sm text-gray-600">Learn from other makers and contribute to shared projects respectfully.</p>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section id="who-its-for" class="overflow-hidden rounded-2xl border border-gray-200 bg-emerald-50 shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
                    <div class="p-6 sm:p-8 lg:p-12">
                        <div>
                <span class="rounded-full bg-emerald-600 px-2 py-1 text-sm font-semibold uppercase text-white">
                    Who it’s for
                </span>
                        </div>

                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
                            A creative space for makers, families and communities
                        </h2>

                        <p class="mt-4 text-lg text-gray-600">
                            STEMCraft is designed to support different kinds of learners and organisations connected with STEMMechanics.
                        </p>

                        <div class="mt-8 grid gap-5 sm:grid-cols-2">
                            <article>
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700">
                                        <i class="fa-solid fa-child-reaching" aria-hidden="true"></i>
                                    </div>

                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Children
                                    </h3>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600">
                                    Young makers who enjoy building, experimenting, Minecraft and open-ended creative challenges.
                                </p>
                            </article>

                            <article>
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700">
                                        <i class="fa-solid fa-people-roof" aria-hidden="true"></i>
                                    </div>

                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Families
                                    </h3>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600">
                                    Families looking for a structured, creative space that extends learning beyond a workshop.
                                </p>
                            </article>

                            <article>
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700">
                                        <i class="fa-solid fa-school" aria-hidden="true"></i>
                                    </div>

                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Schools
                                    </h3>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600">
                                    Schools wanting to connect classroom STEM activities with an ongoing creative digital environment.
                                </p>
                            </article>

                            <article>
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700">
                                        <i class="fa-solid fa-book-open-reader" aria-hidden="true"></i>
                                    </div>

                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Libraries
                                    </h3>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600">
                                    Libraries delivering creative technology, Minecraft or digital inclusion programs with STEMMechanics.
                                </p>
                            </article>
                        </div>
                    </div>

                    <img
                            src="{{ asset('stemcraft-workshop.webp') }}"
                            alt="Young makers exploring and building together in the STEMCraft world"
                            class="h-full min-h-80 w-full object-cover"
                    >
                </div>
            </section>

            <section id="community-expectations">
                <div class="mx-auto max-w-6xl">
                    <div class="max-w-2xl">
                        <div>
                <span class="rounded-full bg-amber-600 px-2 py-1 text-sm font-semibold uppercase text-white">
                    Community expectations
                </span>
                        </div>

                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
                            Build kindly and responsibly
                        </h2>

                        <p class="mt-4 text-lg text-gray-600">
                            STEMCraft is a shared creative space. A few simple expectations help keep it welcoming for everyone.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-700">
                                    <i class="fa-solid fa-heart" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Be kind</h3>
                            </div>

                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                Treat other participants with patience, respect and encouragement.
                            </p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-700">
                                    <i class="fa-solid fa-cubes" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Respect builds</h3>
                            </div>

                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                Ask before changing or using something another participant has created.
                            </p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-700">
                                    <i class="fa-solid fa-people-roof" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Keep it family friendly</h3>
                            </div>

                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                Use suitable language and create content that everyone can comfortably enjoy.
                            </p>
                        </article>

                        <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-700">
                                    <i class="fa-solid fa-face-smile-beam" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Have fun</h3>
                            </div>

                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                Experiment, share ideas and enjoy creating something new.
                            </p>
                        </article>
                    </div>

                    <div class="mt-6">
                        <a
                                href="{{ route('stemcraft.rules') }}"
                                class="inline-flex items-center gap-2 font-semibold text-primary-color transition hover:underline"
                        >
                            View full community guidelines
                            <i class="fa-solid fa-arrow-right text-sm" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </section>

            @if(count($faqs) > 0)
                <section id="stemcraft-faq" class="pt-12">
                    <div class="mx-auto max-w-4xl">
                        <div class="text-center">
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">
                                Questions before you join?
                            </h2>

                            <p class="mt-4 text-lg text-gray-600">
                                Here are the answers to the most common questions about STEMCraft.
                            </p>
                        </div>

                        <div class="mt-10 space-y-3">
                            @foreach($faqs as $faq)
                                <details class="group rounded-lg border border-gray-200 bg-white shadow-sm">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-gray-900">
                                        <span>{{ $faq['question'] }}</span>

                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-500 transition group-open:rotate-45">
                            <i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
                        </span>
                                    </summary>

                                    <div class="border-t border-gray-100 px-5 py-4">
                                        <p class="text-base leading-7 text-gray-600">
                                            {{ $faq['answer'] }}
                                        </p>
                                    </div>
                                </details>
                            @endforeach
                        </div>

                        <p class="mt-6 text-center text-sm text-gray-600">
                            Need more detail?
                            <a
                                    href="{{ route('stemcraft.faqs') }}"
                                    class="font-semibold text-primary-color hover:underline"
                            >
                                View the complete FAQ
                            </a>
                        </p>
                    </div>
                </section>
            @endif
        </div>
    </x-container>
</x-layout>
