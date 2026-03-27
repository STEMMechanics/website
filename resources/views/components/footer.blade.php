<footer class="flex flex-col bg-gray-950 text-gray-400 px-4 py-8 sm:px-12 sm:py-16 mt-12">
    <section class="grid gap-8 mb-12 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <div class="text-center md:text-left text-sm self-center md:pr-8">STEMMechanics Australia acknowledges the Traditional Owners of Country throughout Australia and the continuing connection to land, cultures and communities. We pay our respect to Aboriginal and Torres Strait Islander cultures; and to Elders both past, present and emerging.</div>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">Community</h3>
            </li>
            <li><a href="https://git.stemmechanics.com.au/" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Gitea</a></li>
            <!-- <li><a href="https://discord.gg/yNzk4x7mpD" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Discord</a></li> -->
            <li><a href="https://www.facebook.com/stemmechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Facebook</a></li>
            <li><a href="https://jenkins.stemmechanics.com.au/" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">Jenkins</a></li>
            <li><a href="https://youtube.com/@STEMMechanics" class="text-sm hover:text-primary-color" referrerpolicy="no-referrer">YouTube</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">STEMCraft</h3>
            </li>
            <li><a href="{{ route('stemcraft.index') }}" class="text-sm hover:text-primary-color">Overview</a></li>
            <li><a href="{{ route('stemcraft.join') }}" class="text-sm hover:text-primary-color">Join</a></li>
            <li><a href="{{ route('stemcraft.rules') }}" class="text-sm hover:text-primary-color">Rules</a></li>
            <li><a href="{{ route('stemcraft.faqs') }}" class="text-sm hover:text-primary-color">FAQs</a></li>
            <li><a href="{{ route('stemcraft.leaderboards') }}" class="text-sm hover:text-primary-color">Leaderboards</a></li>
            <li><a href="{{ route('stemcraft.punishments') }}" class="text-sm hover:text-primary-color">Punishments</a></li>
            <li><a href="https://map.stemcraft.com.au/" class="text-sm hover:text-primary-color" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Server Map</a></li>
        </ul>
        <ul class="flex flex-col gap-0.5 text-center md:text-left">
            <li>
                <h3 class="font-bold mb-2">STEMMechanics</h3>
            </li>
            <li><a href="{{ route('about') }}" class="text-sm hover:text-primary-color">About</a></li>
            <li><a href="{{ route('contact') }}" class="text-sm hover:text-primary-color">Contact Us</a></li>
            <li><a href="{{ route('forum.index') }}" class="text-sm hover:text-primary-color">Discussions</a></li>
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
