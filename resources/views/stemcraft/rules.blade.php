@php
    $tabs = [
        ['title' => 'Overview', 'route' => route('stemcraft.index')],
        ['title' => 'Join', 'route' => route('stemcraft.join')],
        ['title' => 'Rules', 'route' => route('stemcraft.rules')],
        ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ['title' => 'Leaderboard', 'route' => route('stemcraft.leaderboards')],
        ['title' => 'Punishments', 'route' => route('stemcraft.punishments')],
    ];
@endphp

<x-layout>
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">Rules</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Community standards</div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">The rules are here to protect the community, not to catch people out.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">STEMCraft works best when players know what is expected of them. These rules are meant to keep the space respectful, readable, and fair for players, families, workshop groups, and regular community members alike.</p>
                    <p class="mt-4 text-base leading-7 text-gray-600">If you are unsure about something, the safest approach is simple: respect other people’s time, builds, and ability to enjoy the server without being hassled.</p>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Respect people</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No bullying, harassment, hate speech, threats, or behaviour aimed at making others feel unwelcome.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Respect the world</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Do not grief, steal, vandalise, or interfere with builds, areas, or shared resources that are not yours.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Respect fair play</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Do not exploit bugs, cheat, evade moderation, or use the server in ways that undermine the wider community.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">How moderation is handled</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">Moderation actions can include warnings, mutes, kicks, bans, or account restrictions depending on the issue and whether it is ongoing or repeated.</p>
                    <div class="mt-4 flex justify-end">
                        <x-ui.button href="{{ route('stemcraft.punishments') }}" color="primary-outline" class="w-full">View punishments log</x-ui.button>
                    </div>
                </section>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Core rules</h2>
                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">1. Be respectful in chat and in behaviour</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No abusive language, targeted harassment, repeated hostility, or behaviour that creates pressure or discomfort for others.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">2. Do not grief, steal, or sabotage</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Leave other players’ creative builds, resources, and community areas alone unless you have clear permission to help or modify them.</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Of course, in survival based worlds, it is every block for themselves!</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">3. Do not cheat or exploit the server</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">That includes hacked clients, deliberate duplication, loopholes, moderation evasion, or abusing technical faults for unfair advantage.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">4. Help keep shared spaces usable</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Avoid spammy builds, repeated clutter, disruptive redstone or lag-heavy setups, and behaviour that makes the server worse for everyone else.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">What happens if rules are broken</h2>
                <p class="mt-3 text-sm leading-6 text-gray-600">Moderation is not always one-size-fits-all. The response depends on the seriousness of the issue, any pattern of repeated behaviour, and whether the player is engaging constructively after the problem is raised.</p>

                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Lower-level issues</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">These may lead to reminders, warnings, or short restrictions where that is enough to correct the behaviour.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Serious or repeated issues</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">These can lead to mutes, kicks, bans, or more permanent account restrictions if needed to protect the community.</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <h3 class="text-sm font-semibold text-amber-900">Public visibility</h3>
                        <p class="mt-2 text-sm leading-6 text-amber-900">Some moderation actions are visible on the punishments page so the community can understand how active restrictions are being handled.</p>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
