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
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">Joining</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="space-y-8">
            <section class="overflow-hidden rounded-4xl border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,1.03fr)_minmax(0,0.97fr)]">
                    <div class="p-6 sm:p-8 lg:p-10 flex flex-col justify-between">
                        <div>
                            <h2 class="mt-4 text-3xl font-semibold text-gray-900">Access must be set up first, then joining is straightforward.</h2>
                            <p class="mt-4 text-base leading-7 text-gray-600">The server address is public, but approved access is what lets a player in. If a player tries to join before approval, Minecraft sends them to the website instead.</p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <x-ui.button href="{{ route('account.stemcraft.index') }}" color="success">Manage access</x-ui.button>
                            <x-ui.button href="{{ route('stemcraft.faqs') }}" color="primary-outline">Read the FAQs</x-ui.button>
                        </div>
                    </div>

                    <div class="aspect-4/3 overflow-hidden">
                        <img
                            src="{{ asset('stemcraft-workshop-map.webp') }}"
                            alt="A workshop map that shows the STEMCraft joining process"
                            class="h-full w-full object-cover object-center"
                            loading="eager"
                        >
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.12fr)_minmax(0,0.88fr)]">
                <section class="rounded-4xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-2xl font-semibold text-gray-900">How to join</h2>
                    <p class="mt-3 text-base leading-7 text-gray-600">Once access is approved, the join process is short and predictable.</p>

                    <div class="mt-6 space-y-4">
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">1</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Create a website account</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">A parent needs to create a website account first using the workshop email.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">2</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Check that access is already approved</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Under the account menu, you should see the <a href="{{ route('account.stemcraft.index') }}" class="cursor-pointer text-sky-500 hover:text-sky-700">My STEMCraft</a> option.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">3</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Add your Minecraft Username</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Add your child's Minecraft username to your account.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">4</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Open Minecaft and Add the server address</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Open Minecraft and enter <span class="font-semibold text-gray-900">play.stemcraft.com.au</span>.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">5</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Use the Bedrock port if needed</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Bedrock players should use port <span class="font-semibold text-gray-900">19132</span>.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 rounded-3xl bg-gray-50 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-color text-sm font-semibold text-white">6</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Join the server</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Once access is approved, the player can connect normally.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-4xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900">Technical details</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">These details needed to join the server. You still need to be granted access from your STEMMechanics account to play.</p>

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
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
