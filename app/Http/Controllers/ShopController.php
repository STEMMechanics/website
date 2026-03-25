<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\User;
use App\Services\StoreCartService;
use App\Services\AccountCreditService;
use App\Services\StoreOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopController extends Controller
{
    private const CHECKOUT_SESSION_KEY = 'store.checkout.flow';

    private const LEGACY_CHECKOUT_SESSION_KEY = 'shop.checkout.flow';

    private const ORDER_DOCUMENT_ACCESS_SESSION_KEY = 'store.order.document-access-tokens';

    private const AUSTRALIA_SHIPPING_COUNTRY = 'Australia';

    private const AUSTRALIAN_STATES = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'NT' => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA' => 'Western Australia',
    ];

    public function __construct(private readonly AccountCreditService $accountCredit)
    {
    }

    public function index(Request $request, StoreCartService $cart): View
    {
        $selectedView = trim((string) $request->query('view', 'grid'));
        if (! in_array($selectedView, ['grid', 'list'], true)) {
            $selectedView = 'grid';
        }

        $selectedSort = trim((string) $request->query('sort', 'relevance'));
        if (! in_array($selectedSort, ['relevance', 'price_low', 'price_high', 'title_asc', 'title_desc'], true)) {
            $selectedSort = 'relevance';
        }

        $query = Product::query()
            ->active()
            ->with([
                'hero',
                'variants' => fn ($builder) => $builder->active()->orderBy('sort_order')->orderBy('name'),
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('category')) {
            $query->where('category', trim((string) $request->query('category')));
        }

        $this->applyIndexSort($query, $selectedSort);

        return view('shop.index', [
            'products' => $query->paginate(12)->onEachSide(1),
            'categories' => Product::query()
                ->active()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'selectedCategory' => trim((string) $request->query('category')),
            'selectedView' => $selectedView,
            'selectedSort' => $selectedSort,
            'cartPayload' => $cart->payload([
                'shipping_country' => 'Australia',
                'user' => $request->user(),
            ]),
        ]);
    }

    public function show(Request $request, Product $product, StoreCartService $cart): View
    {
        abort_unless($product->isActive(), 404);

        return view('shop.show', [
            'product' => $product->load([
                'hero',
                'galleryMedia',
                'variants' => fn ($builder) => $builder->active()->orderBy('sort_order')->orderBy('name'),
            ]),
            'cartPayload' => $cart->payload([
                'shipping_country' => 'Australia',
                'user' => $request->user(),
            ]),
        ]);
    }

    public function cart(Request $request, StoreCartService $cart): View|JsonResponse
    {
        $shippingCountry = self::AUSTRALIA_SHIPPING_COUNTRY;
        $summary = $cart->summary([
            'shipping_country' => $shippingCountry,
            'shipping_method_code' => $cart->shippingMethodCode(),
            'consolidate_shipments' => $cart->consolidateShipments(),
            'user' => $request->user(),
        ]);
        $cartPayload = $cart->payload([
            'shipping_country' => $shippingCountry,
            'user' => $request->user(),
        ]);

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'cart' => $cartPayload,
            ]);
        }

        return view('shop.cart', [
            'lines' => $cart->lines(),
            'summary' => $summary,
            'shippingCountry' => $shippingCountry,
            'couponCode' => $summary['coupon_code'] ?? null,
            'cartPayload' => $cartPayload,
        ]);
    }

    public function addToCart(Request $request, Product $product, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        abort_unless($product->isActive(), 404);

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'product_variant_id' => ['nullable', 'integer'],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $product->load(['variants' => fn ($builder) => $builder->active()->orderBy('sort_order')->orderBy('name')]);
        $variant = $this->resolveVariantSelection($product, $validated['product_variant_id'] ?? null);

        if (! $product->isSelectionPurchasable($variant)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'That item is currently out of stock.',
            ]);
        }

        $resolvedQuantity = (int) ($validated['quantity'] ?? 1);

        $cart->add($product, $variant, $resolvedQuantity);

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Added '.$product->displayTitle($variant).' to your cart.',
                'cart' => $cart->payload([
                    'shipping_country' => trim((string) $request->input('shipping_country', 'Australia')) ?: 'Australia',
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Added '.$product->displayTitle($variant).' to your cart.');
        session()->flash('message-title', 'Cart updated');
        session()->flash('message-type', 'success');

        return redirect()->to($this->cartReturnUrl($request, route('shop.cart.show')));
    }

    public function updateCartPreferences(Request $request, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shipping_method_code' => ['nullable', 'string', 'max:40'],
            'consolidate_shipments' => ['nullable', 'boolean'],
            'shipping_country' => $this->australianShippingCountryRules(),
        ]);

        $shippingCountry = self::AUSTRALIA_SHIPPING_COUNTRY;

        $summary = $cart->summary([
            'shipping_country' => $shippingCountry,
            'shipping_method_code' => $validated['shipping_method_code'] ?? null,
            'consolidate_shipments' => $request->boolean('consolidate_shipments'),
            'user' => $request->user(),
        ]);

        $availableShippingCodes = collect($summary['shipping_methods'] ?? [])
            ->pluck('code')
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->values()
            ->all();

        $requestedShippingMethodCode = trim((string) ($validated['shipping_method_code'] ?? ''));
        if ($requestedShippingMethodCode !== '' && ! in_array($requestedShippingMethodCode, $availableShippingCodes, true)) {
            if ($this->shouldReturnJson($request)) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'shipping_method_code' => ['Please choose a valid shipping option.'],
                    ],
                ], 422);
            }

            return redirect()->route('shop.cart.show')->withErrors([
                'shipping_method_code' => 'Please choose a valid shipping option.',
            ]);
        }

        $cart->updatePreferences(
            $summary['shipping_method_code'] ?? null,
            (bool) ($summary['shipping_quote']['offers_consolidation'] ?? false) && $request->boolean('consolidate_shipments'),
        );

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Delivery options updated.',
                'cart' => $cart->payload([
                    'shipping_country' => $shippingCountry,
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Delivery options updated.');
        session()->flash('message-title', 'Cart updated');
        session()->flash('message-type', 'success');

        return redirect()->route('shop.cart.show', [
            'shipping_country' => $shippingCountry,
        ]);
    }

    public function updateCart(Request $request, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
        ]);

        $cart->update($validated['quantities']);

        if ($this->shouldReturnJson($request)) {
            $shippingCountry = trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia';

            return response()->json([
                'success' => true,
                'message' => 'Your cart has been updated.',
                'cart' => $cart->payload([
                    'shipping_country' => $shippingCountry,
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Your cart has been updated.');
        session()->flash('message-title', 'Cart updated');
        session()->flash('message-type', 'success');

        return redirect()->route('shop.cart.show', [
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia',
        ]);
    }

    public function removeFromCart(Request $request, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'line_key' => ['required', 'string', 'max:60'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
            'return_to' => ['nullable', 'string', 'max:2000'],
            'cart_context' => ['nullable', 'string', 'max:40'],
        ]);

        $cart->removeLine((string) $validated['line_key']);

        if ($this->shouldReturnJson($request)) {
            $shippingCountry = trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia';

            return response()->json([
                'success' => true,
                'message' => 'Removed that item from your cart.',
                'cart' => $cart->payload([
                    'shipping_country' => $shippingCountry,
                    'user' => $request->user(),
                ]),
            ]);
        }

        if (trim((string) ($validated['cart_context'] ?? '')) === 'drawer') {
            session()->flash('store-cart-open', true);
        }

        session()->flash('message', 'Removed that item from your cart.');
        session()->flash('message-title', 'Cart updated');
        session()->flash('message-type', 'success');

        return redirect()->to($this->cartReturnUrl($request, route('shop.cart.show', [
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia',
        ])));
    }

    public function applyCoupon(Request $request, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ]);
        $shippingCountry = trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia';
        $redirectUrl = $this->cartReturnUrl($request, route('shop.cart.show', [
            'shipping_country' => $shippingCountry,
        ]));

        $couponCode = trim((string) ($validated['coupon_code'] ?? ''));
        if ($couponCode === '') {
            $cart->clearCoupon();
            if ($this->shouldReturnJson($request)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Voucher removed.',
                    'cart' => $cart->payload([
                        'shipping_country' => $shippingCountry,
                        'user' => $request->user(),
                    ]),
                ]);
            }
            session()->flash('message', 'Voucher removed.');
            session()->flash('message-title', 'Voucher updated');
            session()->flash('message-type', 'success');

            return redirect()->to($redirectUrl);
        }

        $cart->applyCoupon($couponCode);
        $summary = $cart->summary([
            'shipping_country' => $shippingCountry,
            'user' => $request->user(),
        ]);

        if ($summary['coupon_error']) {
            $cart->clearCoupon();
            if ($this->shouldReturnJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => $summary['coupon_error'],
                    'errors' => [
                        'coupon_code' => [$summary['coupon_error']],
                    ],
                    'cart' => $cart->payload([
                        'shipping_country' => $shippingCountry,
                        'user' => $request->user(),
                    ]),
                ], 422);
            }
            session()->flash('message', $summary['coupon_error']);
            session()->flash('message-title', 'Voucher not applied');
            session()->flash('message-type', 'danger');
            return redirect()->to($redirectUrl)->withInput([
                'coupon_code' => $couponCode,
            ]);
        }

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Voucher applied successfully.',
                'cart' => $cart->payload([
                    'shipping_country' => $shippingCountry,
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Voucher applied successfully.');
        session()->flash('message-title', 'Voucher applied');
        session()->flash('message-type', 'success');

        return redirect()->to($redirectUrl);
    }

    public function removeCoupon(Request $request, StoreCartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shipping_country' => ['nullable', 'string', 'max:120'],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ]);
        $shippingCountry = trim((string) ($validated['shipping_country'] ?? 'Australia')) ?: 'Australia';
        $cart->clearCoupon();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Voucher removed.',
                'cart' => $cart->payload([
                    'shipping_country' => $shippingCountry,
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Voucher removed.');
        session()->flash('message-title', 'Voucher updated');
        session()->flash('message-type', 'success');

        return redirect()->to($this->cartReturnUrl($request, route('shop.cart.show', [
            'shipping_country' => $shippingCountry,
        ])));
    }

    public function checkout(Request $request, StoreCartService $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $user = $request->user();
        $storedCustomer = $this->checkoutSessionCustomer();
        $userName = $user instanceof User ? trim((string) $user->getName()) : '';
        $userEmail = $user instanceof User ? trim((string) $user->email) : '';
        $userPhone = $user instanceof User ? trim((string) $user->phone) : '';
        $userCompany = $user instanceof User ? trim((string) $user->company) : '';
        $userShippingCountry = $user instanceof User ? trim((string) $user->shipping_country) : '';
        $userShippingAddress = $user instanceof User ? trim((string) $user->shipping_address) : '';
        $userShippingAddress2 = $user instanceof User ? trim((string) $user->shipping_address2) : '';
        $userShippingCity = $user instanceof User ? trim((string) $user->shipping_city) : '';
        $userShippingState = $user instanceof User ? trim((string) $user->shipping_state) : '';
        $userShippingPostcode = $user instanceof User ? trim((string) $user->shipping_postcode) : '';

        if ($userShippingCountry !== '' && strcasecmp($userShippingCountry, self::AUSTRALIA_SHIPPING_COUNTRY) !== 0) {
            $userShippingAddress = '';
            $userShippingAddress2 = '';
            $userShippingCity = '';
            $userShippingState = '';
            $userShippingPostcode = '';
        }

        $shippingCountry = self::AUSTRALIA_SHIPPING_COUNTRY;
        $billingEmail = trim((string) old('billing_email', $storedCustomer['billing_email'] ?? $userEmail));
        $shippingMethodCode = trim((string) old('shipping_method_code', $storedCustomer['shipping_method_code'] ?? $cart->shippingMethodCode() ?? ''));
        $consolidateShipments = (bool) old('consolidate_shipments', $storedCustomer['consolidate_shipments'] ?? $cart->consolidateShipments());
        $lines = $cart->lines();
        $summary = $cart->summary([
            'shipping_country' => $shippingCountry,
            'shipping_method_code' => $shippingMethodCode,
            'consolidate_shipments' => $consolidateShipments,
            'user' => $user,
            'billing_email' => $billingEmail,
        ]);
        $accountCreditAvailable = $this->accountCredit->availableCreditForUser($user);
        $accountCreditApplied = min($accountCreditAvailable, (float) ($summary['total'] ?? 0));
        $amountDueAfterCredit = max(0, round((float) ($summary['total'] ?? 0) - $accountCreditApplied, 2));

        return view('shop.checkout', [
            'lines' => $lines,
            'summary' => $summary,
            'accountCreditAvailable' => $accountCreditAvailable,
            'accountCreditApplied' => $accountCreditApplied,
            'amountDueAfterCredit' => $amountDueAfterCredit,
            'couponCode' => $summary['coupon_code'] ?? null,
            'cartPayload' => $cart->payload([
                'shipping_country' => $shippingCountry,
                'shipping_method_code' => $summary['shipping_method_code'] ?? $shippingMethodCode,
                'consolidate_shipments' => $summary['consolidate_shipments'] ?? $consolidateShipments,
                'user' => $user,
                'billing_email' => $billingEmail,
            ]),
            'inventoryChangeNotices' => $cart->inventoryChangeNotices(),
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
            'prefill' => [
                'billing_name' => trim((string) old('billing_name', $storedCustomer['billing_name'] ?? $userName)),
                'billing_email' => $billingEmail,
                'billing_phone' => trim((string) old('billing_phone', $storedCustomer['billing_phone'] ?? $userPhone)),
                'billing_company' => trim((string) old('billing_company', $storedCustomer['billing_company'] ?? $userCompany)),
                'shipping_name' => trim((string) old('shipping_name', $storedCustomer['shipping_name'] ?? $userName)),
                'shipping_phone' => trim((string) old('shipping_phone', $storedCustomer['shipping_phone'] ?? $userPhone)),
                'shipping_address' => trim((string) old('shipping_address', $storedCustomer['shipping_address'] ?? $userShippingAddress)),
                'shipping_address2' => trim((string) old('shipping_address2', $storedCustomer['shipping_address2'] ?? $userShippingAddress2)),
                'shipping_city' => trim((string) old('shipping_city', $storedCustomer['shipping_city'] ?? $userShippingCity)),
                'shipping_state' => $this->normalizeAustralianState(old('shipping_state', $storedCustomer['shipping_state'] ?? $userShippingState)),
                'shipping_postcode' => trim((string) old('shipping_postcode', $storedCustomer['shipping_postcode'] ?? $userShippingPostcode)),
                'shipping_country' => $shippingCountry,
                'shipping_method_code' => trim((string) old('shipping_method_code', $storedCustomer['shipping_method_code'] ?? ($summary['shipping_method_code'] ?? $shippingMethodCode))),
                'consolidate_shipments' => (bool) old('consolidate_shipments', $storedCustomer['consolidate_shipments'] ?? ($summary['consolidate_shipments'] ?? $consolidateShipments)),
                'notes' => trim((string) old('notes', $storedCustomer['notes'] ?? '')),
            ],
            'australianStates' => self::AUSTRALIAN_STATES,
        ]);
    }

    public function quoteRequested(Request $request): View
    {
        return view('shop.quote-requested', [
            'quoteNumber' => trim((string) $request->query('quote', '')),
        ]);
    }

    public function placeOrder(Request $request, StoreCartService $cart, StoreOrderService $orders): RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $requestedShippingCountry = trim((string) $request->input('shipping_country', self::AUSTRALIA_SHIPPING_COUNTRY)) ?: self::AUSTRALIA_SHIPPING_COUNTRY;
        $requestedShippingMethodCode = trim((string) $request->input('shipping_method_code', $cart->shippingMethodCode() ?? ''));
        $requestedConsolidateShipments = $request->boolean('consolidate_shipments', $cart->consolidateShipments());
        $billingEmail = trim((string) $request->input('billing_email', ''));
        $checkoutPreview = $cart->summary([
            'shipping_country' => $requestedShippingCountry,
            'shipping_method_code' => $requestedShippingMethodCode,
            'consolidate_shipments' => $requestedConsolidateShipments,
            'user' => $request->user(),
            'billing_email' => $billingEmail !== '' ? $billingEmail : null,
            'coupon_code' => $cart->couponCode(),
        ]);
        $shippingRequired = (bool) ($checkoutPreview['contains_physical'] ?? false)
            && ! (bool) ($checkoutPreview['shipping_quote']['is_pickup'] ?? false);
        $normalizedShippingState = $this->normalizeAustralianState($request->input('shipping_state'));

        if ($normalizedShippingState !== '') {
            $request->merge([
                'shipping_state' => $normalizedShippingState,
            ]);
        }

        $validated = $request->validate([
            'billing_name' => ['required', 'string', 'max:120'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:60'],
            'billing_company' => ['nullable', 'string', 'max:120'],
            'shipping_method_code' => ['nullable', 'string', 'max:40'],
            'consolidate_shipments' => ['nullable', 'boolean'],
            'shipping_name' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'shipping_phone' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:60'],
            'shipping_address' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:255'],
            'shipping_address2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'shipping_state' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', Rule::in(array_keys(self::AUSTRALIAN_STATES))],
            'shipping_postcode' => [Rule::requiredIf($shippingRequired), 'nullable', 'regex:/^\d{4}$/'],
            'shipping_country' => $this->australianShippingCountryRules($shippingRequired),
            'notes' => ['nullable', 'string'],
        ]);

        $availableShippingCodes = collect($checkoutPreview['shipping_methods'] ?? [])
            ->pluck('code')
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->values()
            ->all();

        if ($requestedShippingMethodCode !== '' && ! in_array($requestedShippingMethodCode, $availableShippingCodes, true)) {
            return redirect()->back()->withErrors([
                'shipping_method_code' => 'Please choose a valid shipping option.',
            ])->withInput();
        }

        $validated['shipping_method_code'] = $checkoutPreview['shipping_method_code'] ?? null;
        $validated['consolidate_shipments'] = (bool) ($checkoutPreview['shipping_quote']['offers_consolidation'] ?? false)
            && $request->boolean('consolidate_shipments');
        $validated['shipping_country'] = self::AUSTRALIA_SHIPPING_COUNTRY;

        if (! $shippingRequired) {
            $validated['shipping_name'] = $validated['billing_name'];
            $validated['shipping_phone'] = $validated['billing_phone'];
            $validated['shipping_address'] = '';
            $validated['shipping_address2'] = '';
            $validated['shipping_city'] = '';
            $validated['shipping_state'] = '';
            $validated['shipping_postcode'] = '';
        }

        $validated['coupon_code'] = $cart->couponCode();

        $summary = $cart->summary([
            'shipping_country' => $validated['shipping_country'] ?? 'Australia',
            'shipping_method_code' => $validated['shipping_method_code'] ?? null,
            'consolidate_shipments' => $validated['consolidate_shipments'] ?? false,
            'user' => $request->user(),
            'billing_email' => $validated['billing_email'],
            'coupon_code' => $validated['coupon_code'],
        ]);
        $accountCreditAvailable = $this->accountCredit->availableCreditForUser($request->user());
        $accountCreditApplied = min($accountCreditAvailable, (float) ($summary['total'] ?? 0));
        $amountDueAfterCredit = max(0, round((float) ($summary['total'] ?? 0) - $accountCreditApplied, 2));

        if ($summary['coupon_error']) {
            return redirect()->back()->withErrors([
                'coupon_code' => $summary['coupon_error'],
            ])->withInput();
        }

        if ((bool) ($summary['shipping_quote']['requires_manual_quote'] ?? false)) {
            $orders->createQuoteRequestFromCart($lines, $validated, $request->user());
            $cart->clear();
            $this->clearCheckoutSession();

            session()->flash('message', 'Your request for a shipping quote has been submitted. We will get back to you shortly.');
            session()->flash('message-title', 'Quote requested');
            session()->flash('message-type', 'success');

            return redirect()->route('shop.index');
        }

        if (! ($summary['can_checkout'] ?? true)) {
            return redirect()->back()->withErrors([
                'shipping_country' => (string) ($summary['shipping_quote']['reason'] ?? 'Shipping could not be calculated for this order.'),
            ])->withInput();
        }

        $this->putCheckoutSessionCustomer($validated);

        if ((float) ($summary['total'] ?? 0) <= 0.0001) {
            $order = $orders->createFromCart($lines, $validated, $request->user());
            $cart->clear();
            $this->clearCheckoutSession();
            $this->rememberGuestOrderDocumentAccess($order, $request);

            session()->flash('message', 'Your order is complete.');
            session()->flash('message-title', 'Order created');
            session()->flash('message-type', 'success');

            return $request->user()
                ? redirect()->route('account.order.show', $order)
                : redirect()->route('shop.order.tracking', ['accessToken' => $order->access_token]);
        }

        if ($amountDueAfterCredit > 0.0001 && ! (bool) config('services.square.enabled')) {
            return redirect()->route('shop.checkout')
                ->withErrors(['source_id' => 'Online card payments are currently unavailable.'])
                ->withInput()
                ->with('shop_checkout_step', 'payment');
        }

        $payment = $amountDueAfterCredit > 0.0001
            ? $request->validate([
                'source_id' => ['required', 'string', 'max:255'],
            ], [
                'source_id.required' => 'Card details are required.',
            ])
            : ['source_id' => null];

        try {
            $order = $orders->createAndChargeFromCart($lines, $validated, $payment['source_id'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('shop.checkout')
                ->withErrors($e->errors())
                ->withInput()
                ->with('shop_checkout_step', 'payment');
        } catch (\Throwable $e) {
            report($e);

            session()->flash('message', 'Unable to process payment right now.');
            session()->flash('message-title', 'Payment failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('shop.checkout')
                ->withInput()
                ->with('shop_checkout_step', 'payment');
        }

        $cart->clear();
        $this->clearCheckoutSession();
        $this->rememberGuestOrderDocumentAccess($order, $request);

        session()->flash('message', 'Payment completed successfully.');
        session()->flash('message-title', 'Payment success');
        session()->flash('message-type', 'success');

        return $request->user()
            ? redirect()->route('account.order.show', $order)
            : redirect()->route('shop.order.tracking', ['accessToken' => $order->access_token]);
    }

    public function checkoutPayment(Request $request, StoreCartService $cart): RedirectResponse
    {
        if ($cart->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $customer = $this->checkoutSessionCustomer();
        if ($customer === []) {
            return redirect()->route('shop.checkout');
        }

        return redirect()->route('shop.checkout')
            ->with('shop_checkout_step', 'payment');
    }

    public function processCheckoutPayment(Request $request, StoreCartService $cart, StoreOrderService $orders): RedirectResponse
    {
        $customer = $this->checkoutSessionCustomer();
        if ($customer === []) {
            return redirect()->route('shop.checkout');
        }

        if (! $request->filled('source_id')) {
            return redirect()->route('shop.checkout')
                ->withErrors(['source_id' => 'Card details are required.'])
                ->with('shop_checkout_step', 'payment');
        }

        $request->merge($customer);

        return $this->placeOrder($request, $cart, $orders);
    }

    private function resolveVariantSelection(Product $product, mixed $variantId): ?ProductVariant
    {
        if (! $product->hasVariants()) {
            return null;
        }

        if ($variantId === null || (string) $variantId === '') {
            return null;
        }

        $selectedVariant = $product->variantById((int) $variantId);
        if (! $selectedVariant instanceof ProductVariant) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Please choose an option before adding this item to your cart.',
            ]);
        }

        return $selectedVariant;
    }

    private function applyIndexSort($query, string $selectedSort): void
    {
        switch ($selectedSort) {
            case 'price_low':
                $query
                    ->orderByRaw("COALESCE((select min(case when products.product_type = 'digital' then coalesce(product_variants.price, products.price) else products.price end) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1), products.price) asc")
                    ->orderBy('title');

                return;

            case 'price_high':
                $query
                    ->orderByRaw("COALESCE((select max(case when products.product_type = 'digital' then coalesce(product_variants.price, products.price) else products.price end) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1), products.price) desc")
                    ->orderBy('title');

                return;

            case 'title_asc':
                $query->orderBy('title');

                return;

            case 'title_desc':
                $query->orderByDesc('title');

                return;

            case 'relevance':
            default:
                $query
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order')
                    ->orderBy('title');
        }
    }

    private function cartReturnUrl(Request $request, string $fallback): string
    {
        $returnTo = trim((string) $request->input('return_to', ''));
        if ($this->isSafeReturnUrl($returnTo)) {
            return $returnTo;
        }

        $referer = trim((string) $request->headers->get('referer'));
        if ($this->isSafeReturnUrl($referer)) {
            return $referer;
        }

        return $fallback;
    }

    private function isSafeReturnUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $allowedRoots = collect([
            rtrim(url('/'), '/'),
            rtrim((string) config('app.url', ''), '/'),
        ])->filter()->unique();

        return $allowedRoots->contains(function (string $root) use ($url): bool {
            return $url === $root || str_starts_with($url, $root.'/');
        });
    }

    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->ajax();
    }

    private function australianShippingCountryRules(bool $required = false): array
    {
        return [
            Rule::requiredIf($required),
            'nullable',
            'string',
            'max:120',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $country = trim((string) $value);

                if ($country !== '' && strcasecmp($country, self::AUSTRALIA_SHIPPING_COUNTRY) !== 0) {
                    $fail('Shipping and checkout are currently only available to Australian addresses.');
                }
            },
        ];
    }

    private function normalizeAustralianState(mixed $value): string
    {
        $state = trim((string) $value);
        if ($state === '') {
            return '';
        }

        $code = strtoupper($state);
        if (array_key_exists($code, self::AUSTRALIAN_STATES)) {
            return $code;
        }

        foreach (self::AUSTRALIAN_STATES as $stateCode => $label) {
            if (strcasecmp($state, $label) === 0) {
                return $stateCode;
            }
        }

        return '';
    }

    private function checkoutSessionCustomer(): array
    {
        $customer = session()->get(self::CHECKOUT_SESSION_KEY.'.customer', []);
        if (! is_array($customer) || $customer === []) {
            $customer = session()->get(self::LEGACY_CHECKOUT_SESSION_KEY.'.customer', []);

            if (is_array($customer) && $customer !== []) {
                $this->putCheckoutSessionCustomer($customer);
                session()->forget(self::LEGACY_CHECKOUT_SESSION_KEY);
            }
        }

        return is_array($customer) ? $customer : [];
    }

    private function putCheckoutSessionCustomer(array $customer): void
    {
        session()->put(self::CHECKOUT_SESSION_KEY, [
            'customer' => $customer,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function clearCheckoutSession(): void
    {
        session()->forget(self::CHECKOUT_SESSION_KEY);
        session()->forget(self::LEGACY_CHECKOUT_SESSION_KEY);
    }

    private function rememberGuestOrderDocumentAccess(StoreOrder $order, Request $request): void
    {
        if ($request->user() || trim((string) ($order->access_token ?? '')) === '') {
            return;
        }

        $tokens = collect((array) session()->get(self::ORDER_DOCUMENT_ACCESS_SESSION_KEY, []))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->push((string) $order->access_token)
            ->unique()
            ->take(-20)
            ->values()
            ->all();

        session()->put(self::ORDER_DOCUMENT_ACCESS_SESSION_KEY, $tokens);
    }
}
