<x-layout>
    <x-card class="relative mx-auto mt-12 max-w-lg shadow-lg">
        <header>
            <h2 class="-m-6 mb-6 rounded-t-lg bg-green px-6 py-4 text-xl text-white">Sign up to STEMMechanics</h2>
        </header>

        <form method="POST" action="/verify" id="verification-form">
            @csrf
            @include('partials.email-verify')

        </form>
    </x-card>
</x-layout>
