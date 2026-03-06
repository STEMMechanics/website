<nav class="shadow bg-white" x-data="{showSearch:false}" x-init="
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'f') {
      event.preventDefault();
      $data.showSearch = true;
    }
  })
">
    @php
        $navUser = auth()->user();
        $hasMyQuotes = $navUser ? $navUser->quotes()->exists() : false;
        $hasMyPayments = $navUser ? $navUser->payments()->exists() : false;
        $hasMyInvoices = $navUser ? $navUser->invoices()->exists() : false;
        $hasMyMedia = $navUser ? $navUser->media()->exists() : false;
        $forumUnreadCount = $navUser ? \App\Models\ForumTopic::unreadCountForUser($navUser) : 0;
    @endphp
    <div
        class="mx-auto max-w-7xl px-2 relative"
        x-data="{
            pageMenuOpen:false,
            userMenuOpen:false,
            forumUnreadCount: {{ $forumUnreadCount }},
            forumSummaryUrl: @js(auth()->check() ? route('forum.notifications.summary') : null),
            forumPollHandle: null,
            async refreshForumNotifications() {
                if (!this.forumSummaryUrl) {
                    return;
                }

                try {
                    const response = await fetch(this.forumSummaryUrl, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    this.forumUnreadCount = Number(payload?.count || 0);
                } catch (_error) {
                }
            },
            init() {
                if (!this.forumSummaryUrl) {
                    return;
                }

                this.refreshForumNotifications();
                this.forumPollHandle = window.setInterval(() => this.refreshForumNotifications(), 30000);
                window.addEventListener('forum-notifications-refresh', () => this.refreshForumNotifications());
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.refreshForumNotifications();
                    }
                });
            },
        }"
    >
        <div class="relative flex h-16 items-center justify-between">
            <div class="ml-4 mr-2 {{ !(auth()->user()?->isAdmin() ?? false) ? 'sm:hidden' : '' }}">
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
                    <div class="flex space-x-2 items-center">
                        {{-- <a href="{{ route('post.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out" aria-current="page">Blog</a>--}}
                        <a href="{{ route('about') }}" class="text-gray-900 hover:text-sky-500 px-1 md:px-3 py-2 text-sm font-medium transition duration-300 ease-in-out">About</a>
                        <a href="{{ route('workshop.index') }}" class="text-gray-900 hover:text-sky-500 px-1 md:px-3 py-2 text-sm font-medium transition duration-300 ease-in-out">Workshops</a>
                        <a href="{{ route('contact') }}" class="text-gray-900 hover:text-sky-500 px-1 md:px-3 py-2 text-sm font-medium transition duration-300 ease-in-out">Contact</a>
                        <button type="button" class="text-gray-900 hover:text-sky-500 text-sm md:pl-1 font-medium transition duration-300 ease-in-out" @click.prevent="showSearch=true">
                            <i class="fa fa-search"></i>
                        </button>
                        <a href="{{ route('stemcraft.index') }}" class="pl-1 md:pl-3" title="STEMCraft"><img class="w-6 h-auto" src="/stemcraft-short-logo.webp" alt="STEMCraft"></a>
                        <a
                                href="{{ route('forum.index') }}"
                                class="text-gray-900 hover:text-sky-500 pl-1 md:pl-4 py-3 text-sm font-medium transition duration-300 ease-in-out relative"
                                title="Discussions"
                                aria-label="Discussions"
                        >
                            <i class="fa-regular fa-comments text-base"></i>
                            <span
                                x-cloak
                                x-show="forumUnreadCount > 0"
                                x-text="forumUnreadCount"
                                class="bg-red-600 text-white text-xxs absolute -right-3 top-1 min-w-4 px-1 text-center rounded-full"
                            ></span>
                        </a>
                    </div>
                </div>
                <div class="mr-3 md:mx-3">
                    <button type="button" @click="userMenuOpen=!userMenuOpen" @keydown.escape="userMenuOpen=false" class="relative flex w-6 text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <i class="fa-regular fa-user-circle text-gray-800 hover:text-sky-500 transition"></i>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="pageMenuOpen" @click.away="pageMenuOpen=false" x-cloak class="fixed left-0 top-0 h-full w-full z-20" role="menu" aria-labelledby="page-menu-button" tabindex="-1">
            <div x-show="pageMenuOpen" @click="pageMenuOpen=false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div x-show="pageMenuOpen" class="relative h-full left-0 top-0 w-96 max-w-full bg-white z-50 shadow-lg p-4 overflow-scroll"
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
                    <i class="fa fa-search w-4 mr-2"></i>Search
                </div>
                {{-- <a href="{{ route('post.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Blog</a>--}}
                <a href="{{ route('about') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-circle-info w-4 mr-2"></i>About</a>
                <a href="{{ route('forum.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-comments w-4 mr-2"></i>Discussions<span x-cloak x-show="forumUnreadCount > 0" x-text="forumUnreadCount" class="bg-red-600 text-white text-xs px-1 rounded ml-2"></span></a>
                <a href="{{ route('workshop.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                <a href="{{ route('stemcraft.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1" title="STEMCraft"><img class="w-5 h-auto mr-2 -ml-1 inline-block" src="/stemcraft-short-logo.webp" alt="STEMCraft">STEMCraft</a>
                <a href="{{ route('contact') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-envelope w-4 mr-2"></i>Contact</a>
                @if(auth()->user()?->isAdmin())
                <div class="sm:hidden border-t border-gray-200 my-2"></div>
                <div class="block text-xs font-semibold text-gray-500 px-2 py-1">People & Content</div>
                <a href="{{ route('admin.user.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-users w-4 mr-2"></i>Users</a>
                <a href="{{ route('admin.subscription.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-envelope-open-text w-4 mr-2"></i>Subscriptions</a>
                <a href="{{ route('admin.custom-page.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-file-lines w-4 mr-2"></i>Custom Pages</a>
                <a href="{{ route('admin.location.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-location-dot w-4 mr-2"></i>Locations</a>
                <a href="{{ route('admin.media.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-photo-film w-4 mr-2"></i>Media</a>

                <div class="block text-xs font-semibold text-gray-500 px-2 py-1 mt-6">Community</div>
                <a href="{{ route('admin.stemcraft.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-cube w-4 mr-2"></i>STEMCraft</a>
                <a href="{{ route('admin.forum.category.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-comments w-4 mr-2"></i>Discussion Categories</a>
                <a href="{{ route('admin.forum.moderation.show') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-shield-halved w-4 mr-2"></i>Discussion Moderation</a>

                <div class="block text-xs font-semibold text-gray-500 px-2 py-1 mt-6">Operations</div>
                <a href="{{ route('admin.workshop.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                <a href="{{ route('admin.ticket.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-ticket w-4 mr-2"></i>Tickets</a>

                <div class="block text-xs font-semibold text-gray-500 px-2 py-1 mt-6">Finance & Planning</div>
                <a href="{{ route('admin.bas.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-calculator w-4 mr-2"></i>BAS Report</a>
                <a href="{{ route('admin.expense.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-receipt w-4 mr-2"></i>Expenses</a>
                <a href="{{ route('admin.invoice.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-file-lines w-4 mr-2"></i>Invoices</a>
                <a href="{{ route('admin.payment.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-money-check-dollar w-4 mr-2"></i>Payments</a>
                <a href="{{ route('admin.quote.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-file-lines w-4 mr-2"></i>Quotes</a>
                <a href="{{ route('admin.pick-list-template.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-list-check w-4 mr-2"></i>Pick List Templates</a>

                <div class="block text-xs font-semibold text-gray-500 px-2 py-1 mt-6">Server & Maintenance</div>
                <a href="{{ route('admin.analytics.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-chart-line w-4 mr-2"></i>Analytics</a>
                <a href="{{ route('admin.server.audit') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-clipboard-list w-4 mr-2"></i>Audit Log</a>
                <a href="{{ route('admin.server.orphans') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-link-slash w-4 mr-2"></i>Orphaned Files</a>
                <a href="{{ route('admin.server.sent-emails') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-envelope-circle-check w-4 mr-2"></i>Sent Emails</a>
                <a href="{{ route('admin.server.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-server w-4 mr-2"></i>Server Info</a>
                <a href="{{ route('admin.server.square-webhooks') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-plug-circle-bolt w-4 mr-2"></i>Square Webhooks</a>
                <a href="{{ route('admin.site_option.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-sliders w-4 mr-2"></i>Site Options</a>
                @endif
            </div>
        </div>


        <div
            x-show="userMenuOpen"
            @click.away="userMenuOpen=false"
            x-cloak>
            <div x-show="userMenuOpen" @click="userMenuOpen=false" class="fixed left-0 w-screen z-20 h-screen bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div
                x-show="userMenuOpen"
                class="absolute w-full right-0 sm:right-5 sm:top-12 z-50 sm:mt-2 sm:w-64 origin-top-right sm:rounded-md bg-white py-3 px-2 shadow-lg border-t border-gray-200 sm:ring-1 ring-black/25 focus:outline-none">
                @if(auth()->guest())
                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-pen-to-square w-4 mr-2"></i>Register</a>
                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-to-bracket w-4 mr-2"></i>Log in</a>
                @else
                <div class="text-lg font-semibold px-4 py-1 text-gray-700">Welcome {{ auth()->user()->firstname ?? strstr(auth()->user()->email, '@', true) }}</div>
                <div class="border-t border-gray-200 my-2"></div>
                <a href="{{ route('account.ticket.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-ticket w-4 mr-2"></i>My Tickets</a>
                @if($hasMyPayments)
                <a href="{{ route('account.payment.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-money-check-dollar w-4 mr-2"></i>My Payments</a>
                @endif
                @if($hasMyQuotes)
                    <a href="{{ route('account.quote.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-file-lines w-4 mr-2"></i>My Quotes</a>
                @endif
                @if($hasMyInvoices)
                    <a href="{{ route('account.invoice.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-file-invoice-dollar w-4 mr-2"></i>My Invoices</a>
                @endif
                @if($hasMyMedia)
                    <a href="{{ route('account.media.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-photo-film w-4 mr-2"></i>My Media</a>
                @endif
                @if(auth()->user()?->hasMinecraftAccess())
                    <a href="{{ route('account.stemcraft.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-cube w-4 mr-2"></i>My STEMCraft</a>
                @endif
                <a href="{{ route('account.show') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-user-pen w-4 mr-2"></i>Account</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-from-bracket w-4 mr-2"></i>Log out</button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-50 flex items-center justify-center" x-cloak x-show="showSearch" x-on:click="showSearch=false" x-on:keydown.escape.window="showSearch=false" x-init="$watch('showSearch', value => {
        if(value) {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const el = document.getElementsByName('q')[0];
                    if (!el) return;
                    el.focus({ preventScroll: true });
                    if (typeof el.select === 'function') el.select();
                    // iOS fallback:
                    if (el.setSelectionRange) el.setSelectionRange(0, el.value.length);
                })
            })
        }
        })">
        <div class="absolute inset-0 backdrop-blur-sm bg-black/40"></div>
        <div class="relative w-full mx-8 max-w-2xl bg-gray-50 p-2 rounded-lg shadow-lg" x-on:click.stop>
            <form action="{{ route('search.index') }}" method="GET">
                <x-ui.search type="text" name="q" label="Search..." />
            </form>
        </div>
    </div>
</nav>
