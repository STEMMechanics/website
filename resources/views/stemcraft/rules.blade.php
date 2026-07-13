<x-layout>
    @php
        $tabs = [
            ['title' => 'Overview', 'route' => route('stemcraft.index')],
            ['title' => 'Join', 'route' => route('stemcraft.join')],
            ['title' => 'Rules', 'route' => route('stemcraft.rules')],
            ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ];

        $rules = [
            ['title' => 'Be kind and respectful', 'body' => 'Use friendly language, include others where you can, and remember there is a real person behind every build.', 'icon' => 'fa-solid fa-heart'],
            ['title' => 'Build responsibly', 'body' => 'Create things you would be happy to show at a STEMMechanics workshop. Keep shared areas tidy and safe for others to explore.', 'icon' => 'fa-solid fa-cubes'],
            ['title' => 'Help others learn', 'body' => 'Share ideas, explain how you solved problems, and give other makers room to try things for themselves.', 'icon' => 'fa-solid fa-lightbulb'],
            ['title' => 'Ask before changing shared creations', 'body' => 'Do not edit, move or remove someone else\'s work unless they have said it is okay.', 'icon' => 'fa-solid fa-hand-paper'],
            ['title' => 'Play fairly', 'body' => 'Do not use cheats, bots, x-ray packs, hacked clients, automation or mods that give you an unfair advantage.', 'icon' => 'fa-solid fa-scale-balanced'],
            ['title' => 'Understand survival mode', 'body' => 'Survival is game-on. Mobs, hazards and other players may put you or your items at risk, so plan, protect your gear and play responsibly.', 'icon' => 'fa-solid fa-shield-heart'],
            ['title' => 'Use appropriate language', 'body' => 'Keep chat, signs and builds suitable for young participants, families, schools and libraries.', 'icon' => 'fa-solid fa-comment-dots'],
            ['title' => 'Protect personal information', 'body' => 'Do not share addresses, phone numbers, school details, passwords or private contact information.', 'icon' => 'fa-solid fa-shield-halved'],
            ['title' => 'Follow moderator instructions', 'body' => 'If a moderator or STEMMechanics facilitator gives an instruction, follow it and ask questions politely if you need clarification.', 'icon' => 'fa-solid fa-clipboard-check'],
            ['title' => 'Have fun and be creative', 'body' => 'Experiment, test ideas, learn from mistakes and enjoy building something you are proud of.', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
        ];
    @endphp

    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs" description="Community expectations">STEMCraft Rules</x-mast>

    <x-container class="pt-12">
        <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_30rem] lg:items-center">
            <div>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">Build Kindly and Responsibly</h1>
                <p class="mt-5 max-w-3xl text-lg text-gray-600">STEMCraft is a shared creative space connected to STEMMechanics workshops and programs. These expectations help keep it calm, welcoming and useful for young makers.</p>
                <p class="mt-5 max-w-3xl text-lg text-gray-600">The goal is not competition or rankings. The goal is to experiment, create, learn from others, play fairly and treat shared spaces with care.</p>
            </div>
            <img src="{{ asset('stemcraft-instance-approval.webp') }}" alt="Young makers exploring and building together in the STEMCraft world" class="h-full min-h-72 w-full rounded-lg object-cover shadow-sm">
        </section>
    </x-container>

    <section>
        <div class="mt-14 h-4 bg-[linear-gradient(to_bottom_right,transparent_50%,var(--color-amber-200)_50%)]"></div>
        <div class="bg-amber-200">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-black sm:text-4xl">Community expectations</h2>
                    <p class="mt-4 text-lg text-gray-700">A few simple habits help everyone keep building, learning and sharing ideas respectfully. Rule breaking is taken seriously, and restrictions or bans may be used when needed. A ban might last a few hours, several days, or permanently for serious or repeated behaviour.</p>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2">
                    @foreach($rules as $rule)
                        <article class="rounded-lg border border-white/20 bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex w-11 h-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                    <i class="{{ $rule['icon'] }}" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $rule['title'] }}</h3>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-gray-600">{{ $rule['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="h-4 bg-[linear-gradient(to_bottom_right,var(--color-amber-200)_50%,transparent_50%)]"></div>
    </section>

    <x-container class="py-12">
        <section class="mt-12 overflow-hidden rounded-2xl bg-primary-color px-6 py-12 text-center text-white shadow-sm sm:px-10">
            <h2 class="text-3xl font-semibold tracking-tight">If something goes wrong</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-white/90">Pause, ask for help, and contact STEMMechanics if a participant needs support. We want everyone to keep learning and creating respectfully.</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button href="{{ route('contact') }}" color="outline">Contact support</x-ui.button>
                <x-ui.button href="{{ route('stemcraft.faqs') }}" color="outline">Read the FAQs</x-ui.button>
            </div>
        </section>
    </x-container>
</x-layout>
