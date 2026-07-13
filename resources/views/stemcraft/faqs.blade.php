<x-layout>
    @php
        $tabs = [
            ['title' => 'Overview', 'route' => route('stemcraft.index')],
            ['title' => 'Join', 'route' => route('stemcraft.join')],
            ['title' => 'Rules', 'route' => route('stemcraft.rules')],
            ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ];

        $faqs = \App\Support\StemcraftFaqs::all();
    @endphp

    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs" description="Practical answers">STEMCraft FAQs</x-mast>

    <x-container class="py-14">
        <div class="flex gap-8">
            <div>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Clear answers before you connect.</h2>
                <p class="mt-4 text-base leading-7 text-gray-600">
                    STEMCraft is a small supported online space connected to STEMMechanics workshops. These answers explain what it is, how to join and how we help young makers participate respectfully.
                </p>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm w-96 h-48 shrink-0">
                <img
                        src="{{ asset('stemcraft-workshop.webp') }}"
                        alt="Young makers building and experimenting during a STEMMechanics workshop"
                        class="w-full object-center"
                >
            </div>
        </div>

        <div class="mt-10 space-y-3">
            @foreach($faqs as $faq)
                <details class="group rounded-lg border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-gray-900">
                        <span>{{ $faq['question'] }}</span>

                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-500 transition group-open:rotate-45">
                            <i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
                        </span>
                    </summary>

                    <div class="border-t border-gray-100 px-5 py-4">
                        <p class="text-base leading-7 text-gray-600">
                            {{ $faq['answer'] }}
                        </p>
                    </div>
                </details>
            @endforeach
        </div>
    </x-container>

    <x-container class="py-12">
        <section class="mt-12 overflow-hidden rounded-2xl bg-primary-color px-6 py-12 text-center text-white shadow-sm sm:px-10">
            <h2 class="text-3xl font-semibold tracking-tight">We can help you work out the next step.</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-white/90">Contact STEMMechanics if you need help with connection details, workshop links or whether STEMCraft is the right fit.</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button href="{{ route('contact') }}" color="outline">Contact support</x-ui.button>
                <x-ui.button href="{{ route('stemcraft.join') }}" color="outline">How to join</x-ui.button>
            </div>
        </section>
    </x-container>
</x-layout>
