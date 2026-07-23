@php
    $footerWorkshopCategories = \App\Models\WorkshopCategory::query()
        ->where('hide_in_footer', false)
        ->orderBy('name')
        ->get();
@endphp

<footer class="flex flex-col bg-gray-950 text-gray-400 px-4 py-8 sm:px-12 sm:py-16 mt-12 relative">
    <div class="absolute -top-4 left-0 w-full overflow-hidden leading-none">
        <svg viewBox="0 0 1440 120" class="block w-full h-4" preserveAspectRatio="none">
            <path
                    d="M0,32 C240,120 480,120 720,64 C960,8 1200,8 1440,96 L1440,120 L0,120 Z"
                    fill="#030712"
            />
        </svg>
    </div>
    <section class="grid gap-8 mb-12 sm:grid-cols-3 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]">
        <div class="text-center lg:text-left text-sm self-center lg:pr-8 flex flex-col gap-3 sm:col-span-3 lg:col-span-1">
            <p class="font-semibold">Build • Experiment • Create.</p>
            <p>STEMMechanics delivers hands-on STEM experiences that inspire curiosity through engineering, coding and creative technology. Based in Cairns, Queensland.</p>
        </div>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">Community</h3>
            </li>
            <li><a href="{{ route('stemcraft.index') }}" class="text-sm hover:text-primary-color">STEMCraft</a></li>
            <li class="mb-3"><a href="https://stemmech.com.au/discord" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Discord</a></li>
            <li><a href="https://www.facebook.com/stemmechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Facebook</a></li>
            <li><a href="https://instagram.com/stemmechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Instagram</a></li>
            <li><a href="https://youtube.com/@STEMMechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">YouTube</a></li>
            <li class="mb-3"><a href="https://linkedin.com/company/stemmechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Linked-In</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">Workshops</h3>
            </li>
            <li class="mb-3"><a href="{{ route('workshop.index') }}" class="text-sm hover:text-primary-color">All Workshops</a></li>
            @foreach($footerWorkshopCategories as $category)
                <li>
                    <a href="{{ route('workshop.index', ['category' => $category->slug]) }}" class="inline-flex items-center justify-center gap-1.5 text-sm hover:text-primary-color md:justify-start">
                        {{ $category->name }}
                    </a>
                </li>
            @endforeach
        </ul>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">STEMMechanics</h3>
            </li>
            <li class="mb-3"><a href="{{ route('tickets.request') }}" class="text-sm hover:text-primary-color">My Tickets</a></li>
            <li><a href="{{ route('about') }}" class="text-sm hover:text-primary-color">About</a></li>
            <li class="mb-3"><a href="{{ route('contact') }}" class="text-sm hover:text-primary-color">Contact Us</a></li>
            <li><a href="{{ route('code-of-conduct') }}" class="text-sm hover:text-primary-color">Code of Conduct</a></li>
            <li><a href="{{ route('terms-conditions') }}" class="text-sm hover:text-primary-color">Terms & Conditions</a></li>
            <li><a href="{{ route('privacy') }}" class="text-sm hover:text-primary-color">Privacy Policy</a></li>
        </ul>
    </section>
    <section class="border-t border-t-gray-600 pt-8 flex justify-between text-xs items-center flex-col sm:flex-row">
        <div class="mb-3 sm:mb-0">@includeSVG('logo.svg', 'width:12rem;margin-top:-0.2rem;color:#DDD')</div>
        @php $commit = config('app.commit'); @endphp
        <div>
            Made with ❤️&nbsp;© {{ date('Y') }} STEMMechanics
            @php($appVersion = (string) config('app.version'))
            • <a href="https://git.stemmechanics.com.au/STEMMechanics/Website" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ preg_match('/^\d/', $appVersion) ? 'v' . $appVersion : $appVersion }}</a>
            @if(!empty($commit))
                ({{ substr($commit, 0, 10) }})
            @endif
        </div>
    </section>
</footer>
