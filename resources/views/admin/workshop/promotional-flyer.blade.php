<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Workshop Promotional Flyer</x-mast>

    <x-container>
        <form
            method="POST"
            action="{{ route('admin.workshop-flyer.generate') }}"
            target="_blank"
            x-data="workshopFlyerBuilder(
                @js($previewWorkshops),
                @js(array_map('strval', old('workshop_ids', []))),
                @js(old('customizations', [])),
                @js(old('footer', $defaultFooter))
            )"
        >
            @csrf

            <template x-for="workshopId in selected" :key="`customization-${workshopId}`">
                <div>
                    <input type="hidden" :name="`customizations[${workshopId}][description]`" :value="customizations[workshopId].description">
                    <input type="hidden" :name="`customizations[${workshopId}][image_zoom]`" :value="customizations[workshopId].image_zoom">
                    <input type="hidden" :name="`customizations[${workshopId}][image_x]`" :value="customizations[workshopId].image_x">
                    <input type="hidden" :name="`customizations[${workshopId}][image_y]`" :value="customizations[workshopId].image_y">
                </div>
            </template>

            <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
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
                                        <x-ui.checkbox
                                            :noWrapper="true"
                                            name="workshop_ids[]"
                                            value="{{ $workshopId }}"
                                            x-model="selected"
                                            x-bind:disabled="selected.length >= 3 && !selected.includes(@js($workshopId))"
                                            inputClass="mt-1"
                                        />
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
                        <x-ui.input label="Footer" name="footer" type="text" :value="old('footer', $defaultFooter)" maxlength="220" class="mb-0" x-model="footer" />
                    </section>
                </div>

                <aside class="grid h-fit grid-cols-1 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white p-5 lg:grid-cols-2 lg:divide-x lg:divide-y-0 xl:sticky xl:top-6 xl:grid-cols-1 xl:divide-x-0 xl:divide-y">
                    <div class="pb-6 lg:pb-0 lg:pr-6 xl:pb-6 xl:pr-0">
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
                    </div>
                    <div class="flex flex-col pt-6 lg:pl-6 lg:pt-0 xl:pl-0 xl:pt-6">
                        <div class="text-xs font-bold uppercase tracking-widest text-primary-color">Live preview</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">One DL flyer</h2>
                        <p class="mt-1 text-sm text-gray-600">Select a workshop card to edit its picture and description.</p>

                        <div class="relative mx-auto mt-4 w-full max-w-[21rem] overflow-hidden border border-gray-300 bg-white shadow-sm" style="aspect-ratio: 99 / 210; container-type: inline-size;">
                            <div class="text-center leading-none" style="margin-top: 6.06cqw;">
                                <img src="{{ asset('logo.png') }}" alt="STEMMechanics" class="inline-block" style="width: 38.38cqw;">
                            </div>

                            <div class="mx-auto" style="width: 80cqw;">
                                <template x-if="selected.length === 0">
                                    <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-xs text-gray-500">
                                        Select at least one workshop to preview it here.
                                    </div>
                                </template>

                                <template x-for="(workshopId, index) in selected" :key="`preview-${workshopId}`">
                                    <button
                                        type="button"
                                        class="block w-full bg-white text-left transition"
                                        :class="activeId === workshopId ? 'ring-3 ring-primary-color/60' : ''"
                                        style="margin: 2.14cqw 0; padding: 1px; border-radius: 2.14cqw;"
                                        @click="activeId = workshopId"
                                    >
                                        <div class="overflow-hidden" style="border-radius: 2.14cqw;">
                                            <div
                                                    class="relative w-full cursor-move touch-none select-none overflow-hidden bg-slate-100"
                                                    :class="dragState?.workshopId === workshopId ? 'cursor-grabbing' : 'cursor-grab'"
                                                    style="height: 28.28cqw;"
                                                    @pointerdown.stop.prevent="startImageDrag($event, workshopId)"
                                                    @pointermove.stop.prevent="moveImageDrag($event)"
                                                    @pointerup.stop.prevent="endImageDrag($event)"
                                                    @pointercancel.stop="endImageDrag($event)"
                                            >
                                                <template x-if="workshops[workshopId].image">
                                                    <img
                                                        :src="workshops[workshopId].image"
                                                        alt=""
                                                        draggable="false"
                                                        class="absolute max-w-none object-cover"
                                                        :style="imageStyle(workshopId)"
                                                    >
                                                </template>
                                                <template x-if="!workshops[workshopId].image">
                                                    <div class="flex h-full items-center justify-center font-bold text-sky-600" style="font-size: 2.85cqw;">STEM</div>
                                                </template>
                                            </div>
                                            <div class="relative flex items-end justify-between gap-2" style="height: 7.07cqw;">
                                                <span class="truncate font-bold text-slate-900" style="font-size: 3.56cqw;" x-text="workshops[workshopId].title"></span>
                                                <span
                                                        class="shrink-0 font-bold uppercase"
                                                        :style="`color: ${accentColour(index)}; font-size: 2.14cqw; letter-spacing: 0.18cqw; margin-bottom: 0.81cqw;`"
                                                        x-text="workshops[workshopId].location"
                                                ></span>
                                            </div>
                                            <p class="overflow-hidden leading-none text-slate-600" style="height: 17.17cqw; padding-top: 1.01cqw; font-size: 2.28cqw; line-height: 1.4" x-text="previewDescription(workshopId)"></p>
                                            <div class="truncate font-bold text-slate-700" style="height: 4.04cqw; margin-bottom: 2.02cqw; font-size: 2.32cqw; line-height: 1.1;">
                                                <span x-text="workshops[workshopId].date"></span>
                                                <template x-if="workshops[workshopId].duration"><span> &middot; <span x-text="workshops[workshopId].duration"></span></span></template>
                                                <span> &middot; </span><span x-text="workshops[workshopId].price"></span>
                                            </div>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <div class="absolute left-[10%] truncate bg-primary-color text-center text-white" style="bottom: 6.06cqw; width: 80%; border-radius: 2.14cqw; padding: 1.07cqw 0 1.07cqw; font-size: 2.85cqw;" x-text="footer"></div>
                        </div>

                        <template x-if="activeWorkshop">
                            <div class="mt-5 space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <x-ui.select label="Editing workshop" name="preview_workshop" x-model="activeId" class="mb-0">
                                        <template x-for="workshopId in selected" :key="`option-${workshopId}`">
                                            <option :value="workshopId" x-text="workshops[workshopId].title"></option>
                                        </template>
                                </x-ui.select>

                                <div>
                                    <x-ui.input label="Description" name="preview_description" type="textarea" x-model="customizations[activeId].description" rows="5" maxlength="400" class="mb-0" />
                                    <div class="mt-1 text-right text-xs text-gray-500"><span x-text="customizations[activeId].description.length"></span> / 400</div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-sm"><label for="image-zoom" class="font-semibold text-gray-800">Picture size</label><span x-text="`${customizations[activeId].image_zoom}%`"></span></div>
                                    <input id="image-zoom" type="range" min="100" max="200" step="1" x-model.number="customizations[activeId].image_zoom" class="mt-1 w-full accent-primary-color">
                                </div>

                                <div>
                                    <div class="flex justify-between text-sm"><label for="image-x" class="font-semibold text-gray-800">Move left / right</label><span x-text="customizations[activeId].image_x"></span></div>
                                    <input id="image-x" type="range" min="0" max="100" step="1" x-model.number="customizations[activeId].image_x" class="mt-1 w-full accent-primary-color">
                                </div>

                                <div>
                                    <div class="flex justify-between text-sm"><label for="image-y" class="font-semibold text-gray-800">Move up / down</label><span x-text="customizations[activeId].image_y"></span></div>
                                    <input id="image-y" type="range" min="0" max="100" step="1" x-model.number="customizations[activeId].image_y" class="mt-1 w-full accent-primary-color">
                                </div>

                                <button type="button" class="text-sm font-semibold text-primary-color hover:underline" @click="resetCustomization(activeId)">Reset picture and text</button>
                            </div>
                        </template>

                        <div class="flex justify-end">
                            <x-ui.button type="submit" class="mt-5" :disabled="$workshops->isEmpty()">
                                <i class="fa-regular fa-file-pdf mr-2"></i>Generate PDF
                            </x-ui.button>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </x-container>

    <script>
        function workshopFlyerBuilder(workshops, initialSelected, initialCustomizations, initialFooter) {
            const customizations = {};

            Object.entries(workshops).forEach(([id, workshop]) => {
                const saved = initialCustomizations[id] ?? {};
                customizations[id] = {
                    description: saved.description ?? workshop.description,
                    image_zoom: Number(saved.image_zoom ?? 100),
                    image_x: Number(saved.image_x ?? 50),
                    image_y: Number(saved.image_y ?? 50),
                };
            });

            return {
                workshops,
                customizations,
                selected: initialSelected,
                activeId: initialSelected[0] ?? null,
                footer: initialFooter,
                dragState: null,

                init() {
                    this.$watch('selected', (selected) => {
                        if (!selected.includes(this.activeId)) {
                            this.activeId = selected[0] ?? null;
                        }
                    });
                },

                get activeWorkshop() {
                    return this.activeId ? this.workshops[this.activeId] : null;
                },

                accentColour(index) {
                    return ['#2563eb', '#15803d', '#b45309'][index] ?? '#2563eb';
                },

                imageStyle(workshopId) {
                    const settings = this.customizations[workshopId];
                    const scale = settings.image_zoom / 100;

                    return [
                        'width: 100%',
                        'height: 100%',
                        'left: 0',
                        'top: 0',
                        `object-position: ${settings.image_x}% ${settings.image_y}%`,
                        `transform: scale(${scale})`,
                        `transform-origin: ${settings.image_x}% ${settings.image_y}%`,
                    ].join('; ');
                },

                startImageDrag(event, workshopId) {
                    this.activeId = workshopId;

                    if (!this.workshops[workshopId]?.image) {
                        return;
                    }

                    event.currentTarget.setPointerCapture?.(event.pointerId);
                    const bounds = event.currentTarget.getBoundingClientRect();
                    this.dragState = {
                        workshopId,
                        pointerId: event.pointerId,
                        startClientX: event.clientX,
                        startClientY: event.clientY,
                        startX: Number(this.customizations[workshopId].image_x),
                        startY: Number(this.customizations[workshopId].image_y),
                        width: bounds.width,
                        height: bounds.height,
                    };
                },

                moveImageDrag(event) {
                    if (!this.dragState || this.dragState.pointerId !== event.pointerId) {
                        return;
                    }

                    const deltaX = ((event.clientX - this.dragState.startClientX) / this.dragState.width) * 100;
                    const deltaY = ((event.clientY - this.dragState.startClientY) / this.dragState.height) * 100;
                    const settings = this.customizations[this.dragState.workshopId];
                    settings.image_x = this.clamp(Math.round(this.dragState.startX - deltaX), 0, 100);
                    settings.image_y = this.clamp(Math.round(this.dragState.startY - deltaY), 0, 100);
                },

                endImageDrag(event) {
                    if (!this.dragState || this.dragState.pointerId !== event.pointerId) {
                        return;
                    }

                    event.currentTarget.releasePointerCapture?.(event.pointerId);
                    this.dragState = null;
                },

                clamp(value, minimum, maximum) {
                    return Math.min(maximum, Math.max(minimum, value));
                },

                previewDescription(workshopId) {
                    const description = String(this.customizations[workshopId].description ?? '')
                        .replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\uFE0F\u200D\u20E3]/gu, '')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (description.length <= 220) {
                        return description;
                    }

                    const excerpt = description.slice(0, 220);
                    const sentenceEnds = [...excerpt.matchAll(/[.!?](?=\s|$)/g)]
                        .filter((match) => (match.index ?? 0) >= 109);

                    if (sentenceEnds.length > 0) {
                        const lastSentence = sentenceEnds[sentenceEnds.length - 1];
                        return description.slice(0, (lastSentence.index ?? 0) + 1).trim();
                    }

                    return `${excerpt.replace(/\s+\S*$/, '').trim()}...`;
                },

                resetCustomization(workshopId) {
                    this.customizations[workshopId] = {
                        description: this.workshops[workshopId].description,
                        image_zoom: 100,
                        image_x: 50,
                        image_y: 50,
                    };
                },
            };
        }
    </script>
</x-layout>
