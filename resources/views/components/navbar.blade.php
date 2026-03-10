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
        $hasMyOrders = $navUser ? $navUser->storeOrders()->exists() : false;
        $hasMyQuotes = $navUser ? $navUser->quotes()->exists() : false;
        $hasMyPayments = $navUser ? $navUser->payments()->exists() : false;
        $hasMyInvoices = $navUser ? $navUser->invoices()->exists() : false;
        $hasMyMedia = $navUser ? $navUser->media()->exists() : false;
        $forumUnreadCount = $navUser ? \App\Models\ForumTopic::unreadCountForUser($navUser) : 0;
        $shopCart = app(\App\Services\StoreCartService::class);
        $shopCartPayload = $shopCart->payload([
            'shipping_country' => 'Australia',
            'user' => $navUser,
        ]);
        $shopCartCount = (int) ($shopCartPayload['summary']['item_count'] ?? 0);
    @endphp
    <div
        class="mx-auto max-w-7xl px-2 relative"
        x-data="{
            pageMenuOpen:false,
            userMenuOpen:false,
            publicShopAvailable: {{ $publicShopAvailable ? 'true' : 'false' }},
            cartOpen: {{ (session('store-cart-open') || session('shop-cart-open')) && $publicShopAvailable ? 'true' : 'false' }},
            cartState: @js($shopCartPayload),
            busyCartLineKey: null,
            forumUnreadCount: {{ $forumUnreadCount }},
            forumSummaryUrl: @js(auth()->check() ? route('forum.notifications.summary') : null),
            forumPollHandle: null,
            cartCount() {
                return Number(this.cartState?.summary?.item_count || 0);
            },
            setCartState(cart) {
                if (!cart || typeof cart !== 'object') {
                    return;
                }

                this.cartState = cart;
            },
            formatMoney(value) {
                const amount = Number(value || 0);
                return `$${amount.toFixed(2)}`;
            },
            openCartDrawer() {
                if (!this.publicShopAvailable) {
                    return;
                }

                this.pageMenuOpen = false;
                this.userMenuOpen = false;
                this.cartOpen = true;
            },
            async changeCartQuantity(lineKey, nextQuantity, maxQuantity = 99) {
                if (this.busyCartLineKey) {
                    return;
                }

                this.busyCartLineKey = lineKey;
                try {
                    await window.SM.shopCart.updateQuantity(lineKey, nextQuantity, {
                        max: maxQuantity,
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                    });
                } finally {
                    this.busyCartLineKey = null;
                }
            },
            async removeCartLine(lineKey) {
                if (this.busyCartLineKey) {
                    return;
                }

                this.busyCartLineKey = lineKey;
                try {
                    await window.SM.shopCart.removeLine(lineKey, {
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                    });
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
                if (window.SM?.shopCart) {
                    window.SM.shopCart.configure({
                        showUrl: @js(route('shop.cart.show')),
                        updateUrl: @js(route('shop.cart.update')),
                        removeUrl: @js(route('shop.cart.remove')),
                        initialState: @js($shopCartPayload),
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
        }"
    >
        <div class="relative flex h-16 items-center justify-between">
            <div class="ml-4 mr-2 flex gap-3 items-center">
                <button type="button" @click="pageMenuOpen=!pageMenuOpen" @keydown.escape="pageMenuOpen=false" class="relative flex w-6 text-gray-400 hover:text-white {{ !(auth()->user()?->isAdmin() ?? false) ? 'sm:hidden' : '' }}" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                    <span class="sr-only">Open page menu</span>
                    <i class="fa fa-bars text-gray-800 hover:text-sky-500 transition"></i>
                </button>
                <button type="button" class="text-gray-900 hover:text-sky-500 text-sm md:pl-1 font-medium transition duration-300 ease-in-out md:block hidden" @click.prevent="showSearch=true">
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
                <a href="{{ route('stemcraft.index') }}" class="hidden md:block" title="STEMCraft"><img class="min-w-6 w-6 h-auto" src="/stemcraft-short-logo.webp" alt="STEMCraft"></a>
                <a
                        href="{{ route('forum.index') }}"
                        class="text-gray-900 hover:text-sky-500 text-sm font-medium transition duration-300 ease-in-out relative"
                        title="Discussions"
                        aria-label="Discussions"
                >
                    <i class="fa-regular fa-comments text-base"></i>
                    <span
                        x-cloak
                        x-show="forumUnreadCount > 0"
                        x-text="forumUnreadCount"
                        class="bg-red-600 text-white text-xxs absolute -right-3 -top-2 min-w-4 px-1 text-center rounded-full"
                    ></span>
                </a>
                <button type="button" @click="userMenuOpen=!userMenuOpen" @keydown.escape="userMenuOpen=false" class="relative flex text-gray-400 hover:text-white" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                    <span class="sr-only">Open user menu</span>
                    <i class="fa-regular fa-user-circle text-gray-800 hover:text-sky-500 transition"></i>
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
                @if($publicShopAvailable)
                    <a href="{{ route('shop.index') }}" class="sm:hidden block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bag-shopping w-4 mr-2"></i>Store</a>
                @endif
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
                <a href="{{ route('admin.shop.product.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-bag-shopping w-4 mr-2"></i>Store Products</a>
                <a href="{{ route('admin.shop.settings.edit') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-boxes-stacked w-4 mr-2"></i>Store Settings</a>
                <a href="{{ route('admin.shop.coupon.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-tags w-4 mr-2"></i>Store Coupons</a>
                <a href="{{ route('admin.shop.order.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-box-open w-4 mr-2"></i>Store Orders</a>
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

        @if($publicShopAvailable)
            <div x-show="cartOpen" @click.away="cartOpen=false" x-cloak class="fixed inset-0 z-30" aria-labelledby="cart-drawer-title" role="dialog" aria-modal="true">
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
                    class="absolute right-0 top-0 h-full w-md max-w-full bg-white shadow-2xl p-5 overflow-y-auto"
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
                        <div class="space-y-4">
                            <template x-for="line in cartState.lines" :key="line.key">
                                <div class="flex items-start gap-4 rounded-2xl border border-gray-200 p-3">
                                    <img :src="line.product.image_url" :alt="line.display_title" class="h-20 w-20 rounded-2xl object-cover bg-gray-100" />
                                    <div class="min-w-0 flex-1">
                                        <a :href="line.product.url" class="block font-semibold text-gray-900 hover:text-primary-color" @click="cartOpen=false" x-text="line.product.title"></a>
                                        <div x-show="line.variant_name" class="text-sm text-gray-600" x-text="line.variant_name"></div>
                                        <div class="mt-1 text-sm text-gray-500" x-text="`${line.product.product_type_label} · ${formatMoney(line.unit_price)} each`"></div>
                                        <div class="mt-3 flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-primary-color hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === line.key || line.quantity <= 1"
                                                @click="changeCartQuantity(line.key, Math.max(1, Number(line.quantity || 1) - 1), line.max_quantity)"
                                            >-</button>
                                            <input
                                                type="number"
                                                min="1"
                                                :max="line.max_quantity || 99"
                                                :value="line.quantity"
                                                class="h-9 w-16 rounded-lg border border-gray-300 px-2 text-center text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                                                :disabled="busyCartLineKey === line.key"
                                                @change="changeCartQuantity(line.key, $event.target.value, line.max_quantity)"
                                            />
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-primary-color hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === line.key || Number(line.quantity || 0) >= Number(line.max_quantity || 99)"
                                                @click="changeCartQuantity(line.key, Number(line.quantity || 0) + 1, line.max_quantity)"
                                            >+</button>
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
                                <span>Items</span>
                                <span x-text="formatMoney(cartState.summary.subtotal)"></span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Shipping</span>
                                <span x-text="cartState.summary.can_checkout ? formatMoney(cartState.summary.shipping) : 'Manual quote'"></span>
                            </div>
                            <div x-show="Number(cartState.summary.discount || 0) > 0" class="flex items-center justify-between gap-4 text-emerald-700">
                                <span x-text="cartState.summary.coupon_code ? `Discount (${cartState.summary.coupon_code})` : 'Discount'"></span>
                                <span x-text="`- ${formatMoney(cartState.summary.discount)}`"></span>
                            </div>
                            <div x-show="cartState.summary.shipping_quote.boxed_shipping_required" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                <div class="font-semibold" x-text="cartState.summary.shipping_quote.method"></div>
                                <div class="mt-1" x-text="cartState.summary.shipping_quote.reason"></div>
                            </div>
                            <div class="border-t border-gray-200 pt-3 flex items-center justify-between gap-4">
                                <span class="text-base font-bold text-gray-900">Total</span>
                                <span class="text-right text-xl font-bold text-gray-900" x-text="cartState.summary.total !== null ? formatMoney(cartState.summary.total) : 'Unavailable'"></span>
                            </div>
                        </div>

                        <div class="mt-5">
                            <template x-if="cartState.summary.can_checkout">
                                <x-ui.button type="link" href="{{ route('shop.checkout') }}" class="block" x-on:click="cartOpen=false">Checkout</x-ui.button>
                            </template>
                            <template x-if="!cartState.summary.can_checkout">
                                <button type="button" disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Checkout unavailable</button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @endif


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
                @if($hasMyOrders)
                <a href="{{ route('account.order.index') }}" class="block px-4 py-2 text-sm text-gray-700 rounded transition hover:bg-sky-600 hover:text-white" role="menuitem" tabindex="-1"><i class="fa-solid fa-box-open w-4 mr-2"></i>My Orders</a>
                @endif
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
