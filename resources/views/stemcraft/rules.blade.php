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
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Simple expectations</div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">These rules help STEMCraft stay safe, calm, and easy to use for families and groups.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">STEMCraft is built for workshop participants, parents, schools, and OSHC providers. The rules are here so everyone knows what is expected before they start playing.</p>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Be kind</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No bullying, harassment, threats, or behaviour that makes other people feel unwelcome.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Respect builds</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Do not grief, steal, vandalise, or change someone else’s work without permission.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Play fairly</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Do not cheat, exploit bugs, or try to bypass moderation or access controls.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">What moderation may do</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If there is a problem, the response depends on what happened and whether it is repeated. The goal is to correct behaviour and protect the community.</p>
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
                        <h3 class="text-sm font-semibold text-gray-900">1. Speak respectfully</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Use chat and in-game actions in a way that treats other people with respect.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">2. Leave other people’s work alone</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Only change another player’s build, items, or area if they have clearly said it is okay.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">3. Keep the server fair</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No cheats, hacked clients, duplication, loopholes, or attempts to avoid moderation.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">4. Keep shared areas usable</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Avoid spam, clutter, or anything that makes the server harder for others to enjoy.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Safety and supervision</h2>
                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Private access</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Only approved players can enter the server.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Logged activity</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Chat and actions are recorded so staff can review issues if needed.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">No personal information sharing</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Players should not share names, contact details, or other personal information in chat.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Structured play</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">The environment is designed around supervised sessions, clear expectations, and predictable access.</p>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
