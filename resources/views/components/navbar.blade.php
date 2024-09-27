<nav class="shadow bg-white">
    <div class="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8 relative" x-data="{open:false}">
        <div class="relative flex h-16 items-center justify-between">
            <div class="absolute inset-y-0 left-0 flex items-center sm:hidden">
                <!-- Mobile menu button-->
                <button type="button" class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="absolute -inset-0.5"></span>
                    <span class="sr-only">Open main menu</span>
{{--                    <img src="/assets/logo.svg" alt="STEMMechanics" onload="SVGInject(this)" style="color:purple"/>--}}
                </button>
            </div>
            <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
                <div class="flex flex-shrink-0 items-center">
                    <a href="{{ route('index') }}">
                    @includeSVG('logo.svg', 'width:14rem;margin-top:-0.2rem;color:black')
                    </a>
                </div>
            </div>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">
                <div class="hidden sm:ml-6 sm:block mr-4">
                    <div class="flex space-x-2">
                        <a href="{{ route('post.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out transform hover:-translate-y-0.5" aria-current="page">Blog</a>
                        <a href="{{ route('workshop.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out transform hover:-translate-y-0.5">Workshops</a>
                    </div>
                </div>
                <div class="ml-3">
                    <div>
                        <button type="button" @click="open=!open" @keydown.escape="open=false" class="relative flex w-6 text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <svg class="w-6 h-6 text-gray-800 hover:text-sky-500 transition" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h14"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div x-show="open" @click.away="open=false" x-cloak class="absolute w-full right-0 sm:right-5 sm:top-9 z-50 sm:mt-2 sm:w-48 origin-top-right sm:rounded-md bg-white py-3 px-2 shadow-lg border-t sm:ring-1 ring-black ring-opacity-25 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
            <a href="{{ route('post.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Blog</a>
            <a href="{{ route('workshop.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
            <div class="sm:hidden border-t border-gray-200 my-2"></div>
        @if(auth()->guest())
                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-pen-to-square w-4 mr-2"></i>Register</a>
                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-to-bracket w-4 mr-2"></i>Log in</a>
            @else
                @if(auth()->user()?->admin)
                    <div class="block text-xs font-semibold text-gray-500 px-2 py-1">Admin</div>
                    <a href="{{ route('admin.location.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-location-dot w-4 mr-2"></i>Locations</a>
                    <a href="{{ route('admin.media.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-photo-film w-4 mr-2"></i>Media</a>
                    <a href="{{ route('admin.post.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Posts</a>
                    <a href="{{ route('admin.user.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-users w-4 mr-2"></i>Users</a>
                    <a href="{{ route('admin.workshop.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                    <div class="border-t border-gray-200 my-2"></div>
                @endif
                <a href="{{ route('account.show') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-user-pen w-4 mr-2"></i>Account</a>
                <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-from-bracket w-4 mr-2"></i>Log out</a>
            @endif
        </div>
    </div>
</nav>
