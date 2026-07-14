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
    <section class="grid gap-8 mb-12 md:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]">
        <div class="text-center md:text-left text-sm self-center md:pr-8">STEMMechanics Australia acknowledges the Traditional Owners of Country throughout Australia and the continuing connection to land, cultures and communities. We pay our respect to Aboriginal and Torres Strait Islander cultures; and to Elders both past, present and emerging.</div>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">Community</h3>
            </li>
            <li><a href="{{ route('stemcraft.index') }}" class="text-sm hover:text-primary-color">STEMCraft</a></li>
            <li><a href="https://git.stemmechanics.com.au/" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Gitea</a></li>
            <!-- <li><a href="https://discord.gg/yNzk4x7mpD" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Discord</a></li> -->
            <li><a href="https://www.facebook.com/stemmechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Facebook</a></li>
            <li><a href="https://jenkins.stemmechanics.com.au/" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Jenkins</a></li>
            <li><a href="https://youtube.com/@STEMMechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">YouTube</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">Workshops</h3>
            </li>
            <li><a href="{{ route('workshop.index') }}" class="text-sm hover:text-primary-color">All Workshops</a></li>
            @forelse($footerWorkshopCategories as $category)
                <li>
                    <a href="{{ route('workshop.index', ['category' => $category->slug]) }}" class="inline-flex items-center justify-center gap-1.5 text-sm hover:text-primary-color md:justify-start">
                        {{ $category->name }}
                    </a>
                </li>
            @empty
                <li><a href="{{ route('workshop.index') }}" class="text-sm hover:text-primary-color">All Workshops</a></li>
            @endforelse
        </ul>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">STEMMechanics</h3>
            </li>
            <li><a href="{{ route('about') }}" class="text-sm hover:text-primary-color">About</a></li>
            <li><a href="{{ route('contact') }}" class="text-sm hover:text-primary-color">Contact Us</a></li>
            <li><a href="{{ route('tickets.request') }}" class="text-sm hover:text-primary-color">My Tickets</a></li>
            <li><a href="{{ route('code-of-conduct') }}" class="text-sm hover:text-primary-color">Code of Conduct</a></li>
            <li><a href="{{ route('terms-conditions') }}" class="text-sm hover:text-primary-color">Terms & Conditions</a></li>
            <li><a href="{{ route('privacy') }}" class="text-sm hover:text-primary-color">Privacy Policy</a></li>
        </ul>
    </section>
    <section class="border-t border-t-gray-600 pt-8 flex justify-between text-xs items-center flex-col sm:flex-row">
        <div class="mb-3 sm:mb-0">@includeSVG('logo.svg', 'width:10rem;margin-top:-0.2rem;color:#DDD')</div>
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
