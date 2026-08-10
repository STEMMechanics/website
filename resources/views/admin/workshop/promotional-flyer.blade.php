<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Promotional Flyer</x-mast>

    <x-container>
        <form
            method="POST"
            action="{{ route('admin.workshop-flyer.generate') }}"
            target="_blank"
            x-data="{ selected: @js(array_map('strval', old('workshop_ids', []))) }"
        >
            @csrf

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] mt-4">
                <div class="space-y-6">
                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Choose workshops</h2>
                                <p class="mt-1 text-sm text-gray-600">Select up to three upcoming workshops. They will appear on each of the three DL flyers.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700"><span x-text="selected.length"></span> / 3 selected</span>
                        </div>

                        @error('workshop_ids')
                            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        @if($workshops->isEmpty())
                            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600">There are no scheduled or open upcoming workshops to promote.</div>
                        @else
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach($workshops as $workshop)
                                    @php($workshopId = (string) $workshop->id)
                                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition" :class="selected.includes(@js($workshopId)) ? 'border-primary-color bg-sky-50 ring-1 ring-primary-color' : 'border-gray-200 bg-white hover:border-gray-300'">
                                        <input
                                            type="checkbox"
                                            name="workshop_ids[]"
                                            value="{{ $workshopId }}"
                                            x-model="selected"
                                            :disabled="selected.length >= 3 && !selected.includes(@js($workshopId))"
                                            class="mt-1 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                        >
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-gray-900">{{ $workshop->title }}</span>
                                            <span class="mt-1 block text-sm text-gray-600">{{ $workshop->starts_at?->format('D j M Y, g:i a') }}</span>
                                            <span class="mt-1 block text-xs font-semibold uppercase tracking-wide text-primary-color">{{ $workshop->getLocationName() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-lg font-semibold text-gray-900">Booking call to action</h2>
                        <p class="mb-4 mt-1 text-sm text-gray-600">This short message appears at the bottom of all three flyers.</p>
                        <x-ui.input label="Footer" name="footer" type="text" :value="old('footer', $defaultFooter)" maxlength="220" class="mb-0" />
                    </section>
                </div>

                <aside class="h-fit rounded-xl border border-gray-200 bg-white p-5 xl:sticky xl:top-6">
                    <div class="text-xs font-bold uppercase tracking-widest text-primary-color">Print layout</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">A4 with three DL flyers</h2>
                    <div class="mx-auto mt-5 grid w-full max-w-xl grid-cols-3 border border-gray-300 bg-white shadow-sm" style="aspect-ratio: 297 / 210;">
                        @for($copy = 0; $copy < 3; $copy++)
                            <div class="relative flex h-full flex-col overflow-hidden border-r border-dashed border-gray-400 bg-slate-50 px-2 py-3 last:border-r-0">
                                <div class="flex h-[8%] items-center justify-center">
                                    <img src="{{ asset('logo.png') }}" alt="STEMMechanics" class="w-3/5">
                                </div>
                                <div class="my-2 flex min-h-0 flex-1 flex-col gap-1">
                                    <div class="flex-1 rounded-sm border border-sky-200 bg-white"></div>
                                    <div class="flex-1 rounded-sm border border-sky-200 bg-white"></div>
                                    <div class="flex-1 rounded-sm border border-sky-200 bg-white"></div>
                                </div>
                                <div class="truncate rounded-sm bg-primary-color px-1 py-1 text-center text-[5px] font-bold text-white">Book now at stemmechanics.com.au/workshops</div>
                            </div>
                        @endfor
                    </div>
                    <p class="mt-4 text-sm leading-6 text-gray-600 text-center">Print at 100% scale on A4 landscape paper, then cut along the two vertical guides.</p>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" class="mt-5" :disabled="$workshops->isEmpty()">
                            <i class="fa-regular fa-file-pdf mr-2"></i>Generate PDF
                        </x-ui.button>
                    </div>
                </aside>
            </div>
        </form>
    </x-container>
</x-layout>
