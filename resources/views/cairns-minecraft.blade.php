<x-layout
    title="Cairns Minecraft"
    description="Cairns Minecraft closed in May 2022 and the archives are preserved here."
    :canonical="route('cairns.minecraft')"
    :ogImage="asset('cairns-minecraft-logo.webp')"
>
    <x-container class="py-8">
        <article class="mx-auto max-w-4xl">
            <div class="flex justify-center">
                <div class="h-24 w-full max-w-md sm:h-28">
                    <img
                        src="{{ asset('cairns-minecraft-logo.webp') }}"
                        alt="Cairns Minecraft logo"
                        class="h-full w-full object-contain"
                    />
                </div>
            </div>

            <div class="mt-8 space-y-5 text-lg leading-8 text-gray-800">
                <p>
                    Cairns Minecraft closed in May 2022. It was a place for Minecrafters from Far North Queensland to get together, create, share and compete.
                </p>
                <p>
                    Cairns Minecraft was developed to stimulate ideas and discussion with young people about urban design; architecture; public art and space. The initial creative server was launched at the 2016 Cairns Children’s Festival and had over 700 members on the server whitelist.
                </p>
                <p>
                    STEMCraft started in July 2023, and many players moved across to the new server. Workshops, builds, and community activity continued there, giving the same group a place to keep learning, creating, and connecting after Cairns Minecraft closed.
                </p>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <div class="w-full rounded-2xl shadow-sm ring-1 ring-gray-200 h-64 bg-center bg-cover" style="background-image: url('{{ asset('cairns-minecraft-workshop.webp') }}')"></div>
                <div class="w-full rounded-2xl shadow-sm ring-1 ring-gray-200 h-64 bg-center bg-cover" style="background-image: url('{{ asset('cairns-minecraft-spawn.webp') }}')"></div>
            </div>

            <p class="mt-8 text-base leading-7 text-gray-700">
                The Cairns Minecraft world archives are preserved below as external downloads.
            </p>

            <div class="mt-8 space-y-4 border-t border-gray-200 pt-6">
                @foreach($downloads as $download)
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $download['filename'] }}</div>
                            <div class="text-sm text-gray-600">{{ $download['size'] }} - {{ $download['description'] }}</div>
                        </div>
                        <div class="flex flex-nowrap items-center gap-2">
                            @if(trim((string) $download['url']) !== '')
                                <a
                                    href="{{ $download['url'] }}"
                                    class="inline-flex items-center gap-2 rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <i class="fa-solid fa-download"></i>
                                    <span>Download</span>
                                </a>
                            @else
                                <span class="text-sm text-gray-500">CDN link not set</span>
                            @endif

                            @if(trim((string) ($download['magnet_url'] ?? '')) !== '')
                                <a
                                    href="{{ $download['magnet_url'] }}"
                                    class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
                                >
                                    <i class="fa-solid fa-magnet"></i>
                                    <span>Magnet</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </x-container>
</x-layout>
