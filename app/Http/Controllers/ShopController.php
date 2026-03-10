<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StoreCartService;
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
        $shippingCountry = trim((string) ($request->query('shipping_country') ?? 'Australia')) ?: 'Australia';
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
            'summary' => $cart->summary([
                'shipping_country' => $shippingCountry,
                'user' => $request->user(),
            ]),
            'shippingCountry' => $shippingCountry,
            'couponCode' => $cart->couponCode(),
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

        if (! $product->isInStock($variant)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'That item is currently out of stock.',
            ]);
        }

        $cart->add($product, $variant, (int) ($validated['quantity'] ?? 1));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Added '.($variant ? $product->title.' - '.$variant->name : $product->title).' to your cart.',
                'cart' => $cart->payload([
                    'shipping_country' => trim((string) $request->input('shipping_country', 'Australia')) ?: 'Australia',
                    'user' => $request->user(),
                ]),
            ]);
        }

        session()->flash('message', 'Added '.($variant ? $product->title.' - '.$variant->name : $product->title).' to your cart.');
        session()->flash('message-title', 'Cart updated');
        session()->flash('message-type', 'success');

        return redirect()->to($this->cartReturnUrl($request, route('shop.cart.show')));
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

    public function applyCoupon(Request $request, StoreCartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'shipping_country' => ['nullable', 'string', 'max:120'],
        ]);

        $couponCode = trim((string) ($validated['coupon_code'] ?? ''));
        if ($couponCode === '') {
            $cart->clearCoupon();
            session()->flash('message', 'Coupon removed.');
            session()->flash('message-title', 'Coupon updated');
            session()->flash('message-type', 'success');

            return redirect()->route('shop.cart.show', [
                'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia',
            ]);
        }

        $cart->applyCoupon($couponCode);
        $summary = $cart->summary([
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia',
            'user' => $request->user(),
        ]);

        if ($summary['coupon_error']) {
            session()->flash('message', $summary['coupon_error']);
            session()->flash('message-title', 'Coupon not applied');
            session()->flash('message-type', 'danger');
        } else {
            session()->flash('message', 'Coupon applied successfully.');
            session()->flash('message-title', 'Coupon applied');
            session()->flash('message-type', 'success');
        }

        return redirect()->route('shop.cart.show', [
            'shipping_country' => trim((string) ($validated['shipping_country'] ?? '')) ?: 'Australia',
        ]);
    }

    public function removeCoupon(Request $request, StoreCartService $cart): RedirectResponse
    {
        $shippingCountry = trim((string) $request->input('shipping_country', 'Australia')) ?: 'Australia';
        $cart->clearCoupon();

        session()->flash('message', 'Coupon removed.');
        session()->flash('message-title', 'Coupon updated');
        session()->flash('message-type', 'success');

        return redirect()->route('shop.cart.show', [
            'shipping_country' => $shippingCountry,
        ]);
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
        $userShippingAddress = $user instanceof User ? trim((string) $user->shipping_address) : '';
        $userShippingAddress2 = $user instanceof User ? trim((string) $user->shipping_address2) : '';
        $userShippingCity = $user instanceof User ? trim((string) $user->shipping_city) : '';
        $userShippingState = $user instanceof User ? trim((string) $user->shipping_state) : '';
        $userShippingPostcode = $user instanceof User ? trim((string) $user->shipping_postcode) : '';
        $userShippingCountry = $user instanceof User ? trim((string) $user->shipping_country) : '';
        $shippingCountry = trim((string) old('shipping_country', $storedCustomer['shipping_country'] ?? $userShippingCountry ?: 'Australia')) ?: 'Australia';
        $billingEmail = trim((string) old('billing_email', $storedCustomer['billing_email'] ?? $userEmail));
        $summary = $cart->summary([
            'shipping_country' => $shippingCountry,
            'user' => $user,
            'billing_email' => $billingEmail,
        ]);

        return view('shop.checkout', [
            'lines' => $cart->lines(),
            'summary' => $summary,
            'couponCode' => $cart->couponCode(),
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
                'shipping_state' => trim((string) old('shipping_state', $storedCustomer['shipping_state'] ?? $userShippingState)),
                'shipping_postcode' => trim((string) old('shipping_postcode', $storedCustomer['shipping_postcode'] ?? $userShippingPostcode)),
                'shipping_country' => $shippingCountry,
                'notes' => trim((string) old('notes', $storedCustomer['notes'] ?? '')),
            ],
        ]);
    }

    public function placeOrder(Request $request, StoreCartService $cart, StoreOrderService $orders): RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $summary = $cart->summary();
        $shippingRequired = (bool) ($summary['contains_physical'] ?? false);

        $validated = $request->validate([
            'billing_name' => ['required', 'string', 'max:120'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:60'],
            'billing_company' => ['nullable', 'string', 'max:120'],
            'shipping_name' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'shipping_phone' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:60'],
            'shipping_address' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:255'],
            'shipping_address2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'shipping_state' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'shipping_postcode' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:20'],
            'shipping_country' => [Rule::requiredIf($shippingRequired), 'nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $shippingRequired) {
            $validated['shipping_name'] = $validated['billing_name'];
            $validated['shipping_phone'] = $validated['billing_phone'];
            $validated['shipping_country'] = $validated['shipping_country'] ?? 'Australia';
        }

        $validated['coupon_code'] = $cart->couponCode();

        $summary = $cart->summary([
            'shipping_country' => $validated['shipping_country'] ?? 'Australia',
            'user' => $request->user(),
            'billing_email' => $validated['billing_email'],
            'coupon_code' => $validated['coupon_code'],
        ]);

        if ($summary['coupon_error']) {
            return redirect()->back()->withErrors([
                'coupon_code' => $summary['coupon_error'],
            ])->withInput();
        }

        if (! ($summary['can_checkout'] ?? true)) {
            return redirect()->back()->withErrors([
                'shipping_country' => (string) ($summary['shipping_quote']['reason'] ?? 'Shipping could not be calculated for this order.'),
            ])->withInput();
        }

        if ((float) ($summary['total'] ?? 0) <= 0.0001) {
            $order = $orders->createFromCart($lines, $validated, $request->user());
            $cart->clear();
            $this->clearCheckoutSession();

            session()->flash('message', 'Your order is complete.');
            session()->flash('message-title', 'Order created');
            session()->flash('message-type', 'success');

            return $request->user()
                ? redirect()->route('account.order.show', $order)
                : redirect()->route('shop.order.show', ['storeOrder' => $order, 'accessToken' => $order->access_token]);
        }

        $this->putCheckoutSessionCustomer($validated);

        return redirect()->route('shop.checkout.payment');
    }

    public function checkoutPayment(Request $request, StoreCartService $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $customer = $this->checkoutSessionCustomer();
        if ($customer === []) {
            return redirect()->route('shop.checkout');
        }

        $summary = $cart->summary([
            'shipping_country' => $customer['shipping_country'] ?? 'Australia',
            'user' => $request->user(),
            'billing_email' => $customer['billing_email'] ?? null,
            'coupon_code' => $cart->couponCode(),
        ]);

        if ($summary['coupon_error']) {
            return redirect()->route('shop.checkout')
                ->withErrors(['coupon_code' => $summary['coupon_error']])
                ->withInput();
        }

        if (! ($summary['can_checkout'] ?? true)) {
            return redirect()->route('shop.checkout')
                ->withErrors(['shipping_country' => (string) ($summary['shipping_quote']['reason'] ?? 'Shipping could not be calculated for this order.')])
                ->withInput();
        }

        $lines = $cart->lines();

        return view('shop.payment', [
            'lines' => $lines,
            'heroLine' => $lines->shuffle()->first(),
            'summary' => $summary,
            'couponCode' => $cart->couponCode(),
            'customer' => $customer,
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
        ]);
    }

    public function processCheckoutPayment(Request $request, StoreCartService $cart, StoreOrderService $orders): RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            $this->clearCheckoutSession();

            return redirect()->route('shop.cart.show');
        }

        $customer = $this->checkoutSessionCustomer();
        if ($customer === []) {
            return redirect()->route('shop.checkout');
        }

        $payload = array_merge($customer, [
            'coupon_code' => $cart->couponCode(),
        ]);

        $summary = $cart->summary([
            'shipping_country' => $payload['shipping_country'] ?? 'Australia',
            'user' => $request->user(),
            'billing_email' => $payload['billing_email'] ?? null,
            'coupon_code' => $payload['coupon_code'] ?? null,
        ]);

        if ($summary['coupon_error']) {
            return redirect()->route('shop.checkout')
                ->withErrors(['coupon_code' => $summary['coupon_error']])
                ->withInput();
        }

        if (! ($summary['can_checkout'] ?? true)) {
            return redirect()->route('shop.checkout')
                ->withErrors(['shipping_country' => (string) ($summary['shipping_quote']['reason'] ?? 'Shipping could not be calculated for this order.')])
                ->withInput();
        }

        if ((float) ($summary['total'] ?? 0) <= 0.0001) {
            try {
                $order = $orders->createFromCart($lines, $payload, $request->user());
            } catch (ValidationException $e) {
                return redirect()->route('shop.checkout')
                    ->withErrors($e->errors())
                    ->withInput();
            }

            $cart->clear();
            $this->clearCheckoutSession();

            session()->flash('message', 'Your order is complete.');
            session()->flash('message-title', 'Order created');
            session()->flash('message-type', 'success');

            return $request->user()
                ? redirect()->route('account.order.show', $order)
                : redirect()->route('shop.order.show', ['storeOrder' => $order, 'accessToken' => $order->access_token]);
        }

        $validated = $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
        ], [
            'source_id.required' => 'Card details are required.',
        ]);

        try {
            $order = $orders->createAndChargeFromCart($lines, $payload, (string) $validated['source_id'], $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('shop.checkout.payment')
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            session()->flash('message', 'Unable to process payment right now.');
            session()->flash('message-title', 'Payment failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('shop.checkout.payment');
        }

        $cart->clear();
        $this->clearCheckoutSession();

        session()->flash('message', 'Payment completed successfully.');
        session()->flash('message-title', 'Payment success');
        session()->flash('message-type', 'success');

        return $request->user()
            ? redirect()->route('account.order.show', $order)
            : redirect()->route('shop.order.show', ['storeOrder' => $order, 'accessToken' => $order->access_token]);
    }

    private function resolveVariantSelection(Product $product, mixed $variantId): ?ProductVariant
    {
        if (! $product->hasVariants()) {
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
                    ->orderByRaw('COALESCE((select min(coalesce(product_variants.price, products.price)) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1), products.price) asc')
                    ->orderBy('title');

                return;

            case 'price_high':
                $query
                    ->orderByRaw('COALESCE((select max(coalesce(product_variants.price, products.price)) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1), products.price) desc')
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
}
