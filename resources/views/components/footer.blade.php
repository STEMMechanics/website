<footer class="flex flex-col bg-gray-950 text-gray-400 px-4 py-8 sm:px-12 sm:py-16 mt-12">
    <section class="flex flex-col sm:flex-row gap-8 sm:gap-4 mb-12">
        <div class="text-center sm:text-left sm:w-2/3 text-sm self-center sm:pr-8">STEMMechanics Australia acknowledges the Traditional Owners of Country throughout Australia and the continuing connection to land, cultures and communities. We pay our respect to Aboriginal and Torres Strait Islander cultures; and to Elders both past, present and emerging.</div>
        <ul class="sm:w-1/3 flex flex-col gap-0.5 text-center sm:text-left">
            <li><h3 class="font-bold mb-2">Community</h3></li>
            <li><a href="https://github.com/stemmechanics" class="text-sm hover:text-primary-color">GitHub</a></li>
            <li><a href="https://discord.gg/yNzk4x7mpD" class="text-sm hover:text-primary-color">Discord</a></li>
            <li><a href="https://www.facebook.com/stemmechanics" class="text-sm hover:text-primary-color">Facebook</a></li>
            <li><a href="https://www.stemcraft.com.au/" class="text-sm hover:text-primary-color">STEMCraft (Minecraft)</a></li>
            <li><a href="https://jenkins.stemmechanics.com.au/" class="text-sm hover:text-primary-color">Jenkins</a></li>
            <li><a href="https://youtube.com/@STEMMechanics" class="text-sm hover:text-primary-color">YouTube</a></li>
        </ul>
        <ul class="sm:w-1/3 flex flex-col gap-0.5 text-center sm:text-left">
            <li><h3 class="font-bold mb-2">STEMMechanics</h3></li>
            <li><a href="{{ route('about') }}" class="text-sm hover:text-primary-color">About</a></li>
            <li><a href="{{ route('contact') }}" class="text-sm hover:text-primary-color">Contact Us</a></li>
            <li><a href="{{ route('code-of-conduct') }}" class="text-sm hover:text-primary-color">Code of Conduct</a></li>
            <li><a href="{{ route('terms-conditions') }}" class="text-sm hover:text-primary-color">Terms & Conditions</a></li>
            <li><a href="{{ route('privacy') }}" class="text-sm hover:text-primary-color">Privacy Policy</a></li>
        </ul>
    </section>
    <section class="border-t border-t-gray-600 pt-8 flex justify-between text-xs items-center flex-col sm:flex-row">
        <div class="mb-3 sm:mb-0">@includeSVG('logo.svg', 'width:10rem;margin-top:-0.2rem;color:#DDD')</div>
        <div>Made with ❤️&nbsp;© {{ date('Y') }} STEMMechanics</div>
    </section>
</footer>
