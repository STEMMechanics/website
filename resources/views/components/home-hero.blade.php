@props(['hero', 'imageUrl', 'preview' => false])
    <section @unless($preview) id="banner" @endunless class="relative isolate overflow-hidden bg-center bg-no-repeat bg-cover">
        <img src="{{ $imageUrl }}" @if($preview) x-bind:src="heroImageUrl" @endif alt="" class="absolute inset-0 h-full w-full object-cover" fetchpriority="high" />
        <div class="absolute inset-0 bg-linear-to-r from-black/72 to-black/18"></div>
        <x-container class="py-24 sm:py-28 lg:py-32 relative">
            <div class="z-10 absolute right-4 bottom-4 rounded-full bg-black/65 px-3 py-1 text-xs text-white shadow-lg" @if($preview) x-text="hero.caption" x-show="hero.caption" @elseif($hero['caption'] === '') hidden @endif>{{ $hero['caption'] }}</div>
            <div class="relative max-w-4xl">
                <div class="absolute -left-4 top-4 -z-10 h-72 w-[20rem] rounded-[52%_48%_58%_42%/43%_55%_45%_57%] bg-amber-300/16 blur-3xl sm:-left-10 sm:h-120 sm:w-120"></div>
                <div class="relative inline-block w-full max-w-3xl min-h-88 sm:min-h-96 lg:min-h-104">
                    <div class="absolute -top-10 -left-20 -bottom-15 md:bottom-0 right-0 rounded-[40%_60%_28%_72%/66%_30%_70%_34%] bg-[#00a6f4bf] shadow-2xl ring-1 ring-black/10 home-hero-blob-bg"></div>

                    <div class="relative px-7 py-8 sm:px-10 sm:py-10">
                        <p class="mb-3 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-white ring-1 ring-white/20" @if($preview) x-text="hero.eyebrow" x-show="hero.eyebrow" @elseif($hero['eyebrow'] === '') hidden @endif>{{ $hero['eyebrow'] }}</p>
                        <h2 class="max-w-2xl text-3xl font-bold text-white sm:text-4xl" @if($preview) x-text="hero.heading" @endif>{{ $hero['heading'] }}</h2>
                        <div class="mt-4 max-w-2xl space-y-3 text-white/90">
                            @if($preview)
                                <template x-for="(paragraph, index) in hero.body.replace(/\r\n?/g, '\n').split(/\n\s*\n/).filter(text => text.trim())" :key="index">
                                    <p class="whitespace-pre-line" x-text="paragraph"></p>
                                </template>
                            @else
                                @foreach(preg_split('/\R\s*\R/u', $hero['body'], -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                                    <p class="whitespace-pre-line">{{ $paragraph }}</p>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </x-container>
    </section>
