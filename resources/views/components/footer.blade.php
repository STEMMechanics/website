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
    <section class="grid gap-8 mb-12 sm:grid-cols-2 lg:grid-cols-6">
        <div class="text-center lg:text-left text-sm self-center lg:pr-8 flex flex-col gap-3 sm:col-span-2 lg:col-span-2">
            <p class="font-semibold">Build • Experiment • Create.</p>
            <p>Hands-on STEM workshops, coding and creative technology from Cairns, Queensland.</p>
        </div>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">Community</h3>
            </li>
            <li><a href="{{ route('stemcraft.index') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><span class="sm-icon-stemcraft shrink-0" aria-hidden="true"></span>STEMCraft</a></li>
            <li class="mb-3"><a href="https://stemmech.com.au/discord" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start" referrerpolicy="no-referrer"><i class="fa-brands fa-discord w-4 text-center" aria-hidden="true"></i>Discord</a></li>
            <li><a href="https://www.facebook.com/stemmechanics" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start" referrerpolicy="no-referrer"><i class="fa-brands fa-facebook-f w-4 text-center" aria-hidden="true"></i>Facebook</a></li>
            <li><a href="https://instagram.com/stemmechanics" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start" referrerpolicy="no-referrer"><i class="fa-brands fa-instagram w-4 text-center" aria-hidden="true"></i>Instagram</a></li>
            <li><a href="https://youtube.com/@STEMMechanics" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start" referrerpolicy="no-referrer"><i class="fa-brands fa-youtube w-4 text-center" aria-hidden="true"></i>YouTube</a></li>
            <li><a href="https://linkedin.com/company/stemmechanics" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start" referrerpolicy="no-referrer"><i class="fa-brands fa-linkedin-in w-4 text-center" aria-hidden="true"></i>Linked-In</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">Sponsorships</h3>
            </li>
            <li><a href="{{ route('sponsor.index') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-heart w-4 shrink-0 text-center" aria-hidden="true"></i>Sponsor us</a></li>
            <li><a href="{{ route('sponsors.index') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-handshake w-4 shrink-0 text-center" aria-hidden="true"></i>Our sponsors</a></li>
            <li><a href="{{ route('sponsor.manage.request') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-sliders w-4 shrink-0 text-center" aria-hidden="true"></i>Manage</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">Workshops</h3>
            </li>
            <li class="mb-3"><a href="{{ route('workshop.index') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-layer-group w-4 shrink-0 text-center" aria-hidden="true"></i>All workshops</a></li>
            @foreach($footerWorkshopCategories as $category)
                <li>
                    <a href="{{ route('workshop.index', ['category' => $category->slug]) }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start">
                        <i class="{{ $category->iconClass() }} w-4 shrink-0 text-center" aria-hidden="true"></i>
                        {{ $category->name }}
                    </a>
                </li>
            @endforeach
        </ul>
        <ul class="flex flex-col gap-0.5 text-center lg:text-left">
            <li>
                <h3 class="font-bold mb-2">STEMMechanics</h3>
            </li>
            <li class="mb-3"><a href="{{ route('tickets.request') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-ticket w-4 shrink-0 text-center" aria-hidden="true"></i>My tickets</a></li>
            <li><a href="{{ route('about') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-circle-info w-4 shrink-0 text-center" aria-hidden="true"></i>About</a></li>
            <li class="mb-3"><a href="{{ route('contact') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-envelope w-4 shrink-0 text-center" aria-hidden="true"></i>Contact</a></li>
            <li><a href="{{ route('code-of-conduct') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-shield-halved w-4 shrink-0 text-center" aria-hidden="true"></i>Code of Conduct</a></li>
            <li><a href="{{ route('terms-conditions') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-file-contract w-4 shrink-0 text-center" aria-hidden="true"></i>Terms & Conditions</a></li>
            <li><a href="{{ route('privacy') }}" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-lock w-4 shrink-0 text-center" aria-hidden="true"></i>Privacy Policy</a></li>
            <li><a href="https://status.stemmechanics.com.au/" class="inline-flex items-center justify-center gap-2 text-sm hover:text-primary-color lg:justify-start"><i class="fa-solid fa-signal w-4 shrink-0 text-center" aria-hidden="true"></i>Status</a></li>
        </ul>
    </section>
    <section class="border-t border-t-gray-600 pt-8 flex justify-between text-xs items-center flex-col sm:flex-row">
        <div class="mb-3 sm:mb-0">@includeSVG('logo.svg', 'width:12rem;margin-top:-0.2rem;color:#DDD')</div>
        @php $commit = config('app.commit'); @endphp
        <div>
            Made with ❤️&nbsp;© {{ date('Y') }} STEMMechanics
            @php($appVersion = (string) config('app.version'))
            • <a href="https://github.com/stemmechanics/website" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ preg_match('/^\d/', $appVersion) ? 'v' . $appVersion : $appVersion }}</a>
            @if(!empty($commit))
                ({{ substr($commit, 0, 10) }})
            @endif
        </div>
    </section>
</footer>
