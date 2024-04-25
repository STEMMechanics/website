<x-layout :bodyClass="'image-background'">
    @props(['formmethod' => 'POST', 'formaction' => ''])
    <div class="flex items-center justify-center flex-grow py-24" x-data>
        <div x-show="$store.request.show" class="w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
            <h2 class="text-2xl font-bold mb-4 text-center">Password Required</h2>
            <div class="flex justify-center gap-4 mb-4">
                A password is required to access this file.
            </div>
            <form method="GET" action="" x-on:submit.prevent="submit">
                <x-ui.input type="password" name="password" label="Password" floating autofocus error="{{ $error ?? '' }}"/>
                <div class="flex flex-col items-center gap-4 justify-center">
                    <x-ui.button type="submit">Download</x-ui.button>
                </div>
            </form>
        </div>
        <div x-show="!$store.request.show" x-cloak class="w-full mx-2 max-w-lg p-8 pb-6 bg-white rounded-md shadow-deep">
            <h2 class="text-2xl font-bold mb-4 text-center">Requesting file</h2>
            <div class="flex justify-center mb-8">
                The file has been requested from the server...
            </div>
            <div x-show="!$store.request.home" class="flex justify-center">
                <img  src="/loading.gif" class="h-32 w-32" alt="Please wait" />
            </div>
            <div x-show="$store.request.home" class="flex justify-between gap-4">
                <x-ui.button type="link" color="primary-outline" href="#" x-on:click="$store.request.show=true">Try again</x-ui.button>
                <x-ui.button type="link" href="{{ route('index') }}">Home</x-ui.button>
            </div>
        </div>
    </div>
</x-layout>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('request', {
            show: true,
            home: false
        });
    });

    function submit(e) {
        const password = document.querySelector('input[name="password"]').value;
        const url = new URL(window.location.href);
        url.searchParams.set('password', btoa(password));
        window.location.href = url.href;

        window.setTimeout(() => {
            Alpine.store('request').show = false;
            Alpine.store('request').home = false;

            window.setTimeout(() => {
                Alpine.store('request').home = true;
            }, 4000);
        }, 100);
    }
</script>
