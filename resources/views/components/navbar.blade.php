<div x-data="shopNavbarController(window.shopNavbarConfig || {})">
@php
    $isTestSite = request()->getHost() === 'test.stemmechanics.com.au';
    $navClass = $isTestSite
        ? 'relative z-120 isolate border-b border-purple-300 shadow bg-purple-50'
        : 'relative z-120 isolate shadow bg-white';
    $navStyle = $isTestSite
        ? 'background-image: repeating-linear-gradient(45deg, rgba(126, 34, 206, 0.08) 0 10px, rgba(255, 255, 255, 0.05) 10px 20px);'
        : '';
@endphp
<nav class="{{ $navClass }}" @if($navStyle !== '') style="{{ $navStyle }}" @endif>
    @php
        $navUser = auth()->user();
        \App\Models\Quote::expireOpenQuotes();
        $hasMyOrders = $navUser ? $navUser->storeOrders()->exists() : false;
        $hasMyQuotes = $navUser ? $navUser->quotes()->visibleToCustomer()->exists() : false;
        $hasMyPayments = $navUser ? $navUser->payments()->exists() : false;
        $hasMyInvoices = $navUser ? $navUser->invoices()->exists() : false;
        $hasMyMedia = $navUser ? $navUser->media()->exists() : false;
        $forumUnreadCount = $navUser ? \App\Models\ForumTopic::unreadCountForUser($navUser) : 0;
        $canViewMinecraftPage = (bool) ($navUser?->canViewMinecraftPage() ?? false);
        $childAccountsEnabled = \App\Models\SiteOption::booleanValue('users.child-accounts-enabled', true);
        $managedChildAccountCount = 0;
        $hasManagedChildAccounts = false;
        $pendingChildApprovalCount = 0;
        if ($navUser?->isFullAccount()) {
            $managedChildAccountCount = (int) $navUser->children()
                ->whereNull('anonymized_at')
                ->count();
            $hasManagedChildAccounts = $managedChildAccountCount > 0;

            if ($childAccountsEnabled || $hasManagedChildAccounts) {
                $pendingChildApprovalCount = (int) $navUser->children()
                    ->whereNull('anonymized_at')
                    ->withCount([
                        'forumTopics as pending_topic_count' => fn ($query) => $query->where('is_approved', false),
                        'forumPosts as pending_reply_count' => fn ($query) => $query
                            ->where('is_approved', false)
                            ->whereHas('topic', fn ($topicQuery) => $topicQuery->where('is_approved', true)),
                    ])
                    ->get()
                    ->sum(fn ($child) => (int) ($child->pending_topic_count ?? 0) + (int) ($child->pending_reply_count ?? 0));
            }
        }
        $shopCart = app(\App\Services\StoreCartService::class);
        $shopCartPayload = $shopCart->payload([
            'shipping_country' => 'Australia',
            'user' => $navUser,
        ]);
        $shopCartCount = (int) ($shopCartPayload['summary']['item_count'] ?? 0);
        $isAdmin = (bool) ($navUser?->isAdmin() ?? false);
        $manualRefundQueueCount = $isAdmin
            ? \App\Models\SquareRefundOperation::query()
                ->whereIn('status', [
                    \App\Models\SquareRefundOperation::STATUS_FAILED,
                    \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                ])
                ->count()
            : 0;
        $overdueInvoiceCount = $isAdmin ? \App\Models\Invoice::overdueCount() : 0;
        $adminNavSections = $isAdmin ? [
                [
                    'title' => 'Store',
                    'items' => [
                    ['label' => 'Orders', 'route' => route('admin.shop.order.index'), 'icon' => 'fa-solid fa-box-open', 'active' => ['admin.shop.order.*']],
                    ['label' => 'Products', 'route' => route('admin.shop.product.index'), 'icon' => 'fa-solid fa-bag-shopping', 'active' => ['admin.shop.product.*']],
                    ['label' => 'Categories', 'route' => route('admin.shop.category.index'), 'icon' => 'fa-solid fa-tags', 'active' => ['admin.shop.category.*']],
                    ['label' => 'Vouchers', 'route' => route('admin.shop.coupon.index'), 'icon' => 'fa-solid fa-tags', 'active' => ['admin.shop.coupon.*']],
                    ['label' => 'Settings', 'route' => route('admin.shop.settings.edit'), 'icon' => 'fa-solid fa-boxes-stacked', 'active' => ['admin.shop.settings.*']],
                ],
            ],
            [
                'title' => 'People & Content',
                'items' => [
                    ['label' => 'Users', 'route' => route('admin.user.index'), 'icon' => 'fa-solid fa-users', 'active' => ['admin.user.*']],
                    ['label' => 'Subscriptions', 'route' => route('admin.subscription.index'), 'icon' => 'fa-solid fa-envelope-open-text', 'active' => ['admin.subscription.*']],
                    ['label' => 'Media', 'route' => route('admin.media.index'), 'icon' => 'fa-solid fa-photo-film', 'active' => ['admin.media.*']],
                    ['label' => 'Pages', 'route' => route('admin.custom-page.index'), 'icon' => 'fa-regular fa-file-lines', 'active' => ['admin.custom-page.*']],
                    ['label' => 'Locations', 'route' => route('admin.location.index'), 'icon' => 'fa-solid fa-location-dot', 'active' => ['admin.location.*']],
                ],
            ],
                [
                    'title' => 'Workshops & Community',
                    'items' => [
                    ['label' => 'Courses', 'route' => route('admin.course.index'), 'icon' => 'fa-solid fa-chalkboard-user', 'active' => ['admin.course.*']],
                    ['label' => 'Workshops', 'route' => route('admin.workshop.index'), 'icon' => 'fa-solid fa-bullhorn', 'active' => ['admin.workshop.*']],
                    ['label' => 'Tickets', 'route' => route('admin.ticket.index'), 'icon' => 'fa-solid fa-ticket', 'active' => ['admin.ticket.*']],
                    ['label' => 'Discussion Categories', 'route' => route('admin.forum.category.index'), 'icon' => 'fa-regular fa-comments', 'active' => ['admin.forum.category.*']],
                    ['label' => 'Moderation', 'route' => route('admin.forum.moderation.show'), 'icon' => 'fa-solid fa-shield-halved', 'active' => ['admin.forum.moderation.*']],
                    ['label' => 'Pick Lists', 'route' => route('admin.pick-list-template.index'), 'icon' => 'fa-solid fa-list-check', 'active' => ['admin.pick-list-template.*']],
                    ['label' => 'STEMCraft', 'route' => route('admin.stemcraft.index'), 'icon' => 'fa-solid fa-cube', 'active' => ['admin.stemcraft.*']],
                ],
            ],
                [
                    'title' => 'Finance',
                    'items' => [
                    ['label' => 'BAS', 'route' => route('admin.bas.index'), 'icon' => 'fa-solid fa-calculator', 'active' => ['admin.bas.*']],
                    ['label' => 'Expenses', 'route' => route('admin.expense.index'), 'icon' => 'fa-solid fa-receipt', 'active' => ['admin.expense.*']],
                    ['label' => 'Refunds', 'route' => route('admin.payment.refunds'), 'icon' => 'fa-solid fa-coins', 'active' => ['admin.payment.refunds*'], 'badge' => $manualRefundQueueCount],
                    ['label' => 'Invoices', 'route' => route('admin.invoice.index'), 'icon' => 'fa-solid fa-file-invoice-dollar', 'active' => ['admin.invoice.*', 'admin.tax_adjustment.*'], 'badge' => $overdueInvoiceCount],
                    ['label' => 'Payments', 'route' => route('admin.payment.index'), 'icon' => 'fa-solid fa-money-check-dollar', 'active' => ['admin.payment.index', 'admin.payment.create', 'admin.payment.edit', 'admin.payment.receipt', 'admin.payment.square.*', 'admin.payment.refund.manual']],
                    ['label' => 'Quotes', 'route' => route('admin.quote.index'), 'icon' => 'fa-regular fa-file-lines', 'active' => ['admin.quote.*']],
                    ['label' => 'Square Events', 'route' => route('admin.server.square-events'), 'icon' => 'fa-solid fa-plug-circle-bolt', 'active' => ['admin.server.square-events*', 'admin.server.square-webhooks*']],
                ],
            ],
            [
                'title' => 'Site & Server',
                'items' => [
                    ['label' => 'Analytics', 'route' => route('admin.analytics.index'), 'icon' => 'fa-solid fa-chart-line', 'active' => ['admin.analytics.*']],
                    ['label' => 'Audit Log', 'route' => route('admin.server.audit'), 'icon' => 'fa-solid fa-clipboard-list', 'active' => ['admin.server.audit*']],
                    ['label' => 'Sent Emails', 'route' => route('admin.server.sent-emails'), 'icon' => 'fa-solid fa-envelope-circle-check', 'active' => ['admin.server.sent-emails*']],
                    ['label' => 'Orphaned Files', 'route' => route('admin.server.orphans'), 'icon' => 'fa-solid fa-link-slash', 'active' => ['admin.server.orphans*']],
                    ['label' => 'Site Options', 'route' => route('admin.site_option.index'), 'icon' => 'fa-solid fa-sliders', 'active' => ['admin.site_option.*']],
                    ['label' => 'OAuth Clients', 'route' => route('admin.oauth-clients.index'), 'icon' => 'fa-solid fa-key', 'active' => ['admin.oauth-clients.*']],
                    ['label' => 'Backups & Downloads', 'route' => route('admin.server.backups'), 'icon' => 'fa-solid fa-box-archive', 'active' => ['admin.server.backups']],
                    ['label' => 'Server Info', 'route' => route('admin.server.index'), 'icon' => 'fa-solid fa-server', 'active' => ['admin.server.index']],
                ],
            ],
        ] : [];
        $pageMenuAttentionCount = $isAdmin
            ? collect($adminNavSections)
                ->flatMap(fn ($section) => (array) ($section['items'] ?? []))
                ->sum(fn ($item) => max(0, (int) ($item['badge'] ?? 0)))
            : 0;
    @endphp
    <div class="mx-auto max-w-7xl px-2 relative">
        <div class="relative flex h-16 items-center justify-between">
            <div class="ml-4 mr-2 flex gap-3 items-center">
                <button type="button" @click="pageMenuOpen=!pageMenuOpen" @keydown.escape="pageMenuOpen=false" class="relative flex w-6 text-gray-400 hover:text-white {{ $isAdmin ? '' : 'lg:hidden' }}" id="page-menu-button" aria-expanded="false" aria-haspopup="true">
                    <span class="sr-only">Open page menu</span>
                    <i class="fa fa-bars text-gray-800 hover:text-sky-500 transition"></i>
                    @if($pageMenuAttentionCount > 0)
                        <span class="bg-orange-500 text-white text-xxs absolute -right-1 -top-2 min-w-4 px-1 text-center rounded-full">{{ $pageMenuAttentionCount }}</span>
                    @endif
                </button>
                <button type="button" class="text-gray-900 hover:text-sky-500 text-sm md:pl-1 font-medium transition duration-300 ease-in-out lg:block hidden" @click.prevent="openSearchOverlay()">
                    <i class="fa fa-search"></i>
                </button>
            </div>
            <div class="flex flex-1 items-center justify-center sm:justify-start ml-2">
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('index') }}">
                        @includeSVG('logo.svg', 'width:14rem;margin-top:-0.2rem;color:black')
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-6 mr-6">
                {{-- <a href="{{ route('post.index') }}" class="text-gray-900 hover:text-sky-500 px-3 py-2 text-sm font-medium transition duration-300 ease-in-out" aria-current="page">Blog</a>--}}
                <a href="{{ route('about') }}" class="hidden md:block text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out">About</a>
                @if($publicShopAvailable)
                    <a href="{{ route('shop.index') }}" class="hidden md:block text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out">Store</a>
                @endif
                <a href="{{ route('workshop.index') }}" class="hidden md:block text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out">Workshops</a>
                <a href="{{ route('contact') }}" class="hidden md:block text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out">Contact</a>
                <a href="{{ route('stemcraft.index') }}" class="hidden lg:block" title="STEMCraft"><img class="min-w-6 w-6 h-auto" src="{{ asset('stemcraft-short-logo.webp') }}" alt="STEMCraft"></a>
                <a
                        href="{{ route('forum.index') }}"
                        class="hidden lg:block text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out relative"
                        title="Discussions"
                        aria-label="Discussions"
                >
                    <i class="fa-regular fa-comments text-base"></i>
                    <span
                        x-cloak
                        x-show="forumUnreadCount > 0"
                        x-text="forumUnreadCount"
                        class="bg-green-700 text-green-100 text-xxs absolute -right-3 -top-2 min-w-4 px-1 text-center rounded-full"
                    ></span>
                </a>
                <button type="button" @click="userMenuOpen=!userMenuOpen" @keydown.escape="userMenuOpen=false" class="relative flex text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                    <span class="sr-only">Open user menu</span>
                    @if($childAccountsEnabled && $pendingChildApprovalCount > 0)
                        <span class="sr-only">{{ $pendingChildApprovalCount }} child {{ \Illuminate\Support\Str::plural('approval', $pendingChildApprovalCount) }} pending</span>
                    @endif
                    <i class="fa-regular fa-user-circle text-gray-800 hover:text-sky-500 transition"></i>
                    @if($childAccountsEnabled && $pendingChildApprovalCount > 0)
                        <span class="bg-orange-500 text-white text-xxs absolute -right-3 -top-2 min-w-4 px-1 text-center rounded-full">{{ $pendingChildApprovalCount }}</span>
                    @endif
                </button>
                @if($publicShopAvailable)
                    <button
                            type="button"
                            @click.prevent="openCartDrawer()"
                            class="text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out relative"
                            title="Cart"
                            aria-label="Cart"
                    >
                        <i class="fa-solid fa-cart-shopping text-base"></i>
                        <span x-cloak x-show="cartCount() > 0" x-text="cartCount()" class="bg-red-500 text-white text-xxs absolute -right-3 -top-2 min-w-4 px-1 text-center rounded-full"></span>
                    </button>
                @endif
            </div>
        </div>

        <div x-show="pageMenuOpen" @click.away="pageMenuOpen=false" x-cloak class="fixed left-0 top-0 h-full w-full z-180" role="menu" aria-labelledby="page-menu-button" tabindex="-1">
            <div x-show="pageMenuOpen" @click="pageMenuOpen=false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div x-show="pageMenuOpen" class="relative h-full left-0 top-0 w-96 max-w-full bg-white z-190 shadow-lg p-4 pb-18 overflow-y-auto"
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
                <div class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1" @click.prevent="openSearchOverlay()">
                    <i class="fa fa-search w-4 mr-2"></i>Search
                </div>
                {{-- <a href="{{ route('post.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-newspaper w-4 mr-2"></i>Blog</a>--}}
                <a href="{{ route('about') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-circle-info w-4 mr-2"></i>About</a>
                @if($publicShopAvailable)
                    <a href="{{ route('shop.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bag-shopping w-4 mr-2"></i>Store</a>
                @endif
                <a href="{{ route('forum.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-comments w-4 mr-2"></i>Discussions<span x-cloak x-show="forumUnreadCount > 0" x-text="forumUnreadCount" class="ml-2 rounded-full bg-green-700 px-2 py-0.5 text-xs font-semibold text-green-100"></span></a>
                <a href="{{ route('workshop.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bullhorn w-4 mr-2"></i>Workshops</a>
                <a href="{{ route('stemcraft.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1" title="STEMCraft"><img class="w-5 h-auto mr-2 -ml-1 inline-block" src="{{ asset('stemcraft-short-logo.webp') }}" alt="STEMCraft">STEMCraft</a>
                <a href="{{ route('contact') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-envelope w-4 mr-2"></i>Contact</a>
                @if($isAdmin)
                    @foreach($adminNavSections as $section)
                        <div class="border-t border-gray-200 mt-4 pt-4 px-2">
                            <div class="block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $section['title'] }}</div>
                            @isset($section['description'])
                                <div class="mt-1 text-xs text-gray-500">{{ $section['description'] }}</div>
                            @endisset
                        </div>
                        @foreach($section['items'] as $item)
                            @php
                                $isActiveAdminLink = collect($item['active'] ?? [])
                                    ->contains(fn ($pattern) => request()->routeIs($pattern));
                            @endphp
                            <a
                                href="{{ $item['route'] }}"
                                class="mt-1 block px-4 py-2 text-sm rounded transition {{ $isActiveAdminLink ? 'bg-sky-600 text-white' : 'text-gray-700 hover:bg-sky-600 hover:text-white' }}"
                                role="menuitem"
                                tabindex="-1"
                            >
                                <i class="{{ $item['icon'] }} w-4 mr-2"></i>{{ $item['label'] }}
                                @if((int) ($item['badge'] ?? 0) > 0)
                                    <span class="ml-2 rounded-full bg-orange-500 px-2 py-0.5 text-xs font-semibold text-white">{{ (int) $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                @endif
            </div>
        </div>

        <div
            x-show="userMenuOpen"
            @click.away="userMenuOpen=false"
            x-cloak>
            <div x-show="userMenuOpen" @click="userMenuOpen=false" class="fixed left-0 w-screen z-180 h-screen bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div
                x-show="userMenuOpen"
                class="absolute w-full right-0 sm:right-5 sm:top-12 z-190 sm:mt-2 sm:w-64 origin-top-right sm:rounded-md bg-white py-3 px-2 shadow-lg border-t border-gray-200 sm:ring-1 ring-black/25 focus:outline-none">
                @if(auth()->guest())
                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-pen-to-square w-4 mr-2"></i>Register</a>
                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-to-bracket w-4 mr-2"></i>Log in</a>
                @else
                <div class="text-lg font-semibold px-4 py-1 text-gray-700">Welcome {{ auth()->user()->firstname ?? strstr(auth()->user()->email, '@', true) }}</div>
                <div class="border-t border-gray-200 my-2"></div>
                <a href="{{ route('account.show') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-user-pen w-4 mr-2"></i>Account</a>
                <div class="border-t border-gray-200 my-2"></div>
                <a href="{{ route('account.course.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-chalkboard-user w-4 mr-2"></i>Courses</a>
                <a href="{{ route('account.ticket.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-ticket w-4 mr-2"></i>Tickets</a>
                @if($hasMyOrders)
                <a href="{{ route('account.order.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-box-open w-4 mr-2"></i>Orders</a>
                @endif
                @if($hasMyPayments)
                <a href="{{ route('account.payment.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-money-check-dollar w-4 mr-2"></i>Payments</a>
                @endif
                @if($hasMyQuotes)
                    <a href="{{ route('account.quote.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-regular fa-file-lines w-4 mr-2"></i>Quotes</a>
                @endif
                @if($hasMyInvoices)
                    <a href="{{ route('account.invoice.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-file-invoice-dollar w-4 mr-2"></i>Invoices</a>
                @endif
                @if($hasMyMedia)
                    <a href="{{ route('account.media.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-photo-film w-4 mr-2"></i>Media</a>
                @endif
                @if($canViewMinecraftPage)
                    <a href="{{ route('account.stemcraft.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-cube w-4 mr-2"></i>STEMCraft</a>
                @endif
                @if(($childAccountsEnabled || $hasManagedChildAccounts) && $navUser?->isFullAccount())
                    <a href="{{ route('account.children.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-users w-4 mr-2"></i>Child Accounts</a>
                @endif
                <a href="{{ route('account.oauth-apps.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-plug w-4 mr-2"></i>Connected Apps</a>
                @if(($childAccountsEnabled || $hasManagedChildAccounts) && $pendingChildApprovalCount > 0)
                    <a href="{{ route('account.children.approvals') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1">
                        <i class="fa-solid fa-user-shield w-4 mr-2"></i>Child approvals
                        <span class="ml-2 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">{{ $pendingChildApprovalCount }}</span>
                    </a>
                @endif
                <div class="border-t border-gray-200 my-2"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-right-from-bracket w-4 mr-2"></i>Log out</button>
                </form>
                @endif
            </div>
        </div>
    </div>

</nav>

    @if($publicShopAvailable)
        <div x-show="cartOpen" @click.away="cartOpen=false" @keydown.escape.window="if (cartOpen) { cartOpen = false }" x-cloak class="fixed inset-0 z-[260]" aria-labelledby="cart-drawer-title" role="dialog" aria-modal="true">
            <div
                x-show="cartOpen"
                @click="cartOpen=false"
                class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            ></div>
            <div
                x-show="cartOpen"
                class="absolute right-0 top-0 h-full w-md max-w-full bg-white z-[270] shadow-2xl p-5 overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-full"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-full"
            >
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 id="cart-drawer-title" class="text-2xl font-bold text-gray-900">Cart</h2>
                    </div>
                    <button type="button" @click="cartOpen=false" class="text-gray-500 hover:text-red-500">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <div x-show="cartState.is_empty" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center">
                    <div class="text-lg font-semibold text-gray-900">Your cart is empty</div>
                    <p class="mt-2 text-sm text-gray-600">Add a few items from the store and they will appear here.</p>
                    <div class="mt-4">
                        <x-ui.button type="link" href="{{ route('shop.index') }}" x-on:click="cartOpen=false">Browse Store</x-ui.button>
                    </div>
                </div>

                <div x-show="!cartState.is_empty" x-cloak>
                    <div x-show="Array.isArray(cartState.inventory_change_notices) && cartState.inventory_change_notices.length > 0" x-cloak class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <div class="font-semibold text-amber-900">Stock availability changed</div>
                        <div class="mt-1">Your cart has been updated to match current stock.</div>
                        <div class="mt-3 space-y-2">
                            <template x-for="notice in cartState.inventory_change_notices" :key="`${notice.type}-${notice.key}`">
                                <div class="rounded-xl border border-amber-200 bg-white/80 px-3 py-2 text-xs" x-text="notice.message"></div>
                            </template>
                        </div>
                    </div>

                    <div x-show="cartUpdateNotice !== ''" x-cloak class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900" role="status" x-text="cartUpdateNotice"></div>
                    <div x-show="cartUpdateError !== ''" x-cloak class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" x-text="cartUpdateError"></div>

                    <div class="space-y-4">
                        <template x-for="line in cartState.lines" :key="line.key">
                            <div class="flex items-start gap-4 rounded-2xl border border-gray-200 p-3">
                                <img :src="line.product.image_url" :alt="line.display_title" class="h-20 w-20 rounded-2xl object-cover bg-gray-100" />
                                <div class="min-w-0 flex-1">
                                    <a :href="line.product.url" class="block font-semibold text-gray-900 hover:text-primary-color" @click="cartOpen=false" x-text="line.product.title"></a>
                                    <div x-show="line.variant_name" class="text-sm text-gray-600" x-text="line.variant_name"></div>
                                    <div class="mt-1 text-sm text-gray-500" x-text="`${formatMoney(line.unit_price)} each`"></div>
                                    <div class="mt-3">
                                        <div class="shop-catalog-stepper flex items-center gap-2 rounded border border-gray-300 bg-white">
                                            <button
                                                type="button"
                                                class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-r border-r-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === line.key"
                                                @click="changeCartQuantity(line.key, Number(line.quantity || 1) - 1, line.max_quantity, null, line.quantity)"
                                            >-</button>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                autocomplete="off"
                                                :value="line.quantity"
                                                class="shop-catalog-stepper-input h-9 min-w-14 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                                :disabled="busyCartLineKey === line.key"
                                                @input="sanitizeCartQuantityInput($event.target)"
                                                @change="changeCartQuantity(line.key, $event.target.value, line.max_quantity, $event.target, line.quantity)"
                                            />
                                            <button
                                                type="button"
                                                class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-l border-l-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === line.key || Number(line.quantity || 0) >= Number(line.max_quantity || 99)"
                                                @click="changeCartQuantity(line.key, Number(line.quantity || 0) + 1, line.max_quantity, null, line.quantity)"
                                            >+</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900" x-text="formatMoney(line.line_price)"></div>
                                    <button
                                        type="button"
                                        class="mt-2 text-xs text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyCartLineKey === line.key"
                                        @click="removeCartLine(line.key)"
                                    >Remove</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-4 space-y-3 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-4">
                            <span x-text="`Items (${Number(cartState.summary.item_count || 0)})`"></span>
                            <span x-text="formatMoney(cartState.summary.subtotal)"></span>
                        </div>
                        <div x-show="Number(cartState.summary.discount || 0) > 0" class="flex items-center justify-between gap-4 text-emerald-700">
                            <span x-text="cartState.summary.coupon_code ? `Discount (${cartState.summary.coupon_code})` : 'Discount'"></span>
                            <span x-text="`- ${formatMoney(cartState.summary.discount)}`"></span>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-gray-500">
                            <span>GST included</span>
                            <span x-text="formatMoney(cartState.summary.gst)"></span>
                        </div>
                        <div class="border-t border-gray-200 pt-3 flex items-center justify-between gap-4">
                            <span class="text-base font-bold text-gray-900">Sub-Total</span>
                            <span class="text-right text-xl font-bold text-gray-900" x-text="formatMoney(drawerSubtotalAmount())"></span>
                        </div>
                    </div>

                    <div class="mt-5">
                        <template x-if="!cartUpdateLocked()">
                            <x-ui.button href="{{ route('shop.checkout') }}" class="block" x-on:click="cartOpen=false">Checkout</x-ui.button>
                        </template>
                        <template x-if="cartUpdateLocked()">
                            <button type="button" disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Updating cart...</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="fixed inset-0 z-[300] flex items-center justify-center" x-cloak x-show="showSearch" x-on:click="showSearch=false" x-on:keydown.escape.window="showSearch=false" x-init="$watch('showSearch', value => {
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
</div>

<script>
    window.shopNavbarConfig = {
        publicShopAvailable: {{ $publicShopAvailable ? 'true' : 'false' }},
        cartOpen: {{ (session('store-cart-open') || session('shop-cart-open')) && $publicShopAvailable ? 'true' : 'false' }},
        cartState: @js($shopCartPayload),
        forumUnreadCount: {{ $forumUnreadCount }},
        forumSummaryUrl: @js(auth()->check() ? route('forum.notifications.summary') : null),
        routes: {
            show: @js(route('shop.cart.show')),
            update: @js(route('shop.cart.update')),
            remove: @js(route('shop.cart.remove')),
            preferences: @js(route('shop.cart.preferences')),
        },
    };

    function shopNavbarController(config) {
        return {
            showSearch: false,
            pageMenuOpen: false,
            userMenuOpen: false,
            publicShopAvailable: Boolean(config.publicShopAvailable),
            cartOpen: Boolean(config.cartOpen),
            cartState: config.cartState || {},
            busyCartLineKey: null,
            pendingCartUpdate: null,
            cartUpdateError: '',
            cartUpdateErrorTimer: null,
            cartUpdateNotice: '',
            cartUpdateDebounceTimer: null,
            consolidateShipments: Boolean(config.cartState?.summary?.consolidate_shipments ?? config.cartState?.consolidate_shipments ?? false),
            drawerDeliveryUpdateBusy: false,
            drawerDeliveryUpdateError: '',
            forumUnreadCount: Number(config.forumUnreadCount || 0),
            forumSummaryUrl: config.forumSummaryUrl || null,
            forumPollHandle: null,
            scrollLockY: 0,
            keyboardShortcutHandler: null,

            cartCount() {
                return Number(this.cartState?.summary?.item_count || 0);
            },

            setCartState(cart) {
                if (!cart || typeof cart !== 'object') {
                    return;
                }

                this.cartState = cart;
                this.pendingCartUpdate = null;
                this.cartUpdateError = '';
                this.cartUpdateNotice = '';
                this.clearCartUpdateErrorTimer();
                this.clearCartUpdateDebounceTimer();
                this.drawerDeliveryUpdateError = '';
                this.syncDrawerDeliverySelections();
            },

            formatMoney(value) {
                const amount = Number(value || 0);
                return `$${amount.toFixed(2)}`;
            },

            clearCartUpdateErrorTimer() {
                if (this.cartUpdateErrorTimer !== null) {
                    window.clearTimeout(this.cartUpdateErrorTimer);
                    this.cartUpdateErrorTimer = null;
                }
            },

            clearCartUpdateDebounceTimer() {
                if (this.cartUpdateDebounceTimer !== null) {
                    window.clearTimeout(this.cartUpdateDebounceTimer);
                    this.cartUpdateDebounceTimer = null;
                }
            },

            setCartUpdateError(message, durationMs = 12000) {
                this.cartUpdateError = String(message || '').trim();
                this.clearCartUpdateErrorTimer();

                if (this.cartUpdateError === '') {
                    return;
                }

                this.cartUpdateErrorTimer = window.setTimeout(() => {
                    this.cartUpdateError = '';
                    this.cartUpdateErrorTimer = null;
                }, durationMs);
            },

            setCartUpdateNotice(message) {
                this.cartUpdateNotice = String(message || '').trim();
            },

            cartUpdateLocked() {
                return Boolean(this.busyCartLineKey || this.pendingCartUpdate || this.cartUpdateNotice !== '');
            },

            sanitizeCartQuantityInput(input) {
                if (!window.SM?.shopCart) {
                    return;
                }

                window.SM.shopCart.stripNonNumericQuantityInput(input);
            },

            async recoverCartAfterFailedUpdate(message) {
                this.setCartUpdateNotice('Checking cart...');

                await new Promise((resolve) => {
                    window.setTimeout(resolve, 1200);
                });

                try {
                    await window.SM.shopCart.refresh(this.cartState?.shipping_country || 'Australia', {
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                    return true;
                } catch (_error) {
                    this.setCartUpdateNotice('');
                    this.setCartUpdateError(message);
                    return false;
                }
            },

            async submitPendingCartQuantityUpdate() {
                if (!this.pendingCartUpdate || this.busyCartLineKey || !window.SM?.shopCart) {
                    return;
                }

                const pending = this.pendingCartUpdate;
                this.pendingCartUpdate = null;
                this.clearCartUpdateDebounceTimer();
                this.busyCartLineKey = pending.lineKey;

                try {
                    await window.SM.shopCart.updateQuantity(pending.lineKey, pending.quantity, {
                        max: pending.maxQuantity,
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                } catch (error) {
                    await this.recoverCartAfterFailedUpdate(error?.message || 'Unable to update the cart right now.');
                } finally {
                    this.busyCartLineKey = null;
                    if (!this.pendingCartUpdate) {
                        this.clearCartUpdateDebounceTimer();
                    }
                }
            },

            hasOpenOverlay() {
                return Boolean(this.showSearch || this.pageMenuOpen || this.userMenuOpen || this.cartOpen);
            },

            syncScrollLock() {
                const root = document.documentElement;
                const body = document.body;

                if (!root || !body) {
                    return;
                }

                if (this.hasOpenOverlay()) {
                    if (body.dataset.smScrollLocked === 'true') {
                        return;
                    }

                    this.scrollLockY = window.scrollY || window.pageYOffset || 0;
                    body.dataset.smScrollLocked = 'true';
                    root.style.overflow = 'hidden';
                    body.style.position = 'fixed';
                    body.style.top = `-${this.scrollLockY}px`;
                    body.style.left = '0';
                    body.style.right = '0';
                    body.style.width = '100%';
                    body.style.overflow = 'hidden';

                    return;
                }

                if (body.dataset.smScrollLocked !== 'true') {
                    return;
                }

                const topOffset = Number.parseInt(body.style.top || '0', 10);
                const scrollY = Number.isFinite(topOffset) ? Math.abs(topOffset) : this.scrollLockY;

                delete body.dataset.smScrollLocked;
                root.style.overflow = '';
                body.style.position = '';
                body.style.top = '';
                body.style.left = '';
                body.style.right = '';
                body.style.width = '';
                body.style.overflow = '';
                window.scrollTo(0, scrollY);
            },

            drawerSubtotalAmount() {
                const subtotal = Number(this.cartState?.summary?.subtotal || 0);
                const discount = Number(this.cartState?.summary?.discount || 0);

                return Math.max(0, subtotal - discount);
            },

            currentShippingMethodCode() {
                return String(this.cartState?.summary?.shipping_method_code || this.cartState?.shipping_method_code || '').trim();
            },

            currentShippingMethodIsPickup() {
                const currentCode = this.currentShippingMethodCode();
                const methods = Array.isArray(this.cartState?.summary?.shipping_methods)
                    ? this.cartState.summary.shipping_methods
                    : [];
                const currentMethod = methods.find((method) => String(method?.code || '') === currentCode);

                return Boolean(currentMethod?.is_pickup || this.cartState?.summary?.shipping_quote?.is_pickup);
            },

            syncDrawerDeliverySelections() {
                if (!this.showDrawerConsolidationToggle() || this.currentShippingMethodIsPickup()) {
                    this.consolidateShipments = false;
                    return;
                }

                this.consolidateShipments = Boolean(this.cartState?.summary?.consolidate_shipments);
            },

            showDrawerConsolidationToggle() {
                return Boolean(this.cartState?.summary?.shipping_quote?.offers_consolidation) && !this.currentShippingMethodIsPickup();
            },

            showDrawerShipmentBreakdown() {
                const shipments = this.cartState?.summary?.shipping_quote?.shipments;

                return Array.isArray(shipments)
                    && shipments.length > 0
                    && (
                        Boolean(this.cartState?.summary?.shipping_quote?.split_shipments)
                        || Boolean(this.cartState?.summary?.shipping_quote?.consolidate_shipments)
                    );
            },

            drawerShipmentSummary(shipment) {
                if (!shipment || typeof shipment !== 'object') {
                    return '';
                }

                const parts = [String(shipment.title || '').trim()];

                if (String(shipment.dispatch_label || '').trim() !== '') {
                    parts.push(String(shipment.dispatch_label).trim());
                }

                if (String(shipment.delivery_estimate_label || '').trim() !== '') {
                    parts.push(`ETA ${String(shipment.delivery_estimate_label).trim()}`);
                }

                return parts.filter((part) => part !== '').join(' · ');
            },

            drawerConsolidationStatus() {
                const shipmentCount = Number(this.cartState?.summary?.shipping_quote?.shipment_count || 0);

                if (!this.showDrawerConsolidationToggle()) {
                    return '';
                }

                if (Boolean(this.cartState?.summary?.shipping_quote?.consolidate_shipments)) {
                    return 'This order is set to wait so everything ships together once all items are available.';
                }

                if (shipmentCount > 1) {
                    return `Currently split into ${shipmentCount} shipments. The later shipment adds ${this.formatMoney(this.cartState?.summary?.shipping_quote?.second_shipment_charge_amount || 0)} in extra shipping.`;
                }

                return `A later shipment currently adds ${this.formatMoney(this.cartState?.summary?.shipping_quote?.second_shipment_charge_amount || 0)} in extra shipping.`;
            },

            openSearchOverlay() {
                this.pageMenuOpen = false;
                this.userMenuOpen = false;
                this.cartOpen = false;
                this.showSearch = true;
            },

            async updateDrawerDeliveryPreferences() {
                if (this.drawerDeliveryUpdateBusy || !window.SM?.shopCart) {
                    return;
                }

                if (this.currentShippingMethodIsPickup()) {
                    this.consolidateShipments = false;
                }

                this.drawerDeliveryUpdateBusy = true;
                this.drawerDeliveryUpdateError = '';

                try {
                    await window.SM.shopCart.updatePreferences({
                        shippingMethodCode: this.currentShippingMethodCode(),
                        consolidateShipments: this.consolidateShipments,
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                    });
                } catch (error) {
                    this.drawerDeliveryUpdateError = error?.message || 'Unable to update shipping right now.';
                } finally {
                    this.drawerDeliveryUpdateBusy = false;
                }
            },

            openCartDrawer() {
                if (!this.publicShopAvailable) {
                    return;
                }

                this.pageMenuOpen = false;
                this.userMenuOpen = false;
                this.showSearch = false;
                this.cartOpen = true;
            },

            async changeCartQuantity(lineKey, nextQuantity, maxQuantity = 99, input = null, currentQuantity = 1) {
                if (this.busyCartLineKey || !window.SM?.shopCart) {
                    return;
                }

                const update = window.SM.shopCart.prepareQuantityUpdate(nextQuantity, {
                    input,
                    max: maxQuantity,
                    fallbackQuantity: currentQuantity,
                });
                if (!update.shouldSubmit) {
                    if (this.pendingCartUpdate && this.pendingCartUpdate.lineKey === lineKey) {
                        this.pendingCartUpdate = null;
                        this.clearCartUpdateDebounceTimer();
                        this.setCartUpdateNotice('');
                    }
                    return;
                }

                if (this.pendingCartUpdate && this.pendingCartUpdate.lineKey !== lineKey) {
                    return;
                }

                this.cartUpdateError = '';
                this.clearCartUpdateErrorTimer();
                this.pendingCartUpdate = {
                    lineKey,
                    quantity: update.quantity,
                    maxQuantity,
                };
                this.clearCartUpdateDebounceTimer();
                this.cartUpdateDebounceTimer = window.setTimeout(() => {
                    this.submitPendingCartQuantityUpdate();
                }, 500);
            },

            async removeCartLine(lineKey) {
                if (this.busyCartLineKey || !window.SM?.shopCart) {
                    return;
                }

                this.pendingCartUpdate = null;
                this.clearCartUpdateDebounceTimer();
                this.busyCartLineKey = lineKey;
                this.cartUpdateError = '';
                this.clearCartUpdateErrorTimer();
                try {
                    await window.SM.shopCart.removeLine(lineKey, {
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                } catch (error) {
                    await this.recoverCartAfterFailedUpdate(error?.message || 'Unable to update the cart right now.');
                } finally {
                    this.busyCartLineKey = null;
                }
            },

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
                this.$watch('showSearch', () => this.syncScrollLock());
                this.$watch('pageMenuOpen', () => this.syncScrollLock());
                this.$watch('userMenuOpen', () => this.syncScrollLock());
                this.$watch('cartOpen', () => this.syncScrollLock());
                this.syncScrollLock();

                if (window.SM?.shopCart) {
                    window.SM.shopCart.configure({
                        showUrl: config.routes.show,
                        updateUrl: config.routes.update,
                        removeUrl: config.routes.remove,
                        preferencesUrl: config.routes.preferences,
                        initialState: config.cartState,
                    });
                    window.SM.shopCart.subscribe((cart) => this.setCartState(cart));
                }

                window.addEventListener('shop-cart-open', () => {
                    this.openCartDrawer();
                });

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
        };
    }
</script>
