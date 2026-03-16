<x-layout>
    <x-mast description="Questions, workshop enquiries, partnerships, and support requests all start here.">Contact STEMMechanics</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white inline-flex">Send a message</div>
                    <h2 class="mt-4 text-3xl font-semibold text-gray-900">Tell us what you need</h2>
                    <p class="mt-3 text-base leading-7 text-gray-600">Use the form below for workshop bookings, school or community programs, technical questions, invoice support, or general enquiries. If your request is time-sensitive, include the best way to reach you.</p>
                </div>

                <form method="POST" action="{{ route('contact.send') }}" class="mt-8 space-y-5">
                    @csrf

                    <x-form-guard form="contact" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            label="Your name"
                            name="name"
                            value="{{ $defaultName ?? '' }}"
                            autocomplete="name"
                        />
                        <x-ui.input
                            type="email"
                            label="Email address"
                            name="email"
                            value="{{ $defaultEmail ?? '' }}"
                            autocomplete="email"
                        />
                    </div>

                    <x-ui.input
                        label="Subject"
                        name="subject"
                        value="{{ old('subject') }}"
                        info="A short summary helps us route your message quickly."
                    />

                    <x-ui.input
                        type="textarea"
                        label="Message"
                        name="message"
                        value="{{ old('message') }}"
                        info="Include any dates, locations, age groups, or project details that would help us respond properly."
                    />

                    <x-altcha-proof />

                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500">We usually reply by email, so make sure the address above is one you can access.</p>
                        <x-ui.button type="submit">Send message</x-ui.button>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-1">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Direct contact</h2>
                    <div class="mt-4 space-y-4 text-sm text-gray-600">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</div>
                            <a href="mailto:hello@stemmechanics.com.au" class="mt-1 inline-block font-medium text-primary-color hover:text-primary-color-dark">hello@stemmechanics.com.au</a>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</div>
                            <a href="tel:+61400130190" class="mt-1 inline-block font-medium text-primary-color hover:text-primary-color-dark">0400 130 190</a>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Service area</div>
                            <p class="mt-1">STEMMechanics delivers programs across Queensland and works with both public and private bookings.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Postal address</h2>
                    <div class="mt-4 text-sm leading-7 text-gray-600">
                        <p>STEMMechanics</p>
                        <p>63 Dalton Street</p>
                        <p>Westcourt, QLD, 4870</p>
                        <p>Australia</p>
                    </div>
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                        This is a postal address only. Workshops and programs are not delivered at this address.
                    </div>
                    <div class="mt-4 rounded-2xl bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        <span class="font-semibold">ABN:</span> 15 772 281 735
                    </div>
                </section>
            </div>
        </div>
    </x-container>
</x-layout>
