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
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Clear answers for parents, schools, and workshop families.</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">These questions focus on the basics: who can join, how access is approved, what happens if someone tries too early, and what parents can manage themselves.</p>
                </div>

                <div class="mt-8 space-y-4">
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">What is STEMCraft?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">STEMCraft is the Minecraft server connected to STEMMechanics workshops and group programs. It is designed for families, schools, and OSHC providers who want a safer, more structured space.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Why is the server address public if access is restricted?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">The address is public so it is easy to find and use. Access still has to be approved before a player can enter the server.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">How do I set up access?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">A parent creates the website account using the workshop email, adds the child’s Minecraft username, and the access is granted straight away.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">What happens if my child tries to join before approval?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">They are redirected to the website instead of being allowed into the server. That is a sign the access setup still needs to be completed.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Does access expire?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No. Access stays in place until a parent changes it from the account page.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Can I change the Minecraft username later?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes. Parents can update or remove usernames whenever they need to.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Does STEMCraft support Java and Bedrock?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes. Both editions are supported. The join page lists the current address and the Bedrock port.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Can schools and OSHC providers use it?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes. Group access can be arranged for schools and OSHC providers. Contact STEMMechanics to discuss the setup.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">How do I contact STEMMechanics if something looks wrong?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Use the contact page and include the Minecraft username, the device or edition you are using, and a short description of the issue.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <h3 class="text-base font-semibold text-gray-900">Can more than one child use the same parent account?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Yes. Families can manage multiple approved usernames from the same website account.</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Still need help?</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">If you are not sure whether access is set up correctly, it is better to ask than guess.</p>
                    <div class="mt-5">
                        <x-ui.button href="{{ route('contact') }}" class="w-full">Contact STEMMechanics</x-ui.button>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Quick reminder</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 list-disc pl-4">
                        <li class="pl-1">Workshop first.</li>
                        <li class="pl-1">Parent sets up the account.</li>
                        <li class="pl-1">Add the Minecraft username.</li>
                        <li class="pl-1">Access is granted instantly.</li>
                        <li class="pl-1">The player joins after approval.</li>
                    </ul>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
