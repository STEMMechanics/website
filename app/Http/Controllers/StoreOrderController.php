<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemDownload;
use App\Models\StoreOrderItemTracking;
use App\Models\Token;
use App\Services\StoreOrderService;
use App\Services\StoreShippingMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreOrderController extends Controller
{
    private const ORDER_DOCUMENT_ACCESS_SESSION_KEY = 'store.order.document-access-tokens';

    public function accountIndex(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $orders = StoreOrder::query()
            ->with('invoice')
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->where('user_id', (string) $request->user()->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($orderQuery) use ($search): void {
                    $like = '%'.$search.'%';

                    $orderQuery
                        ->where('order_number', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhere('shipping_method', 'like', $like)
                        ->orWhere('shipping_method_code', 'like', $like)
                        ->orWhere('coupon_code', 'like', $like)
                        ->orWhereHas('invoice', function ($invoiceQuery) use ($like): void {
                            $invoiceQuery->where('invoice_number', 'like', $like);
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($like): void {
                            $itemQuery
                                ->where('product_title', 'like', $like)
                                ->orWhere('variant_name', 'like', $like)
                                ->orWhere('product_sku', 'like', $like)
                                ->orWhere('variant_sku', 'like', $like);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        return view('account.orders', [
            'orders' => $orders,
        ]);
    }

    public function accountShow(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): View
    {
        abort_unless($storeOrder->isAccessibleBy($request->user()), 403);

        $order = $this->freshOrder($storeOrder, $orders);

        return view('shop.order', $this->orderViewPayload($request, $order, true, null));
    }

    public function publicShow(StoreOrder $storeOrder, string $accessToken, StoreOrderService $orders): View
    {
        abort_unless($storeOrder->isAccessibleBy(auth()->user(), $accessToken), 403);

        $order = $this->freshOrder($storeOrder, $orders);

        return view('shop.order', $this->orderViewPayload(request(), $order, false, $accessToken));
    }

    public function trackingShow(string $accessToken, StoreOrderService $orders): View
    {
        $order = $this->freshOrder($this->orderForAccessToken($accessToken), $orders);

        return view('shop.order', $this->orderViewPayload(request(), $order, false, $accessToken));
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

    public function trackingPay(Request $request, string $accessToken, StoreOrderService $orders): RedirectResponse
    {
        return $this->handlePayment($request, $this->orderForAccessToken($accessToken), $orders, false, $accessToken);
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

    public function trackingDownload(Request $request, string $accessToken, StoreOrderItemDownload $storeOrderItemDownload, StoreOrderService $orders): BinaryFileResponse|View
    {
        $order = $this->freshOrder($this->orderForAccessToken($accessToken), $orders);

        return $this->guestAwareDownloadResponse($request, $order, $storeOrderItemDownload, $accessToken);
    }

    public function trackingVerifyDownload(Request $request, string $accessToken, StoreOrderItemDownload $storeOrderItemDownload, StoreOrderService $orders): View
    {
        $order = $this->freshOrder($this->orderForAccessToken($accessToken), $orders);
        abort_unless($order->invoice?->outstandingAmount() <= 0.0001, 403, 'This download is available after payment.');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Enter the email address used for this order.',
        ]);

        $submittedEmail = Str::lower(trim((string) $validated['email']));
        abort_if($submittedEmail === '', 422, 'Enter the email address used for this order.');

        if (! in_array($submittedEmail, $this->guestDownloadAllowedEmails($order), true)) {
            throw ValidationException::withMessages([
                'email' => 'That email address does not match this order.',
            ]);
        }

        $signedUrl = URL::temporarySignedRoute(
            'shop.order.tracking.download',
            now()->addMinutes(15),
            [
                'accessToken' => $accessToken,
                'storeOrderItemDownload' => $storeOrderItemDownload,
            ]
        );

        return view('shop.order-download-started', [
            'order' => $order,
            'download' => $storeOrderItemDownload->loadMissing('media'),
            'downloadUrl' => $signedUrl,
            'backUrl' => route('shop.order.tracking', ['accessToken' => $accessToken]),
        ]);
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
        $this->rememberGuestOrderDocumentAccess($request, $storeOrder);

        return redirect()->to($this->orderRedirectUrl($storeOrder, $isAccountView, $accessToken));
    }

    private function freshOrder(StoreOrder $storeOrder, StoreOrderService $orders): StoreOrder
    {
        $relations = [
            'invoice.allocations.customerPayment',
            'invoice.allocations.customerPayment.refunds',
            'invoice.taxAdjustments.lines',
            'items.product.hero',
            'items.variant',
            'items.downloads.media',
            'items.trackingEntries',
            'user',
            'coupon',
        ];

        $order = $storeOrder->load($relations);
        $orders->syncOrderState($order);
        $order = $order->fresh($relations);

        if ($this->backfillLegacyDigitalDownloads($order)) {
            $order = $order->fresh($relations);
        }

        return $order;
    }

    private function backfillLegacyDigitalDownloads(StoreOrder $order): bool
    {
        $backfilled = false;

        foreach ($order->items as $item) {
            if (! $item->isDigital() || $item->downloads->isNotEmpty()) {
                continue;
            }

            $product = $item->product;
            if (! $product) {
                continue;
            }

            $productDownloads = $product->downloadMedia()->get();
            if ($productDownloads->isEmpty()) {
                continue;
            }

            foreach ($productDownloads as $index => $media) {
                $item->downloads()->firstOrCreate(
                    [
                        'media_name' => (string) $media->name,
                    ],
                    [
                        'title' => (string) ($media->title ?? $media->name),
                        'sort_order' => $index,
                    ],
                );
            }

            $backfilled = true;
        }

        return $backfilled;
    }

    private function orderViewPayload(Request $request, StoreOrder $order, bool $isAccountView, ?string $accessToken): array
    {
        $isPaid = $order->invoice instanceof Invoice
            && $order->invoice->outstandingAmount() <= 0.0001;
        $orderItems = $order->items->values();
        $downloadableItems = $order->items->filter(fn ($item) => $item->downloads->isNotEmpty())->values();
        $canDownloadDocuments = $this->canDownloadOrderDocuments($request, $order, $isAccountView);
        $canViewAddressDetails = $this->canViewOrderAddressDetails($request, $order, $isAccountView);

        return [
            'order' => $order,
            'orderItems' => $orderItems,
            'isAccountView' => $isAccountView,
            'accessToken' => $accessToken,
            'isPaid' => $isPaid,
            'downloadableItems' => $downloadableItems,
            'awaitingFulfilmentItems' => $this->buildAwaitingFulfilmentItems($order, $orderItems),
            'deliveryGroups' => $this->buildDeliveryGroups($order, $orderItems),
            'canDownloadDocuments' => $canDownloadDocuments,
            'canViewAddressDetails' => $canViewAddressDetails,
            'invoicePdfUrl' => $canDownloadDocuments ? $this->orderInvoicePdfUrl($order, $isAccountView) : null,
            'receiptLinks' => $canDownloadDocuments ? $this->orderReceiptLinks($order, $isAccountView) : [],
            'refundReceiptLinksByInvoiceLineId' => $canDownloadDocuments ? $this->orderRefundReceiptLinksByInvoiceLineId($order, $isAccountView) : [],
            'emailDocumentsActionUrl' => $order->invoice instanceof Invoice
                ? route('invoice.public.email-documents', $order->invoice)
                : null,
            'squareEnabled' => (bool) config('services.square.enabled'),
            'squareApplicationId' => (string) config('services.square.application_id'),
            'squareLocationId' => (string) config('services.square.location_id'),
            'squareEnvironment' => (string) config('services.square.environment'),
            'payActionUrl' => $isAccountView
                ? route('account.order.pay', $order)
                : route('shop.order.tracking.pay', ['accessToken' => $accessToken]),
        ];
    }

    private function buildAwaitingFulfilmentItems(StoreOrder $order, Collection $orderedItems): array
    {
        return $orderedItems
            ->filter(fn (StoreOrderItem $item): bool => ! $item->isDigital() && $item->remainingFulfillableQuantity() > 0)
            ->values()
            ->map(function (StoreOrderItem $item, int $index) use ($order): array {
                $remainingAvailable = $item->remainingAvailableQuantity();
                $remainingDelayed = $item->remainingDelayedQuantity();
                $estimate = $item->is_preorder
                    ? $item->preorderShippingEstimateLabel('F jS Y')
                    : $item->delayedShippingEstimateLabel('F jS Y');
                $parts = [];

                if ($remainingAvailable > 0) {
                    $parts[] = $order->usesPickup()
                        ? $remainingAvailable.' ready to collect now'
                        : $remainingAvailable.' preparing for dispatch now';
                }

                if ($remainingDelayed > 0) {
                    $parts[] = $remainingDelayed.' '.($order->usesPickup() ? 'expected availability ' : 'expected shipping ')
                        .($estimate ?: 'to be confirmed');
                }

                return [
                    'number' => $index + 1,
                    'title' => $item->displayTitle(),
                    'quantity' => $item->remainingFulfillableQuantity(),
                    'detail' => $parts !== [] ? implode(', ', $parts) : null,
                    'sku' => $this->resolveOrderItemSku($item),
                ];
            })
            ->all();
    }

    private function buildDeliveryGroups(StoreOrder $order, Collection $orderedItems): array
    {
        $arrivalDetail = null;
        $deliveryEstimate = $this->deliveryEstimateLabel($order);
        if (! $order->usesPickup() && $deliveryEstimate !== null) {
            $arrivalDetail = 'Estimated arrival: '.$deliveryEstimate;
        }

        $numberByItemId = $orderedItems->mapWithKeys(function (StoreOrderItem $item, int $index): array {
            return [(int) $item->id => $index + 1];
        });

        $trackingRows = $order->items
            ->filter(fn (StoreOrderItem $item): bool => ! $item->isDigital() && $item->trackingEntries->isNotEmpty())
            ->flatMap(function (StoreOrderItem $item) {
                return $item->trackingEntries->map(function (StoreOrderItemTracking $tracking) use ($item): array {
                    $carrier = trim((string) ($tracking->carrier ?? ''));
                    $trackingNumber = trim((string) ($tracking->tracking_number ?? ''));
                    $trackingUrl = trim((string) ($tracking->tracking_url ?? ''));
                    $notes = trim((string) ($tracking->notes ?? ''));
                    $dispatchedAt = $tracking->dispatched_at;

                    return [
                        'item_id' => (int) $item->id,
                        'item_number' => 0,
                        'item_sku' => $this->resolveOrderItemSku($item),
                        'group_key' => $this->deliveryGroupKey($tracking),
                        'dispatched_timestamp' => $dispatchedAt instanceof \Illuminate\Support\Carbon ? $dispatchedAt->timestamp : 0,
                        'dispatched_label' => $dispatchedAt instanceof \Illuminate\Support\Carbon ? $dispatchedAt->format('M j, Y') : null,
                        'carrier' => $carrier !== '' ? $carrier : null,
                        'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
                        'tracking_url' => $trackingUrl !== '' ? $trackingUrl : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'item_title' => $item->displayTitle(),
                        'quantity' => max(0, (int) $tracking->quantity),
                    ];
                })->all();
            })
            ->sortBy('dispatched_timestamp')
            ->values()
            ->map(function (array $row) use ($numberByItemId): array {
                $itemId = (int) $row['item_id'];
                $row['item_number'] = (int) ($numberByItemId[$itemId] ?? 0);

                return $row;
            });

        $deliveryNoun = $order->usesPickup() ? 'Collection' : 'Delivery';
        $groupedRows = [];
        foreach ($trackingRows as $row) {
            $groupedRows[(string) $row['group_key']][] = $row;
        }

        $deliveryGroups = [];
        foreach ($groupedRows as $groupKey => $rows) {
            $deliveryGroups[] = [
                'group_key' => $groupKey,
                'sort_timestamp' => (int) collect($rows)->min('dispatched_timestamp'),
                'rows' => $rows,
            ];
        }

        usort($deliveryGroups, function (array $left, array $right): int {
            $timestampComparison = $left['sort_timestamp'] <=> $right['sort_timestamp'];
            if ($timestampComparison !== 0) {
                return $timestampComparison;
            }

            return strcmp((string) $left['group_key'], (string) $right['group_key']);
        });

        return collect($deliveryGroups)
            ->values()
            ->map(function (array $groupData, int $index) use ($deliveryNoun, $arrivalDetail): array {
                $group = collect($groupData['rows']);
                $first = $group->first();
                $metaParts = $first === null
                    ? []
                    : array_values(array_filter([
                        $first['dispatched_label'] ?? null,
                        $first['carrier'] ?? null,
                    ]));

                $items = collect($group)
                    ->groupBy('item_id')
                    ->map(function ($itemGroup): array {
                        $first = $itemGroup->first();

                        return [
                            'number' => (int) ($first['item_number'] ?? 0),
                            'title' => (string) ($first['item_title'] ?? 'Item'),
                            'quantity' => (int) collect($itemGroup)->sum('quantity'),
                            'sku' => trim((string) ($first['item_sku'] ?? '')) !== '' ? trim((string) ($first['item_sku'] ?? '')) : null,
                        ];
                    })
                    ->sortBy('number')
                    ->values()
                    ->all();

                return [
                    'label' => $deliveryNoun.' '.($index + 1),
                    'meta' => $metaParts !== [] ? implode(' · ', $metaParts) : null,
                    'arrival_detail' => $arrivalDetail,
                    'tracking_number' => $first['tracking_number'] ?? null,
                    'tracking_url' => $first['tracking_url'] ?? null,
                    'notes' => $first['notes'] ?? null,
                    'items' => $items,
                ];
            })
            ->all();
    }

    private function resolveOrderItemSku(StoreOrderItem $item): ?string
    {
        $sku = trim((string) ($item->variant_sku ?: $item->product_sku ?: $item->variant?->sku ?: $item->product?->sku));

        return $sku !== '' ? $sku : null;
    }

    private function deliveryEstimateLabel(StoreOrder $order): ?string
    {
        $breakdown = $order->shippingBreakdown();
        $shipments = collect($breakdown['shipments'] ?? [])->filter(fn ($shipment) => is_array($shipment));

        $label = trim((string) ($breakdown['delivery_estimate_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $firstShipment = $shipments->first();
        $label = is_array($firstShipment)
            ? trim((string) ($firstShipment['delivery_estimate_label'] ?? ''))
            : '';
        if ($label !== '') {
            return $label;
        }

        $method = app(StoreShippingMethodService::class)->resolveForLines($order->items, $order->shipping_method_code);
        $label = trim((string) ($method?->deliveryEstimateLabel() ?? ''));

        return $label !== '' ? $label : null;
    }

    private function deliveryGroupKey(StoreOrderItemTracking $tracking): string
    {
        $carrier = Str::lower(trim((string) ($tracking->carrier ?? '')));
        $trackingNumber = Str::lower(trim((string) ($tracking->tracking_number ?? '')));
        if ($trackingNumber !== '') {
            return 'tracking:'.$carrier.'|'.$trackingNumber;
        }

        $trackingUrl = Str::lower(trim((string) ($tracking->tracking_url ?? '')));
        if ($trackingUrl !== '') {
            return 'url:'.$carrier.'|'.$trackingUrl;
        }

        return 'manual:'
            .($tracking->dispatched_at?->format('Y-m-d') ?? 'undated')
            .'|'.$carrier;
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

    private function guestAwareDownloadResponse(
        Request $request,
        StoreOrder $order,
        StoreOrderItemDownload $download,
        string $accessToken
    ): BinaryFileResponse|View {
        $orderItem = $download->orderItem()->with('order')->first();
        abort_unless($orderItem && $orderItem->store_order_id === $order->id, 404);
        abort_unless($order->invoice?->outstandingAmount() <= 0.0001, 403, 'This download is available after payment.');

        if ($order->isAccessibleBy($request->user())) {
            return $this->downloadResponse($order, $download);
        }

        if ($request->hasValidSignature()) {
            return $this->downloadResponse($order, $download);
        }

        return view('shop.order-download-verify', [
            'order' => $order,
            'download' => $download,
            'accessToken' => $accessToken,
            'verifyActionUrl' => route('shop.order.tracking.download.verify', [
                'accessToken' => $accessToken,
                'storeOrderItemDownload' => $download,
            ]),
            'backUrl' => route('shop.order.tracking', ['accessToken' => $accessToken]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function guestDownloadAllowedEmails(StoreOrder $order): array
    {
        return collect([
            $order->billing_email,
            $order->user?->email,
        ])
            ->filter(fn ($email) => trim((string) $email) !== '')
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->unique()
            ->values()
            ->all();
    }

    private function orderRedirectUrl(StoreOrder $order, bool $isAccountView, ?string $accessToken): string
    {
        return $isAccountView
            ? route('account.order.show', $order)
            : route('shop.order.tracking', ['accessToken' => $accessToken]);
    }

    private function orderForAccessToken(string $accessToken): StoreOrder
    {
        $order = StoreOrder::query()
            ->where('access_token', trim($accessToken))
            ->firstOrFail();

        abort_unless($order->isAccessibleBy(auth()->user(), $accessToken), 403);

        return $order;
    }

    private function orderInvoicePdfUrl(StoreOrder $order, bool $isAccountView): ?string
    {
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice) {
            return null;
        }

        if ($isAccountView) {
            return route('account.invoice.pdf', [
                'invoice' => $invoice,
                'download' => 1,
            ]);
        }

        $token = $this->resolveOrderInvoiceAccessToken($invoice, $order);

        return route('invoice.magic.pdf', [
            'invoice' => $invoice,
            'token' => $token->id,
            'download' => 1,
        ]);
    }

    /**
     * @return array<int, array{
     *     payment_id:int,
     *     title:string,
     *     meta:string,
     *     download_url:string
     * }>
     */
    private function orderReceiptLinks(StoreOrder $order, bool $isAccountView): array
    {
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice) {
            return [];
        }

        return $invoice->allocations
            ->filter(function ($allocation) use ($invoice): bool {
                if (((float) $allocation->allocated_amount) <= 0 || ! $allocation->customerPayment) {
                    return false;
                }

                return (string) ($allocation->customerPayment->kind ?? Payment::KIND_PAYMENT) === $invoice->expectedSettlementKind();
            })
            ->map(fn ($allocation): array => $this->paymentReceiptLink(
                $invoice,
                $allocation->customerPayment,
                $isAccountView,
                'Download Receipt #',
                abs(round((float) $allocation->allocated_amount, 2)),
            ))
            ->unique('payment_id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, array{
     *     payment_id:int,
     *     title:string,
     *     meta:string,
     *     download_url:string
     * }>>
     */
    private function orderRefundReceiptLinksByInvoiceLineId(StoreOrder $order, bool $isAccountView): array
    {
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice) {
            return [];
        }

        $taxAdjustmentIdsByInvoiceLineId = $invoice->taxAdjustments
            ->flatMap(function ($adjustment) {
                return $adjustment->lines->map(function ($line) use ($adjustment): array {
                    return [
                        'invoice_line_id' => (int) ($line->invoice_line_id ?? 0),
                        'tax_adjustment_id' => (int) $adjustment->id,
                    ];
                });
            })
            ->filter(fn (array $row): bool => $row['invoice_line_id'] > 0 && $row['tax_adjustment_id'] > 0)
            ->groupBy('invoice_line_id')
            ->map(fn ($rows): array => collect($rows)
                ->pluck('tax_adjustment_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all())
            ->all();

        if ($taxAdjustmentIdsByInvoiceLineId === []) {
            return [];
        }

        $refundAllocationsByAdjustmentId = $invoice->allocations
            ->filter(function ($allocation): bool {
                return ((float) $allocation->allocated_amount) < 0
                    && $allocation->customerPayment instanceof Payment
                    && (int) ($allocation->tax_adjustment_id ?? 0) > 0;
            })
            ->groupBy(fn ($allocation): int => (int) $allocation->tax_adjustment_id);

        $linksByInvoiceLineId = [];

        foreach ($taxAdjustmentIdsByInvoiceLineId as $invoiceLineId => $taxAdjustmentIds) {
            $links = collect($taxAdjustmentIds)
                ->flatMap(fn (int $taxAdjustmentId) => $refundAllocationsByAdjustmentId->get($taxAdjustmentId, collect()))
                ->flatMap(function ($allocation) {
                    $payment = $allocation->customerPayment;
                    if (! $payment instanceof Payment) {
                        return [];
                    }

                    if ($payment->isRefund()) {
                        return [$payment];
                    }

                    return $payment->refunds
                        ->sortByDesc('received_on')
                        ->sortByDesc('id')
                        ->values()
                        ->all();
                })
                ->map(fn (Payment $payment): array => $this->paymentReceiptLink(
                    $invoice,
                    $payment,
                    $isAccountView,
                    'Refund Receipt #',
                    abs(round((float) $payment->total_amount, 2)),
                ))
                ->unique('payment_id')
                ->values()
                ->all();

            if ($links !== []) {
                $linksByInvoiceLineId[(int) $invoiceLineId] = $links;
            }
        }

        return $linksByInvoiceLineId;
    }

    /**
     * @return array{
     *     payment_id:int,
     *     title:string,
     *     meta:string,
     *     download_url:string
     * }
     */
    private function paymentReceiptLink(
        Invoice $invoice,
        Payment $payment,
        bool $isAccountView,
        string $titlePrefix,
        ?float $amount = null
    ): array {
        $baseRoute = $isAccountView ? 'account.invoice.receipt.pdf' : 'invoice.receipt.pdf';
        $downloadUrl = $isAccountView
            ? route($baseRoute, ['invoice' => $invoice, 'payment' => $payment, 'download' => 1])
            : URL::signedRoute($baseRoute, ['invoice' => $invoice, 'payment' => $payment, 'download' => 1]);
        $receivedOn = $payment->received_on?->format('M j, Y');

        return [
            'payment_id' => (int) $payment->id,
            'title' => $titlePrefix.(string) $payment->id,
            'meta' => trim(collect([
                $receivedOn,
                $amount !== null ? '$'.number_format($amount, 2) : null,
            ])->filter()->implode(' · ')),
            'download_url' => $downloadUrl,
        ];
    }

    private function resolveOrderInvoiceAccessToken(Invoice $invoice, StoreOrder $order): Token
    {
        $token = Token::query()
            ->where('type', 'invoice-access')
            ->where('expires_at', '>', now()->addDays(7))
            ->get()
            ->first(function (Token $candidate) use ($invoice, $order): bool {
                return (int) ($candidate->data['invoice_id'] ?? 0) === (int) $invoice->id
                    && (string) ($candidate->data['store_order_access_token'] ?? '') === (string) $order->access_token;
            });

        if ($token instanceof Token) {
            return $token;
        }

        /** @var Token $createdToken */
        $createdToken = Token::query()->create([
            'user_id' => $order->user_id ?: null,
            'type' => 'invoice-access',
            'data' => [
                'invoice_id' => (int) $invoice->id,
                'store_order_id' => (int) $order->id,
                'store_order_access_token' => (string) $order->access_token,
            ],
            'expires_at' => now()->addDays(30),
        ]);

        return $createdToken;
    }

    private function canDownloadOrderDocuments(Request $request, StoreOrder $order, bool $isAccountView): bool
    {
        if ($isAccountView) {
            return true;
        }

        if ($order->isAccessibleBy($request->user())) {
            return true;
        }

        return $this->sessionHasOrderDocumentAccess($request, $order);
    }

    private function canViewOrderAddressDetails(Request $request, StoreOrder $order, bool $isAccountView): bool
    {
        return $this->canDownloadOrderDocuments($request, $order, $isAccountView);
    }

    private function sessionHasOrderDocumentAccess(Request $request, StoreOrder $order): bool
    {
        $accessToken = trim((string) ($order->access_token ?? ''));
        if ($accessToken === '') {
            return false;
        }

        return collect((array) $request->session()->get(self::ORDER_DOCUMENT_ACCESS_SESSION_KEY, []))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->contains($accessToken);
    }

    private function rememberGuestOrderDocumentAccess(Request $request, StoreOrder $order): void
    {
        if ($request->user()) {
            return;
        }

        $accessToken = trim((string) ($order->access_token ?? ''));
        if ($accessToken === '') {
            return;
        }

        $tokens = collect((array) $request->session()->get(self::ORDER_DOCUMENT_ACCESS_SESSION_KEY, []))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->push($accessToken)
            ->unique()
            ->take(-20)
            ->values()
            ->all();

        $request->session()->put(self::ORDER_DOCUMENT_ACCESS_SESSION_KEY, $tokens);
    }
}
