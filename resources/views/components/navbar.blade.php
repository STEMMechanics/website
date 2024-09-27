<nav class="shadow bg-white" x-data="{showSearch:false}" x-init="
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'f') {
      event.preventDefault();
      $data.showSearch = true;
    }
  })
">
    <div class="mx-auto max-w-7xl px-2 relative" x-data="{pageMenuOpen:false,userMenuOpen:false}">
        <div class="relative flex h-16 items-center justify-between">
            <div class="ml-4 mr-2 {{ !auth()->user()?->admin ? 'sm:hidden' : '' }}">
                <button type="button" @click="pageMenuOpen=!pageMenuOpen" @keydown.escape="pageMenuOpen=false" class="relative flex w-6 text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                    <span class="sr-only">Open page menu</span>
                    <i class="fa fa-bars text-gray-800 hover:text-sky-500 transition"></i>
                </button>
            </div>
            <div class="flex flex-1 items-center justify-center sm:justify-start ml-2">
                <div class="flex flex-shrink-0 items-center">
                    <a href="{{ route('index') }}">
                    @includeSVG('logo.svg', 'width:14rem;margin-top:-0.2rem;color:black')
                    </a>
                </div>
            </div>
            <div class="flex items-center">
                <div class="hidden sm:ml-6 sm:block mr-4">
                    <div class="flex space-x-2">
                        <a href="{{ route('post.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out" aria-current="page">Blog</a>
                        <a href="{{ route('workshop.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out">Workshops</a>
                        <button type="button" class="text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out" @click.prevent="showSearch=true">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="mx-3">
                    <button type="button" @click="userMenuOpen=!userMenuOpen" @keydown.escape="userMenuOpen=false" class="relative flex w-6 text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <i class="fa-regular fa-user-circle text-gray-800 hover:text-sky-500 transition"></i>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="pageMenuOpen" @click.away="pageMenuOpen=false" x-cloak class="fixed left-0 top-0 h-full w-full z-20" role="menu" aria-labelledby="page-menu-button" tabindex="-1">
            <div x-show="pageMenuOpen" @click="pageMenuOpen=false" class="absolute inset-0 bg-black bg-opacity-40 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>
            <div x-show="pageMenuOpen" class="relative h-full left-0 top-0 w-96 max-w-full bg-white z-50 shadow-lg p-4"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform -translate-x-full"
                 x-transition:enter-end="opacity-100 transform translate-x-0"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 transform translate-x-0"
                 x-transition:leave-end="opacity-0 transform -translate-x-full">
                <div class="flex justify-between mb-4">
                    <div>
                        @includeSVG('logo.svg', 'width:10em;color:black')
                    </div>
                    <button @click="pageMenuOpen=false" class="hover:text-red-500">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1" @click.prevent="showSearch=true">
                    <i class="fa fa-search w-4 mr-2"></i>Search</i>
                </div>
                <a href="{{ route('post.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Blog</a>
                <a href="{{ route('workshop.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                @if(auth()->user()?->admin)
                    <div class="sm:hidden border-t border-gray-200 my-2"></div>
                    <div class="block text-xs font-semibold text-gray-500 px-2 py-1">Admin</div>
                    <a href="{{ route('admin.location.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-location-dot w-4 mr-2"></i>Locations</a>
                    <a href="{{ route('admin.media.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-photo-film w-4 mr-2"></i>Media</a>
                    <a href="{{ route('admin.post.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Posts</a>
                    <a href="{{ route('admin.user.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-users w-4 mr-2"></i>Users</a>
                    <a href="{{ route('admin.workshop.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                @endif
            </div>
        </div>


        <div
            x-show="userMenuOpen"
            @click.away="userMenuOpen=false"
            x-cloak
            >
            <div x-show="userMenuOpen" @click="userMenuOpen=false" class="absolute left-0 w-screen z-20 h-screen bg-black bg-opacity-40 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>
            <div
                x-show="userMenuOpen"
                class="absolute w-full right-0 sm:right-5 sm:top-12 z-50 sm:mt-2 sm:w-64 origin-top-right sm:rounded-md bg-white py-3 px-2 shadow-lg border-t sm:ring-1 ring-black ring-opacity-25 focus:outline-none">
                @if(auth()->guest())
                    <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-pen-to-square w-4 mr-2"></i>Register</a>
                    <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-to-bracket w-4 mr-2"></i>Log in</a>
                @else
                    <div class="text-lg font-semibold px-4 py-1 text-gray-700">Welcome {{ auth()->user()->firstname ?? strstr(auth()->user()->email, '@', true) }}</div>
                    <div class="border-t border-gray-200 my-2"></div>
                    <a href="{{ route('account.show') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-user-pen w-4 mr-2"></i>Account</a>
                    <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-from-bracket w-4 mr-2"></i>Log out</a>
                @endif
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center" x-cloak x-show="showSearch" x-on:click="showSearch=false" x-on:keydown.escape.window="showSearch=false" x-init="$watch('showSearch', value => { if(value) { $nextTick(() => document.getElementsByName('q')[0].focus()) } })">
        <div class="absolute inset-0 backdrop-blur-sm bg-opacity-40 bg-black"></div>
        <div class="relative w-full mx-8 max-w-2xl bg-gray-50 p-2 rounded-lg shadow-lg" x-on:click.stop>
            <form action="{{ route('search.index') }}" method="GET">
                <x-ui.search type="text" name="q" label="Search..." />
            </form>
        </div>
    </div>
</nav>
