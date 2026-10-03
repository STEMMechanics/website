<x-layout title="Check your email — Sponsor STEMMechanics">
    <x-mast title="Check your email" />
    <x-container class="mx-auto max-w-2xl py-8 sm:py-12">
        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="leading-7 text-gray-700">We’ve sent a confirmation link to your email address. Once you confirm it, we’ll create and send the sponsorship invoice.</p>
            <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
                The link is valid for 30 minutes. If it doesn’t arrive in a few minutes, check your junk folder.
            </div>
            <div class="mt-6">
                <x-ui.button color="outline" href="{{ route('sponsor.index') }}">Back to sponsorship</x-ui.button>
            </div>
        </section>
    </x-container>
</x-layout>
