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
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">FAQs</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">The basics, without having to dig through everything else first.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">These answers are meant to clear up the questions that come up most often around joining, account linking, whitelist access, and moderation visibility.</p>
                </div>

                <div class="mt-8 space-y-4">
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">How do I get access to STEMCraft?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Access is usually arranged through a workshop, community program, or direct onboarding (only in some circumstances).</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">If you are unsure whether you already have access, login to the website and under the user menu, check if you have STEMCraft listed:</p>
                        <img class="mx-auto w-48 h-auto rounded-lg" src="/stemcraft-user-menu.webp" alt="stemcraft-menu" />
                        <p class="mt-2 text-sm leading-6 text-gray-600">From that menu item, you can add your Minecraft accounts to the server. <a href="{{ route('contact') }}" class="link">Contact us</a> if you have any issues.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Do I need a website account?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Generally yes. The website is used to manage linked Minecraft usernames and whitelist access for players or programs. It also allows us to have the contact details of the parent if issues arise.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Can I join using Java/Bedrock</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Sure! The server supports both Java and Bedrock players. Generally you require the latest version and a valid Minecraft account to join. You can visit the <a href="{{ route("forum.index") }}" class="link">STEMCraft Discussions</a> to see the current version and other news.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">What Minecraft version does the server run?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Just like Minecraft at home, STEMCraft server runs on a specific Minecraft version. You can see the current server version on the <a href="{{ route('stemcraft.index') }}" class="link">STEMCraft Overview</a> page.
                        </p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Newer Minecraft game versions can sometimes still join, but mobs, blocks, or items added after the server’s version may not appear in the game yet.
                        </p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            For news about upgrades and upcoming updates, visit the <a href="{{ route('forum.index') }}" class="link">STEMCraft Discussions</a>.
                        </p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Why does my linked account say UUID pending?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">That usually means the server has not seen that Minecraft account log in yet. Once it does, the UUID will be recorded against the account.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">What happens if I change my Minecraft player name?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Nothing! While Minecraft player names can change, their UUID's do not. Provided your player name was previously whitelisted, once you login again under your new player name, the website will be updated accordingly.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Why is there a public punishments page?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">It exists for transparency. It helps players understand when restrictions are active and shows that moderation decisions are not being hidden from the community.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Can punishments be lifted?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes. Depending on the issue, restrictions can expire naturally or be lifted by admins when appropriate.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">What should I do if something looks wrong with my access?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Contact <a href="{{ route('contact') }}" class="link">STEMMechanics</a> and include your Minecraft username, the platform you are using, and what you expected to happen.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Is there a live player map of the server?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes, the server does have a live map of the worlds and players. Some worlds, such as survival worlds, have 'hiding' enabled meaning that players can hide themselves on the map if they are under blocks and not visible from the sky.</p>
                        <p class="mt-6 text-center"><x-ui.button href="https://map.stemcraft.com.au/" target="_blank">STEMCraft Map</x-ui.button></p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">How many accounts can I link to my website account?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">You can link up to 5 player accounts to a single website account. This allows families to manage their children’s accounts in one place.</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Linked accounts should only be for members of your household. Minecraft accounts that belong to friends or other players should not be added.</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">If your family needs to link more than 5 accounts, please contact us and we can help.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Still unsure?</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If your question is not covered here, it is better to ask directly than guess and end up stuck on access or account details.</p>
                    <div class="mt-8 text-center">
                        <x-ui.button href="{{ route('contact') }}" class="block w-full">Contact Us</x-ui.button>
                    </div>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
