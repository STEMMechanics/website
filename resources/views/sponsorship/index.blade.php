<x-layout>
    <x-mast title="Sponsor STEMMechanics" description="Help bring hands-on STEM to more communities." />

    <x-container class="py-7 sm:py-10">
        <aside class="mb-7 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-950" aria-label="About STEMMechanics sponsorship">
            <p><span class="font-semibold">STEMMechanics is an independently operated Australian small business.</span> Sponsorship directly supports the delivery and expansion of our STEM programs.</p>
        </aside>
        <section class="grid items-start gap-7 lg:grid-cols-5">
            <figure class="order-2 overflow-hidden rounded-2xl lg:order-1 lg:col-span-2 lg:min-h-[32rem] mt-8">
                <img src="{{ asset('cairns-minecraft-workshop.webp') }}" alt="Children taking part in a STEMMechanics community workshop in Cairns" class="h-56 w-full object-cover sm:h-72 lg:h-[32rem]" fetchpriority="high">
                <figcaption class="sr-only">Children learning and creating together at a community workshop.</figcaption>
            </figure>
            <div class="order-1 lg:order-2 lg:col-span-3">
                <h2 class="mt-2 text-3xl font-semibold leading-tight text-gray-900 sm:text-4xl">Help hands-on STEM reach further.</h2>
                <p class="mt-4 leading-7 text-gray-600">Over three years, we’ve delivered more than 250 workshops and reached around 3,000 children across North Queensland—from Cairns and the Atherton Tablelands to Townsville, Mount Isa, Hughenden, Croydon, Mareeba, Laura and Julia Creek.</p>
                <p class="mt-3 leading-7 text-gray-600">Our work carries on between workshops. <a href="{{ route('stemcraft.index') }}" class="font-medium text-primary-color hover:underline">STEMCraft</a> gives young makers a place to keep learning and creating online. Our team also gives time to answer questions, help with personal projects and keep kids engaged and connected. We’re building open-source projects like Craftarr, too.</p>
                <p class="mt-3 leading-7 text-gray-600">Sponsorship helps us offer more free and low-cost STEM experiences, reach regional communities and give young people access to equipment, materials and ongoing learning opportunities.</p>
                <ul class="mt-5 grid gap-x-5 gap-y-3 sm:grid-cols-2" aria-label="What sponsorship supports">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-people-group mt-1 w-4 shrink-0 text-center text-primary-color" aria-hidden="true"></i>
                        <p class="text-sm leading-5 text-gray-700"><span class="font-semibold mb-2">Free &amp; subsidised workshops</span><span class="block">Reduce or cover workshop costs for children and families.</span></p>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-route mt-1 w-4 shrink-0 text-center text-primary-color" aria-hidden="true"></i>
                        <p class="text-sm leading-5 text-gray-700"><span class="font-semibold mb-2">Regional communities</span><span class="block">Take workshops to communities where travel costs are a barrier.</span></p>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-toolbox mt-1 w-4 shrink-0 text-center text-primary-color" aria-hidden="true"></i>
                        <p class="text-sm leading-5 text-gray-700"><span class="font-semibold mb-2">Equipment &amp; workshop resources</span><span class="block">Reusable equipment, activity materials and workshop kits.</span></p>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-laptop mt-1 w-4 shrink-0 text-center text-primary-color" aria-hidden="true"></i>
                        <p class="text-sm leading-5 text-gray-700"><span class="font-semibold mb-2">Online learning &amp; STEMCraft</span><span class="block">Keep young people learning and experimenting between workshops.</span></p>
                    </li>
                </ul>
                <div id="sponsorship-highlights" class="mt-5 grid grid-cols-3 border-y border-gray-200 py-4" aria-label="STEMMechanics at a glance">
                    <article class="pr-2 sm:pr-4 text-center">
                        <p class="text-2xl font-bold text-primary-color sm:text-3xl"><span data-count-up-target="3" data-count-up-suffix="" aria-hidden="true">3</span><span class="sr-only">3</span></p>
                        <p class="mt-1 text-xs leading-5 text-gray-600 sm:text-sm">years in communities</p>
                    </article>
                    <article class="border-l border-gray-200 px-3 sm:px-5 text-center">
                        <p class="text-2xl font-bold text-primary-color sm:text-3xl"><span data-count-up-target="250" data-count-up-suffix="+" aria-hidden="true">250+</span><span class="sr-only">250 or more</span></p>
                        <p class="mt-1 text-xs leading-5 text-gray-600 sm:text-sm">workshops delivered</p>
                    </article>
                    <article class="border-l border-gray-200 pl-3 sm:pl-5 text-center">
                        <p class="text-2xl font-bold text-primary-color sm:text-3xl"><span data-count-up-target="3000" data-count-up-suffix="" aria-hidden="true">3,000</span><span class="sr-only">3,000 children</span></p>
                        <p class="mt-1 text-xs leading-5 text-gray-600 sm:text-sm">children reached</p>
                    </article>
                </div>
            </div>
        </section>
    </x-container>

    <x-container class="pb-10 sm:pb-14">
        <section aria-labelledby="sponsorship-ways-heading" class="border-t border-gray-200 pt-7">
            <div class="mb-5">
                <h2 id="sponsorship-ways-heading" class="text-2xl font-semibold text-gray-900">Choose a sponsorship option that works for you.</h2>
            </div>
            <div class="grid items-stretch gap-4 {{ $bitcoinEnabled && $bitcoinAddress ? 'lg:grid-cols-3' : 'md:grid-cols-2' }}">
                <article class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex min-h-9 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-primary-color"><i class="fa-solid fa-handshake" aria-hidden="true"></i></span><h3 class="text-xl font-semibold text-gray-900">Community Support</h3></div>
                    <p class="mt-3 flex-1 text-sm leading-6 text-gray-600">A simple one-time or monthly sponsorship for individuals and community members who want to support STEMMechanics.</p>
                    @if($communitySupportMinimum !== null)
                        <p class="mt-4 text-sm font-semibold text-gray-800">From AUD ${{ number_format((float) $communitySupportMinimum, 2) }}@if($communitySupportMonthlyAvailable) <span class="font-normal text-gray-600">· monthly available</span>@endif</p>
                    @endif
                    <div class="mt-auto pt-5"><x-ui.button href="{{ route('sponsor.community-support') }}" class="w-full">Community Support</x-ui.button></div>
                </article>

                <article class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex min-h-9 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-primary-color"><i class="fa-solid fa-building" aria-hidden="true"></i></span><h3 class="text-xl font-semibold text-gray-900">Business Sponsorship</h3></div>
                    <p class="mt-3 text-sm leading-6 text-gray-600">Partner with STEMMechanics to help deliver hands-on STEM experiences across North Queensland. Business sponsors can support workshops, regional delivery, equipment and ongoing learning opportunities, with recognition based on the level of sponsorship.</p>
                    @if($businessMinimum !== null)
                        <p class="mt-4 text-sm font-semibold text-gray-800">Options from AUD ${{ number_format((float) $businessMinimum, 2) }}</p>
                    @endif
                    <div class="mt-auto pt-5"><x-ui.button color="outline" href="{{ route('sponsor.start', ['new' => 1]) }}" class="w-full">Business Sponsorship</x-ui.button></div>
                </article>

                @if($bitcoinEnabled && $bitcoinAddress)
                    <article id="bitcoin" x-data="{ copied: false, qrOpen: false, async copy(address) { try { await navigator.clipboard.writeText(address); this.copied = true; setTimeout(() => this.copied = false, 2200); } catch (_) { this.copied = false; } } }" class="scroll-mt-6 flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex min-h-9 items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"><i class="fa-brands fa-bitcoin" aria-hidden="true"></i></span><h3 class="text-xl font-semibold text-gray-900">Support with Bitcoin</h3></div>
                            @if($bitcoinQrAvailable)
                                <button type="button" x-on:click="qrOpen = true" class="group flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-white p-1 focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="Show a larger Bitcoin QR code">
                                    <img src="{{ route('sponsor.asset', ['type' => 'btc', 'id' => 0]) }}" alt="" class="h-full w-full object-contain transition group-hover:scale-105">
                                </button>
                            @endif
                        </div>
                        <p class="mt-3 flex-1 text-sm leading-6 text-gray-600">Send a one-off, anonymous sponsorship by scanning the QR code or copying our address.</p>
                        <code class="mt-4 block break-all rounded-lg bg-amber-50 px-3 py-3 text-xs leading-5 text-gray-800">{{ $bitcoinAddress }}</code>
                        <div class="mt-auto pt-3"><button type="button" x-on:click="copy(@js($bitcoinAddress))" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-300 bg-white px-4 py-2 text-center text-sm font-semibold text-gray-800 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500"><i class="fa-regular fa-copy" aria-hidden="true"></i><span x-text="copied ? 'Address copied' : 'Copy address'"></span></button></div>
                        @if($bitcoinQrAvailable)
                            <div x-cloak x-show="qrOpen" x-transition.opacity x-on:click.self="qrOpen = false" x-on:keydown.escape.window="qrOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="bitcoin-qr-title">
                                <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                                    <button type="button" x-on:click="qrOpen = false" class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="Close enlarged QR code"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                    <h4 id="bitcoin-qr-title" class="pr-8 text-left text-lg font-semibold text-gray-900">Bitcoin support</h4>
                                    <p class="mt-1 text-left text-sm text-gray-600">Scan this code to make a one-off sponsorship.</p>
                                    <img src="{{ route('sponsor.asset', ['type' => 'btc', 'id' => 0]) }}" alt="Bitcoin QR code for STEMMechanics support" class="mx-auto mt-5 h-72 w-72 max-w-full rounded-xl border border-gray-200 bg-white p-3">
                                    <button type="button" x-on:click="copy(@js($bitcoinAddress))" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500"><i class="fa-regular fa-copy" aria-hidden="true"></i><span x-text="copied ? 'Address copied' : 'Copy address'"></span></button>
                                </div>
                            </div>
                        @endif
                    </article>
                @endif
            </div>
            <aside class="mt-4 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6">
                <div>
                    <h3 class="font-semibold text-gray-900">Practical partnership support</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Workshop kits, venue hire, regional travel, accommodation, printing, consumable materials, software, hosting or technical expertise can all help us deliver.</p>
                </div>
                <x-ui.button color="outline" class="mt-4 shrink-0 sm:mt-0" href="{{ route('contact') }}">Talk with us</x-ui.button>
            </aside>
            <aside class="mt-4 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6">
                <div>
                    <h3 class="font-semibold text-gray-900">See who partners with STEMMechanics.</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">We’re pleased to recognise the people and organisations partnering with us to bring hands-on STEM learning to more communities.</p>
                </div>
                <x-ui.button color="outline" class="mt-4 shrink-0 sm:mt-0" href="{{ route('sponsors.index') }}">Meet our sponsors</x-ui.button>
            </aside>
            <section class="mt-10" aria-labelledby="sponsorship-faq-heading">
                <div class="text-center">
                    <h2 id="sponsorship-faq-heading" class="text-2xl font-semibold text-gray-900">Sponsorship FAQs</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">A few practical answers about partnering with STEMMechanics.</p>
                </div>
                <div class="mx-auto mt-5 max-w-4xl space-y-3">
                    <details class="group rounded-lg border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-gray-900">
                            <span>Is STEMMechanics a charity?</span>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-500 transition group-open:rotate-45"><i class="fa-solid fa-plus text-sm" aria-hidden="true"></i></span>
                        </summary>
                        <div class="border-t border-gray-100 px-5 py-4 text-sm leading-6 text-gray-600">
                            No. STEMMechanics is an independently operated Australian small business. Sponsorship supports our educational programs and community activities and may include recognition or other sponsorship benefits.
                        </div>
                    </details>
                    <details class="group rounded-lg border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-gray-900">
                            <span>Can businesses receive an invoice?</span>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-500 transition group-open:rotate-45"><i class="fa-solid fa-plus text-sm" aria-hidden="true"></i></span>
                        </summary>
                        <div class="border-t border-gray-100 px-5 py-4 text-sm leading-6 text-gray-600">
                            Yes. Business sponsorships can be invoiced and GST is handled where applicable.
                        </div>
                    </details>
                    <details class="group rounded-lg border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-gray-900">
                            <span>Can we sponsor a specific workshop or community?</span>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-500 transition group-open:rotate-45"><i class="fa-solid fa-plus text-sm" aria-hidden="true"></i></span>
                        </summary>
                        <div class="border-t border-gray-100 px-5 py-4 text-sm leading-6 text-gray-600">
                            Yes. STEMMechanics can work with businesses and organisations wanting to support a particular program, location or group.
                        </div>
                    </details>
                </div>
            </section>
            <section class="mx-auto mt-10 flex max-w-4xl flex-col gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5" aria-labelledby="manage-sponsorship-heading">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"><i class="fa-solid fa-repeat" aria-hidden="true"></i></span>
                    <div>
                        <h3 id="manage-sponsorship-heading" class="font-semibold text-gray-900">Already sponsoring STEMMechanics?</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-700">View payment history and invoices, update public recognition or manage a monthly sponsorship.</p>
                    </div>
                </div>
                <a href="{{ route('sponsor.manage.request') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-primary-color transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-500">Manage your sponsorship</a>
            </section>
        </section>
    </x-container>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const animateCounters = () => {
                const counters = Array.from(document.querySelectorAll('#sponsorship-highlights [data-count-up-target]'));
                if (!counters.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                const duration = 1300;
                const targets = counters.map((counter) => ({
                    element: counter,
                    target: Number(counter.dataset.countUpTarget || 0),
                    suffix: counter.dataset.countUpSuffix || '',
                }));
                targets.forEach(({ element, suffix }) => { element.textContent = `0${suffix}`; });
                const startedAt = performance.now();
                const step = (now) => {
                    const progress = Math.min((now - startedAt) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    targets.forEach(({ element, target, suffix }) => {
                        element.textContent = `${new Intl.NumberFormat('en-AU').format(Math.round(target * eased))}${suffix}`;
                    });
                    if (progress < 1) window.requestAnimationFrame(step);
                };
                window.requestAnimationFrame(step);
            };
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', animateCounters, { once: true });
            else animateCounters();
        })();
    </script>
</x-layout>
