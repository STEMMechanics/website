<x-layout id="home">
    <x-slot name="title">Home</x-slot>
    <section id="banner" class="bg-center bg-no-repeat bg-cover" style="background-image:linear-gradient(to right, rgba(0,0,0,.7),rgba(0,0,0,.2)),url('/home-hero.webp')">
        <x-container class="py-32 relative">
            <h2 class="text-3xl text-white font-bold mb-4">Join the fun!</h2>
            <p class="text-white max-w-[42rem] mb-3">To keep up with our ever-changing world, it's important to encourage and support a new generation of curious minds who love science, engineering, art, and leadership.</p>
            <p class="text-white max-w-[42rem]">Our fun and exciting workshops can unlock countless opportunities for new ideas and improvements, giving kids the skills and tools they need to solve any problem that comes their way.</p>
            <p class="absolute bottom-3 right-5 bg-black bg-opacity-75 text-white text-xs px-3 py-1 rounded">Steady Hand Game in Ravenshoe</p>
        </x-container>
    </section>
    <section id="news" class="py-12">
        <x-container>
            <h2 class="text-2xl font-bold mb-6">Latest Posts</h2>
            @if($posts->isEmpty())
                <x-none-found item="posts" message="No posts have been published at this time" title="" />
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                    @foreach($posts as $index => $post)
                        <x-panel-post :post="$post" class="{{ $index === 3 ? 'lg:hidden' : '' }}" />
                    @endforeach
                </div>
            @endif
        </x-container>
    </section>
    <section id="skills">
        <x-container class="bg-gray-200 py-32 my-8" inner-class="flex flex-row gap-16">
            <div class="flex-1">
                <div class="h-full bg-no-repeat bg-center bg-cover rounded-lg" style="background-image:url('/home-green-screen.webp')"></div>
            </div>
            <div class="flex-1 text-center">
                <h2 class="text-3xl mb-4 text-left">Build skills while having a great time</h2>
                <p class="mb-6 text-left">To keep up with our ever-changing world, it's important to encourage and support a new generation of curious minds who love science, engineering, art, and leadership.</p>
                <x-ui.button color="success" href="{{ route('workshop.index') }}" class="font-normal">Explore Workshops</x-ui.button>
            </div>
        </x-container>
    </section>
    <section id="events" class="pt-4 pb-8">
        <x-container>
            <h2 class="text-2xl font-bold mb-6">Upcoming workshops</h2>
                @if($workshops->isEmpty())
                    <x-none-found item="workshops" message="No workshops have been scheduled at this time" title="" />
                @else
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                        @foreach($workshops as $index => $workshop)
                            <x-panel-workshop :workshop="$workshop" class="{{ $index === 3 ? 'lg:hidden' : '' }}" />
                        @endforeach
                    </div>
                @endif
        </x-container>
    </section>
    <section id="minecraft" class="bg-center bg-no-repeat bg-cover" style="background-image:url('/home-minecraft.webp')">
        <x-container class="text-white py-32">
            <h2 class="text-3xl mb-4">Play Minecraft with us</h2>
            <p class="mb-4">We invite you to join us on our <a href="https://stemcraft.com.au/" class="link">Minecraft server</a> where you can participate in weekly challenges and mini-games.</p>
            <div class="mb-4 flex gap-4">
                <img src="/home-minecraft-edu.webp" class="h-12" />
                <p>We also run workshops on our minecraft server, both online and offline, where you ca learn to make it rain rabbits, or grow flowers wherever you walk!</p>
            </div>
            <div class="flex justify-center">
                <img src="/home-minecraft-address.webp" class="h-12" />
            </div>
        </x-container>
    </section>
    <section id="support">
        <x-container class="bg-gray-200 py-32 -mb-12" inner-class="flex flex-row gap-16">
            <div class="hidden sm:block flex-1">
                <div class="h-full bg-no-repeat bg-center bg-cover rounded-lg" style="background-image:url('/home-discord.webp')"></div>
            </div>
            <div class="flex-1 text-center">
                <h2 class="text-3xl mb-4 text-left">And the support doesn't stop!</h2>
                <p class="mb-6 text-left">Though the workshop has come to a close, we remain available to assist you via email and Discord with any projects you undertake at home. We are always happy to help.</p>
                <div class="flex gap-3 justify-center">
                    <x-ui.button href="https://discord.gg/yNzk4x7mpD" class="font-normal">Join Discord</x-ui.button>
                    <x-ui.button color="outline" href="{{ route('contact') }}" class="font-normal">Contact Us</x-ui.button>
                </div>
            </div>
        </x-container>
    </section>
</x-layout>
