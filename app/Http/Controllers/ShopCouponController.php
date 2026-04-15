<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopCouponController extends Controller
{
    public function index(Request $request): View
    {
        $query = Coupon::query()->withCount(['orders', 'restrictedProducts', 'restrictedWorkshops']);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        return view('admin.shop.coupon.index', [
            'coupons' => $query->orderByDesc('created_at')->paginate(20)->onEachSide(1),
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.coupon.edit');
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = new Coupon();
        $this->saveCoupon($request, $coupon);

        session()->flash('message', 'Voucher created.');
        session()->flash('message-title', 'Voucher created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.coupon.index');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.shop.coupon.edit', [
            'coupon' => $coupon->load(['restrictedProducts', 'restrictedWorkshops']),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->saveCoupon($request, $coupon);

        session()->flash('message', 'Voucher updated.');
        session()->flash('message-title', 'Voucher updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->orders()->exists()) {
            session()->flash('message', 'This voucher has been used on orders and cannot be deleted. Set it to inactive instead.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.shop.coupon.edit', $coupon);
        }

        $coupon->delete();

        session()->flash('message', 'Voucher deleted.');
        session()->flash('message-title', 'Voucher deleted');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.coupon.index');
    }

    public function productOptions(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }

        $status = (string) $request->query('status', 'active');
        if ($status !== 'all') {
            if ($status === 'active') {
                $query->where('status', Product::STATUS_ACTIVE);
            } elseif ($status === 'draft') {
                $query->where('status', Product::STATUS_DRAFT);
            } elseif ($status === 'archived') {
                $query->where('status', Product::STATUS_ARCHIVED);
            }
        }

        $products = $query
            ->orderBy('title')
            ->paginate((int) $request->input('per_page', 12))
            ->onEachSide(1);

        return response()->json([
            'items' => $products->getCollection()->map(fn (Product $product): array => [
                'id' => (string) $product->id,
                'label' => $product->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $product->sku ? 'SKU '.$product->sku : null,
                    Product::statusLabel((string) $product->status),
                ]))),
                'status' => (string) $product->status,
                'selected' => false,
            ])->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function workshopOptions(Request $request): JsonResponse
    {
        $query = Workshop::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all') {
            if ($status === 'public') {
                $query->publiclyVisible();
            } else {
                $query->where('status', $status);
            }
        }

        $workshops = $query
            ->orderByDesc('starts_at')
            ->orderBy('title')
            ->paginate((int) $request->input('per_page', 12))
            ->onEachSide(1);

        return response()->json([
            'items' => $workshops->getCollection()->map(fn (Workshop $workshop): array => [
                'id' => (string) $workshop->id,
                'label' => $workshop->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $workshop->publicStatusLabel(),
                    optional($workshop->starts_at)->format('j M Y') ?: null,
                ]))),
                'status' => (string) $workshop->status,
                'selected' => false,
            ])->values(),
            'meta' => [
                'current_page' => $workshops->currentPage(),
                'last_page' => $workshops->lastPage(),
                'total' => $workshops->total(),
            ],
        ]);
    }

    private function saveCoupon(Request $request, Coupon $coupon): void
    {
        $discountType = (string) $request->input('discount_type', Coupon::DISCOUNT_TYPE_FIXED_AMOUNT);
        $appliesToProducts = $request->boolean('applies_to_products');
        $appliesToWorkshops = $request->boolean('applies_to_workshops');
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Coupon::STATUSES)],
            'discount_type' => ['required', Rule::in(Coupon::DISCOUNT_TYPES)],
            'applies_to_products' => ['nullable', 'boolean'],
            'applies_to_workshops' => ['nullable', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'workshop_ids' => ['nullable', 'array'],
            'workshop_ids.*' => ['string', Rule::exists('workshops', 'id')],
        ]);

        if (! $appliesToProducts && ! $appliesToWorkshops) {
            throw ValidationException::withMessages([
                'applies_to_products' => 'Select at least one voucher scope.',
            ]);
        }

        $coupon->fill([
            'code' => Coupon::normalizeCode($validated['code']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'status' => (string) $validated['status'],
            'discount_type' => $discountType,
            'applies_to_products' => $appliesToProducts,
            'applies_to_workshops' => $appliesToWorkshops,
            'amount' => match ($discountType) {
                Coupon::DISCOUNT_TYPE_PERCENTAGE => round((float) $validated['amount'], 0),
                Coupon::DISCOUNT_TYPE_FREE_SHIPPING => 0.00,
                default => round((float) $validated['amount'], 2),
            },
            'minimum_order_amount' => ($validated['minimum_order_amount'] ?? null) !== null ? round((float) $validated['minimum_order_amount'], 2) : null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_limit_per_user' => $validated['usage_limit_per_user'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);
        $coupon->save();

        $coupon->restrictedProducts()->sync($appliesToProducts ? array_values(array_unique(array_map('intval', $validated['product_ids'] ?? []))) : []);
        $coupon->restrictedWorkshops()->sync($appliesToWorkshops ? array_values(array_unique(array_map(static fn ($id): string => trim((string) $id), $validated['workshop_ids'] ?? []))) : []);
    }
}
