<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\StoreOrder;
use App\Models\StoreOrderItemDownload;
use App\Services\StoreOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreOrderController extends Controller
{
    public function accountIndex(Request $request): View
    {
        $orders = StoreOrder::query()
            ->with('invoice')
            ->where('user_id', (string) $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        return view('account.orders', [
            'orders' => $orders,
        ]);
    }

    public function accountShow(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): View
    {
        abort_unless($storeOrder->isAccessibleBy($request->user()), 403);

        $order = $this->freshOrder($storeOrder, $orders);

        return view('shop.order', $this->orderViewPayload($order, true, null));
    }

    public function publicShow(StoreOrder $storeOrder, string $accessToken, StoreOrderService $orders): View
    {
        abort_unless($storeOrder->isAccessibleBy(auth()->user(), $accessToken), 403);

        $order = $this->freshOrder($storeOrder, $orders);

        return view('shop.order', $this->orderViewPayload($order, false, $accessToken));
    }

    public function accountPay(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): RedirectResponse
    {
        abort_unless($storeOrder->isAccessibleBy($request->user()), 403);

        return $this->handlePayment($request, $storeOrder, $orders, true, null);
    }

    public function publicPay(Request $request, StoreOrder $storeOrder, string $accessToken, StoreOrderService $orders): RedirectResponse
    {
        abort_unless($storeOrder->isAccessibleBy($request->user(), $accessToken), 403);

        return $this->handlePayment($request, $storeOrder, $orders, false, $accessToken);
    }

    public function accountDownload(Request $request, StoreOrder $storeOrder, StoreOrderItemDownload $storeOrderItemDownload, StoreOrderService $orders): BinaryFileResponse
    {
        abort_unless($storeOrder->isAccessibleBy($request->user()), 403);

        return $this->downloadResponse($this->freshOrder($storeOrder, $orders), $storeOrderItemDownload);
    }

    public function publicDownload(StoreOrder $storeOrder, string $accessToken, StoreOrderItemDownload $storeOrderItemDownload, StoreOrderService $orders): BinaryFileResponse
    {
        abort_unless($storeOrder->isAccessibleBy(auth()->user(), $accessToken), 403);

        return $this->downloadResponse($this->freshOrder($storeOrder, $orders), $storeOrderItemDownload);
    }

    private function handlePayment(
        Request $request,
        StoreOrder $storeOrder,
        StoreOrderService $orders,
        bool $isAccountView,
        ?string $accessToken
    ): RedirectResponse {
        $validated = $request->validate([
            'source_id' => ['required', 'string', 'max:255'],
        ], [
            'source_id.required' => 'Card details are required.',
        ]);

        try {
            $orders->charge($storeOrder, (string) $validated['source_id'], $request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->to($this->orderRedirectUrl($storeOrder, $isAccountView, $accessToken))
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            session()->flash('message', 'Unable to process payment right now.');
            session()->flash('message-title', 'Payment failed');
            session()->flash('message-type', 'danger');

            return redirect()->to($this->orderRedirectUrl($storeOrder, $isAccountView, $accessToken));
        }

        session()->flash('message', 'Payment completed successfully.');
        session()->flash('message-title', 'Payment success');
        session()->flash('message-type', 'success');

        return redirect()->to($this->orderRedirectUrl($storeOrder, $isAccountView, $accessToken));
    }

    private function freshOrder(StoreOrder $storeOrder, StoreOrderService $orders): StoreOrder
    {
        $order = $storeOrder->load([
            'invoice.allocations.customerPayment',
            'items.product.hero',
            'items.variant',
            'items.downloads.media',
            'user',
            'coupon',
        ]);
        $orders->syncOrderState($order);

        return $order->fresh([
            'invoice.allocations.customerPayment',
            'items.product.hero',
            'items.variant',
            'items.downloads.media',
            'user',
            'coupon',
        ]);
    }

    private function orderViewPayload(StoreOrder $order, bool $isAccountView, ?string $accessToken): array
    {
        $isPaid = $order->invoice?->outstandingAmount() <= 0.0001;
        $downloadableItems = $order->items->filter(fn ($item) => $item->downloads->isNotEmpty())->values();

        return [
            'order' => $order,
            'isAccountView' => $isAccountView,
            'accessToken' => $accessToken,
            'isPaid' => $isPaid,
            'downloadableItems' => $downloadableItems,
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
            'payActionUrl' => $isAccountView
                ? route('account.order.pay', $order)
                : route('shop.order.pay', ['storeOrder' => $order, 'accessToken' => $accessToken]),
        ];
    }

    private function downloadResponse(StoreOrder $order, StoreOrderItemDownload $download): BinaryFileResponse
    {
        $orderItem = $download->orderItem()->with('order')->first();
        abort_unless($orderItem && $orderItem->store_order_id === $order->id, 404);
        abort_unless($order->invoice?->outstandingAmount() <= 0.0001, 403, 'This download is available after payment.');

        /** @var Media|null $media */
        $media = $download->media;
        abort_unless($media instanceof Media, 404);

        $path = $media->path();
        abort_if($path === null, 404, 'File not found.');

        return response()->download($path, $media->name, [
            'Content-Type' => (string) $media->mime_type,
        ]);
    }

    private function orderRedirectUrl(StoreOrder $order, bool $isAccountView, ?string $accessToken): string
    {
        return $isAccountView
            ? route('account.order.show', $order)
            : route('shop.order.show', ['storeOrder' => $order, 'accessToken' => $accessToken]);
    }
}
