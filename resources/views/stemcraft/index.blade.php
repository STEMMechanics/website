@php
    $tabs = [
        ['title' => 'Overview', 'route' => route('stemcraft.index')],
        ['title' => 'Join', 'route' => route('stemcraft.join')],
        ['title' => 'Rules', 'route' => route('stemcraft.rules')],
        ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ['title' => 'Leaderboard', 'route' => route('stemcraft.leaderboards')],
        ['title' => 'Punishments', 'route' => route('stemcraft.punishments')],
    ];

    $openingCards = [
        [
            'title' => 'Public address',
            'image' => '/stemcraft-public-access.webp',
            'alt' => 'A welcoming STEMCraft scene showing that the server address is public.',
            'text' => 'The server address is public, but that does not mean the server is open to everyone.',
        ],
        [
            'title' => 'Restricted access',
            'image' => '/stemcraft-restricted-access.webp',
            'alt' => 'A STEMCraft image that represents restricted access for approved players only.',
            'text' => 'Only approved players can enter. Access starts with a STEMMechanics workshop and a parent account.',
        ],
        [
            'title' => 'Instant approval',
            'image' => '/stemcraft-instance-approval.webp',
            'alt' => 'A STEMCraft approval scene showing that access is granted instantly after setup.',
            'text' => 'Once the Minecraft username is added, access is granted straight away.',
        ],
    ];
@endphp

<x-layout>
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">STEMCraft</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="space-y-8">
            <section class="overflow-hidden rounded-4xl border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)]">
                    <div class="p-6 sm:p-8 lg:p-12">
                        <h2 class="mt-5 max-w-2xl text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl">STEMCraft is a Minecraft server for STEMMechanics kids, families, schools, and OSHC groups.</h2>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">The server address is public, but the world is not open to everyone. Access is restricted, parent-managed, and only available after a STEMMechanics workshop.</p>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">If someone tries to join before approval, they are redirected to the website instead of being let in.</p>

                        <div class="mt-6 inline-flex flex-col gap-3">
                            <x-ui.button href="{{ route('stemcraft.join') }}">How to join</x-ui.button>
                        </div>
                    </div>

                    <div>
                        <img
                            src="{{ asset('stemcraft-hero.webp') }}"
                            alt="STEMCraft players building together in a welcoming world"
                            class="h-full w-full object-cover object-center"
                            loading="eager"
                        >
                    </div>
                </div>
            </section>

            <section>
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach($openingCards as $card)
                        <article class="flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="aspect-4/3 overflow-hidden bg-gray-100">
                                <img
                                    src="{{ $card['image'] }}"
                                    alt="{{ $card['alt'] }}"
                                    class="h-full w-full object-cover object-center transition duration-500 hover:scale-[1.03]"
                                    loading="lazy"
                                >
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $card['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $card['text'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="rounded-4xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900">Who can access STEMCraft</h2>
                    <p class="mt-3 text-base leading-7 text-gray-600">Access is designed for workshop participants, families, schools, and OSHC providers. The process is simple so parents do not have to guess how it works.</p>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5 flex flex-col justify-between gap-6">
                        <div>
                            <div class="inline-flex rounded-full bg-sky-500 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Players</div>
                            <h3 class="mt-2 text-base font-semibold text-gray-900">Start with a STEMMechanics workshop</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">Players need to attend a workshop before access can be set up.</p>
                        </div>
                        <x-ui.button href="{{ route('workshop.index') }}" class="w-full">Find a workshop</x-ui.button>
                    </div>
                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5 flex flex-col justify-between gap-6">
                        <div>
                            <div class="inline-flex rounded-full bg-green-600 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Families</div>
                            <h3 class="mt-2 text-base font-semibold text-gray-900">Parent-managed from your account page</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">Parents add the Minecraft usernames they want linked and can change them later if needed.</p>
                        </div>
                        <x-ui.button href="{{ route('account.stemcraft.index') }}" color="success" class="w-full">Manage access</x-ui.button>
                    </div>
                    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5 flex flex-col justify-between gap-6">
                        <div>
                            <div class="inline-flex rounded-full bg-orange-600 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Schools and OSHC</div>
                            <h3 class="mt-2 text-base font-semibold text-gray-900">Group access is available</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">Schools and out-of-school-hours care providers can arrange group access with STEMMechanics.</p>
                        </div>
                        <x-ui.button href="{{ route('contact') }}" color="orange">Contact STEMMechanics</x-ui.button>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-4xl border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)]">
                    <div>
                        <img
                            src="{{ asset('stemcraft-calm-build.webp') }}"
                            alt="A calm STEMCraft build that reflects the safe and structured play environment"
                            class="h-full w-full object-cover object-center"
                            loading="lazy"
                        >
                    </div>

                    <div class="p-6 sm:p-8 lg:p-12">
                        <h2 class="text-2xl font-semibold text-gray-900">Safety and environment</h2>
                        <p class="mt-3 text-base leading-7 text-gray-600">STEMCraft is designed to feel calm, supervised, and predictable. It is a place for structured play, not a free-for-all public server.</p>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Private access</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Only approved players can enter.</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Logged activity</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Chat and actions are logged so issues can be reviewed if needed.</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">No personal information sharing</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Players should not share names, contact details, or other personal information.</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">Structured play</h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Expect clear rules, supervised sessions, and a steady environment.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-4xl border border-gray-200 bg-yellow-50 shadow-sm overflow-hidden">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
                    <div class="flex flex-col justify-between h-full p-5 sm:p-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Group access is available for classes, holiday programs, and OSHC sessions.</h2>
                            <p class="mt-3 text-sm leading-6 text-gray-600">STEMMechanics can help you set up group access in a way that is clear for staff, predictable for parents, and suitable for the group you are running.</p>
                            <p class="mt-3 text-sm leading-6 text-gray-600">We can even host a workshop on-site with a dedicated private server that is offline!</p>
                        </div>
                        <div class="text-center">
                            <x-ui.button href="{{ route('contact') }}" color="yellow">Contact STEMMechanics</x-ui.button>
                        </div>
                    </div>

                    <div>
                        <img
                            src="{{ asset('stemcraft-workshop.webp') }}"
                            alt="A STEMCraft workshop scene suited to schools and OSHC providers"
                            class="h-full w-full object-cover object-center"
                            loading="lazy"
                        >
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-4xl border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)]">

                    <div class="overflow-hidden bg-gray-100 h-full">
                        <img
                            src="{{ asset('stemcraft-technical-build.webp') }}"
                            alt="A technical STEMCraft build showing the lower-priority setup details"
                            class="h-full w-full object-cover object-center"
                            loading="lazy"
                        >
                    </div>

                    <div class="p-8">
                        <h2 class="text-2xl font-semibold text-gray-900">Technical details</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-600">These details needed to join the server. You still need to be granted access from your STEMMechanics account to play</p>

                        <div class="mt-6 flex flex-col gap-4">
                            <div class="rounded-2xl bg-gray-100 p-4 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Server address</h3>
                                <p class="mt-2 text-sm font-semibold text-gray-900">play.stemcraft.com.au</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Platform</h3>
                                <p class="mt-2 text-sm font-semibold text-gray-900">Java and Bedrock</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bedrock Port</h3>
                                <p class="mt-2 text-sm font-semibold text-gray-900">19132</p>
                            </div>
                            <div class="rounded-2xl bg-gray-100 p-4 shadow-sm">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Min Version</h3>
                                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $version = collect($serverInfo['cards'])->firstWhere('label', 'Version')['value'] ?? 'Offline' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
