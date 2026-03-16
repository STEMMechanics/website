<?php

namespace App\Http\Controllers;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemTracking;
use App\Services\StoreOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $order = $storeOrder->load([
            'invoice.allocations.customerPayment',
            'items.downloads.media',
            'items.product.hero',
            'items.variant',
            'items.trackingEntries',
            'items.cancellations.cancelledBy',
            'user',
            'coupon',
        ]);
        $orders->syncOrderState($order);

        return view('admin.shop.order.edit', [
            'order' => $order->fresh([
                'invoice.allocations.customerPayment',
                'items.downloads.media',
                'items.product.hero',
                'items.variant',
                'items.trackingEntries',
                'items.cancellations.cancelledBy',
                'user',
                'coupon',
            ]),
            'carrierSuggestions' => $this->carrierSuggestions(),
        ]);
    }

    public function update(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(StoreOrder::STATUSES)],
            'notes' => ['nullable', 'string'],
            'public_notes' => ['nullable', 'string'],
            'item_actions_json' => ['nullable', 'string'],
        ]);

        $itemActions = $this->parseQueuedItemActions($validated['item_actions_json'] ?? null);

        try {
            $itemSummary = $itemActions !== []
                ? $orders->applyOrderItemActions($storeOrder, $itemActions, $request->user(), true)
                : null;

            $orders->updateOrderStatus(
                $storeOrder,
                (string) $validated['status'],
                $validated['notes'] ?? null,
                $validated['public_notes'] ?? null,
                true,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        }

        $message = $itemSummary === null
            ? 'Order updated.'
            : 'Order changes saved. '.(int) ($itemSummary['item_action_count'] ?? 0).' staged item action'.(((int) ($itemSummary['item_action_count'] ?? 0)) === 1 ? '' : 's').' applied.';
        if (is_string($itemSummary['adjustment_note_number'] ?? null) && trim((string) $itemSummary['adjustment_note_number']) !== '') {
            $message .= ' Tax adjustment note '.trim((string) $itemSummary['adjustment_note_number']).' created.';
        }
        $refundedCents = (int) ($itemSummary['refunded_cents'] ?? 0);
        if ($refundedCents > 0) {
            $message .= ' Square refund issued: $'.number_format($refundedCents / 100, 2).'.';
        }
        if ((bool) ($itemSummary['manual_refund_required'] ?? false)) {
            $message .= ' Manual refund still required.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Order updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function cancelItem(Request $request, StoreOrder $storeOrder, StoreOrderItem $storeOrderItem, StoreOrderService $orders): RedirectResponse
    {
        $this->ensureOrderItemMatches($storeOrder, $storeOrderItem);

        $bag = 'cancelItem_'.$storeOrderItem->id;
        $validator = Validator::make($request->all(), [
            'quantity' => ['nullable', 'integer', 'min:0'],
            'available_quantity' => ['nullable', 'integer', 'min:0'],
            'delayed_quantity' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator, $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        $validated = $validator->validated();
        $requestedQuantity = max(0, (int) ($validated['quantity'] ?? 0));

        try {
            $summary = $orders->cancelOrderItemQuantities(
                $storeOrder,
                $storeOrderItem,
                $requestedQuantity > 0
                    ? $requestedQuantity
                    : (int) ($validated['available_quantity'] ?? 0),
                $requestedQuantity > 0
                    ? 0
                    : (int) ($validated['delayed_quantity'] ?? 0),
                (string) $validated['reason'],
                $request->user(),
                true,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors(), $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        $message = 'Item quantities cancelled.';
        $adjustmentNoteNumber = trim((string) ($summary['adjustment_note_number'] ?? ''));
        if ($adjustmentNoteNumber !== '') {
            $message .= ' Tax adjustment note '.$adjustmentNoteNumber.' created.';
        }
        $refundedCents = (int) ($summary['refunded_cents'] ?? 0);
        if ($refundedCents > 0) {
            $message .= ' Square refund issued: $'.number_format($refundedCents / 100, 2).'.';
        }
        if ((bool) ($summary['manual_refund_required'] ?? false)) {
            $message .= ' Manual refund still required.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Item updated');
        session()->flash('message-type', 'success');

        return redirect()->back()->withFragment('item-'.$storeOrderItem->id);
    }

    public function storeItemTracking(Request $request, StoreOrder $storeOrder, StoreOrderItem $storeOrderItem, StoreOrderService $orders): RedirectResponse
    {
        $this->ensureOrderItemMatches($storeOrder, $storeOrderItem);

        $bag = 'trackingItem_'.$storeOrderItem->id;
        $validator = Validator::make($request->all(), [
            'shipment_type' => ['required', Rule::in([
                StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
            ])],
            'quantity' => ['required', 'integer', 'min:1'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string'],
            'dispatched_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator, $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        try {
            $orders->addOrderItemTracking($storeOrder, $storeOrderItem, $validator->validated(), true);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors(), $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        session()->flash('message', 'Shipment entry added.');
        session()->flash('message-title', 'Shipment saved');
        session()->flash('message-type', 'success');

        return redirect()->back()->withFragment('item-'.$storeOrderItem->id);
    }

    private function ensureOrderItemMatches(StoreOrder $order, StoreOrderItem $item): void
    {
        abort_unless($item->store_order_id === $order->id, 404);
    }

    /**
     * @return array<int, string>
     */
    private function carrierSuggestions(): array
    {
        return StoreOrderItemTracking::query()
            ->whereNotNull('carrier')
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('carrier')
            ->map(fn ($carrier) => trim((string) $carrier))
            ->filter(fn (string $carrier) => $carrier !== '')
            ->unique(fn (string $carrier) => mb_strtolower($carrier))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseQueuedItemActions(?string $payload): array
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued item actions could not be read. Please reopen the item modals and try again.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued item actions were not submitted in a valid format.',
            ]);
        }

        return array_values(array_filter($decoded, fn ($action) => is_array($action)));
    }
}
