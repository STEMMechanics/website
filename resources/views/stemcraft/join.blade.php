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
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">Joining</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Join with the right details, then start building.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">STEMCraft access is usually arranged through a workshop, community program, or direct onboarding (only in some circumstances). That helps keep the space manageable and makes it easier to support players who are joining for the first time.</p>
                    <p class="mt-4 text-base leading-7 text-gray-600">If you already have access, use the join details provided to you and make sure the Minecraft username you use matches the one linked to your website profile where required.</p>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Use the correct username</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">If your access is linked to the website, the Minecraft username on your account needs to match the one you join with.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Check current version notes</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Supported platform and version details can change between server releases, workshops, or technical updates. It is generally the latest version.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Read the rules first</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">The rules explain how the server expects players to build, communicate, and share the world with others.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Quick checklist</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 list-disc pl-4">
                        <li class="pl-1">Make sure you have access arranged through a workshop</li>
                        <li class="pl-1">Confirm the Minecraft username and platform (java/bedrock) you plan to use</li>
                        <li class="pl-1">Read the server <a href="{{ route('stemcraft.rules') }}" class="link">rules</a> before joining</li>
                        <li class="pl-1">Visit the <a href="{{ route('account.stemcraft.index') }}" class="link">STEMCraft</a> page under your user account</li>
                        <li class="pl-1">Add your Minecraft account to your profile</li>
                        <li class="pl-1">Add the STEMCraft server details into Minecraft</li>
                    </ul>
                </section>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Server Info</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Below are the settings you need for your Minecraft client. Not all settings may be needed depending on your platform.</p>
                <div class="bg-gray-100 p-4 rounded-2xl mt-6">
                    <table class="table-fixed max-w-108 mx-auto">
                        <tr>
                            <th class="text-left font-semibold text-gray-900 py-1">Server Name</th>
                            <td class="text-gray-600">STEMCraft</td>
                        </tr>
                        <tr>
                            <th class="text-left font-semibold text-gray-900 py-1">Server Address</th>
                            <td class="text-gray-600">play.stemcraft.com.au</td>
                        </tr>
                        <tr>
                            <th class="text-left font-semibold text-gray-900 py-1">Port</th>
                            <td class="text-gray-600">19132</td>
                        </tr>
                        <tr>
                            <th class="text-left font-semibold text-gray-900 pr-4 py-1">Server Resource Packs</th>
                            <td class="text-gray-600">Enabled</td>
                        </tr>
                    </table>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">A few practical notes</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-gray-600">
                    <p>Join details and supported client information can change over time, especially when the server is being adjusted for a new program or season.</p>
                    <p>If your website account shows a linked Minecraft username but the UUID is still pending, that usually just means the server has not seen that player connect yet.</p>
                    <p>If you run into access problems, include your Minecraft username, platform, and a short description of the issue when contacting support.</p>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <x-ui.button href="{{ route('stemcraft.rules') }}">Read the rules</x-ui.button>
                    <x-ui.button href="{{ route('forum.index') }}" color="primary-outline">Discussions</x-ui.button>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
