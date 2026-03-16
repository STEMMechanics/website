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

            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-1">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm order-2 xl:order-1">
                    <h2 class="text-lg font-semibold text-gray-900">Server info</h2>
                    @if(($serverInfo['available'] ?? false) === true)
                        <p class="mt-3 text-sm leading-6 text-gray-600">
                            <span class="font-medium text-gray-900">{{ $serverInfo['heading'] ?? 'STEMCraft' }}</span>
                            <span class="text-gray-500">- {{ $serverInfo['summary'] ?? 'Public snapshot from the game server.' }}</span>
                        </p>

                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            @foreach($serverInfo['cards'] ?? [] as $card)
                                <div class="rounded-2xl bg-gray-50 px-3 py-3">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $card['label'] ?? '-' }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $card['value'] ?? '-' }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if(!empty($serverInfo['worlds'] ?? []))
                            <div class="mt-4">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-gray-500">Worlds</h3>
                                <ul class="mt-2 space-y-2 text-sm text-gray-700">
                                    @foreach($serverInfo['worlds'] as $world)
                                        @if(($world['type'] ?? 'single') === 'group')
                                            <li class="rounded-2xl bg-gray-50 px-3 py-2">
                                                <details>
                                                    <summary class="cursor-pointer select-none font-medium text-gray-900">
                                                        {{ $world['summary'] ?? ($world['name'] ?? '-') }}
                                                    </summary>
                                                    <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-700">
                                                        @foreach($world['children'] ?? [] as $child)
                                                            <li>{{ $child['name'] ?? '-' }}</li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            </li>
                                        @else
                                            <li class="flex items-start gap-2 rounded-2xl bg-gray-50 px-3 py-2">
                                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                                                <span>{{ $world['name'] ?? '-' }}</span>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($serverInfo['refreshed_at_human'] ?? null))
                            <p class="mt-4 text-xs text-gray-500">
                                Last updated {{ $serverInfo['refreshed_at_human'] }}
                            </p>
                        @endif
                    @else
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $serverInfo['summary'] ?? 'Server status is not available right now.' }}</p>
                    @endif
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm order-1 sm:col-span-2 xl:order-2 xl:col-span-1">
                    <h2 class="text-lg font-semibold text-gray-900">What to expect</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 list-disc pl-4">
                        <li class="pl-1">Family-friendly standards and clearer boundaries around chat, griefing, and fair play</li>
                        <li class="pl-1">Room for workshop groups, community projects, and regular players to coexist</li>
                        <li class="pl-1">Access and whitelist handling connected to the website where needed</li>
                        <li class="pl-1">A server that aims to feel organised without becoming cold or overbearing</li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm order-3 xl:order-3">
                    <h2 class="text-lg font-semibold text-gray-900">Start here</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If you are new to STEMCraft, these are the pages to read first.</p>
                    <div class="mt-5 flex flex-col gap-4">
                        <x-ui.button
                                href="{{ route('stemcraft.join') }}"
                                class="h-10 w-full flex items-center text-sm">
                            How to join
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.rules') }}"
                                color="primary-outline"
                                class="h-10 w-full flex items-center text-sm">
                            Read the rules
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.faqs') }}"
                                color="primary-outline"
                                class="h-10 w-full flex items-center text-sm">
                            FAQs
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.leaderboards') }}"
                                color="primary-outline"
                                class="h-10 w-full flex items-center text-sm">
                            Player Leaderboard
                        </x-ui.button>

                        <x-ui.button
                                href="{{ route('stemcraft.punishments') }}"
                                color="primary-outline"
                                class="h-10 w-full flex items-center text-sm">
                            View punishments log
                        </x-ui.button>

                    </div>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
