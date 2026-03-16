<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopCouponController extends Controller
{
    public function index(Request $request): View
    {
        $query = Coupon::query()->withCount('orders');

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
            'coupon' => $coupon,
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

    private function saveCoupon(Request $request, Coupon $coupon): void
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Coupon::STATUSES)],
            'discount_type' => ['required', Rule::in(Coupon::DISCOUNT_TYPES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $coupon->fill([
            'code' => Coupon::normalizeCode($validated['code']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'status' => (string) $validated['status'],
            'discount_type' => (string) $validated['discount_type'],
            'amount' => round((float) $validated['amount'], 2),
            'minimum_order_amount' => ($validated['minimum_order_amount'] ?? null) !== null ? round((float) $validated['minimum_order_amount'], 2) : null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_limit_per_user' => $validated['usage_limit_per_user'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);
        $coupon->save();
    }
}
