@php
    $tabs = [
        ['title' => 'Overview', 'route' => route('stemcraft.index')],
        ['title' => 'Join', 'route' => route('stemcraft.join')],
        ['title' => 'Rules', 'route' => route('stemcraft.rules')],
        ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ['title' => 'Punishments', 'route' => route('stemcraft.punishments')],
    ];
@endphp

<x-layout>
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">STEMCraft</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="">
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Creative, community-minded Minecraft with clearer expectations and a more welcoming pace.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">STEMCraft is the Minecraft community connected to STEMMechanics. It is built around collaborative play, thoughtful moderation, and a space that feels approachable for new players as well as regulars.</p>
                    <p class="mt-4 text-base leading-7 text-gray-600">Rather than chasing chaos, the focus is on building well, playing fairly, and making it easier for players, families, and workshop participants to understand how the space works before they jump in.</p>
                </div>

                <img class="mx-auto rounded-lg mt-6" src="/stemcraft-lobby.webp" alt="">

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Community first</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">The aim is a server culture that feels safe, fair, and easier to understand than a typical open public server.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Creative play</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Expect building, shared projects, exploration, and structured activities tied to workshops or community programs.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Visible moderation</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Rules and public moderation records exist so expectations are visible rather than hidden behind guesswork.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">What to expect</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 list-disc pl-4">
                        <li class="pl-1">Family-friendly standards and clearer boundaries around chat, griefing, and fair play</li>
                        <li class="pl-1">Room for workshop groups, community projects, and regular players to coexist</li>
                        <li class="pl-1">Access and whitelist handling connected to the website where needed</li>
                        <li class="pl-1">A server that aims to feel organised without becoming cold or overbearing</li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Start here</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If you are new to STEMCraft, these are the pages to read first.</p>
                    <div class="mt-5 grid gap-4
            grid-cols-1
            sm:grid-cols-2
            xl:grid-cols-1">
                        <x-ui.button
                                href="{{ route('stemcraft.join') }}"
                                class="h-10 sm:h-32 xl:h-10 w-full flex items-center text-sm sm:text-xl xl:text-sm">
                            How to join
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.rules') }}"
                                color="primary-outline"
                                class="h-10 sm:h-32 xl:h-10 w-full flex items-center text-sm sm:text-xl xl:text-sm">
                            Read the rules
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.faqs') }}"
                                color="primary-outline"
                                class="h-10 sm:h-32 xl:h-10 w-full flex items-center text-sm sm:text-xl xl:text-sm">
                            FAQs
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.punishments') }}"
                                color="primary-outline"
                                class="h-10 sm:h-32 xl:h-10 w-full flex items-center text-sm sm:text-xl xl:text-sm">
                            View punishments log
                        </x-ui.button>

                    </div>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
