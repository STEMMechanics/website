<?php

namespace App\Http\Controllers;

use App\Models\StoreOrder;
use App\Services\StoreOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopAdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = StoreOrder::query()->with('invoice');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('billing_name', 'like', '%'.$search.'%')
                    ->orWhere('billing_email', 'like', '%'.$search.'%')
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%'));
            });
        }

        return view('admin.shop.order.index', [
            'orders' => $query->orderByDesc('created_at')->paginate(20)->onEachSide(1),
        ]);
    }

    public function edit(StoreOrder $storeOrder, StoreOrderService $orders): View
    {
        $order = $storeOrder->load(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'user', 'coupon']);
        $orders->syncOrderState($order);

        return view('admin.shop.order.edit', [
            'order' => $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'user', 'coupon']),
        ]);
    }

    public function update(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(StoreOrder::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $orders->updateOrderStatus(
            $storeOrder,
            (string) $validated['status'],
            $validated['notes'] ?? null,
        );

        session()->flash('message', 'Order updated.');
        session()->flash('message-title', 'Order updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }
}
