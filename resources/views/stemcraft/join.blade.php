<x-layout>
    @php
        $tabs = [
            ['title' => 'Overview', 'route' => route('stemcraft.index')],
            ['title' => 'Join', 'route' => route('stemcraft.join')],
            ['title' => 'Rules', 'route' => route('stemcraft.rules')],
            ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ];
    @endphp

    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs" description="Connection details and support">Join STEMCraft</x-mast>

    <x-container class="pt-12">
        <section class="flex gap-16">
            <div class="flex flex-col flex-1">
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">Get Ready to Build Online</h1>
                <p class="mt-5 max-w-3xl text-lg text-gray-600">STEMCraft is the online world of STEMMechanics, giving young makers a place to continue experimenting, creating and learning between workshops.</p>
                <p class="mt-5 max-w-3xl text-lg text-gray-600">Joining is intentionally simple. Check your Minecraft setup, add the connection details, begin with a small creative build, or jump into a mini-game.</p>
                <p class="mt-5 max-w-3xl text-lg text-gray-600">Follow these steps when you are ready to continue building in STEMCraft.</p>
                <div class="mt-8 grid gap-5">
                    <article class="relative rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="absolute left-0 top-0 z-0 h-22 w-24 rounded-br-full bg-sky-100"></div>
                        <div class="relative z-10 flex items-center">
                            <div class="flex w-8 items-center justify-center text-primary-color">
                                <i class="text-2xl fa-solid fa-gamepad" aria-hidden="true"></i>
                            </div>
                            <h2 class="ml-12 text-xl font-semibold text-gray-900">Check your Minecraft setup</h2>
                        </div>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">Use a Minecraft account and a compatible version of Minecraft. The current supported version is shown in the server status panel when available.</p>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">Both Java and Bedrock versions of Minecraft are supported.</p>
                    </article>

                    <article class="relative rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="absolute left-0 top-0 z-0 h-22 w-24 rounded-br-full bg-sky-100"></div>
                        <div class="relative z-10 flex items-center">
                            <div class="flex w-8 items-center justify-center text-primary-color">
                                <i class="text-2xl fa-solid fa-plug" aria-hidden="true"></i>
                            </div>
                            <h2 class="ml-12 text-xl font-semibold text-gray-900">Add the connection details</h2>
                        </div>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">Open multiplayer, add the STEMCraft server address, and keep it saved so it is easy to return between workshops.</p>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">Bedrock players should use port <span class="font-semibold">19132</span>.</p>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">All players should <span class="font-semibold">enable resource packs</span> for the best experience on the server.</p>
                    </article>

                    <article class="relative rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="absolute left-0 top-0 z-0 h-22 w-24 rounded-br-full bg-sky-100"></div>
                        <div class="relative z-10 flex items-center">
                            <div class="flex w-8 items-center justify-center text-primary-color">
                                <i class="text-2xl fa-solid fa-cubes" aria-hidden="true"></i>
                            </div>
                            <h2 class="ml-12 text-xl font-semibold text-gray-900">Start with a small build</h2>
                        </div>
                        <p class="ml-20 mt-3 text-base leading-7 text-gray-600">Jump into the Creative world and choose a simple idea, explore respectfully. If you are unsure where to begin type <code class="text-sm text-white bg-gray-600 rounded font-semibold px-1.5 py-0.75">/help</code> into the Minecraft chat.</p>
                    </article>
                </div>
            </div>
            <div>
                <x-stemcraft.server-status-card class="w-96" />
            </div>
        </section>
    </x-container>

    <x-container class="py-12">
        <section class="mt-12 overflow-hidden rounded-2xl bg-primary-color px-6 py-12 text-center text-white shadow-sm sm:px-10">
            <h2 class="text-3xl font-semibold tracking-tight">Need help connecting?</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-white/90">Contact STEMMechanics with the minecraft player name, device type and what happened when you tried to connect.</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button href="{{ route('contact') }}" color="outline">Contact support</x-ui.button>
                <x-ui.button href="{{ route('stemcraft.faqs') }}" color="outline">Read the FAQs</x-ui.button>
            </div>
        </section>
    </x-container>
</x-layout>
