<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Jobs\SendDeferredStoreOrderEmail;
use App\Mail\InvoiceDocumentBundle;
use App\Mail\PaymentReceiptPdf;
use App\Mail\StoreQuoteRequestAdminNotification;
use App\Mail\StoreOrderAdminUpdateNotice;
use App\Mail\StoreOrderAdminNotification;
use App\Mail\StoreOrderConfirmation;
use App\Mail\StoreOrderCustomerUpdateNotice;
use App\Mail\StoreOrderPaid;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\SquareRefundOperation;
use App\Models\SentEmail;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCancellation;
use App\Models\StoreOrderItemTracking;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Support\ShopShippingSettings;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreOrderService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly SquareApiService $squareApi,
        private readonly StoreShippingService $shipping,
        private readonly StoreCouponService $coupons,
        private readonly StoreInventoryAllocatorService $allocator,
        private readonly StoreOrderUpdateService $orderUpdates,
        private readonly AccountCreditService $accountCredit,
    ) {}

    public function createFromCart(Collection $lines, array $payload, ?User $authUser = null): StoreOrder
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $customer = $this->normalizeCustomerPayload($payload);
        $user = $this->resolveUser($customer, $authUser);

        /** @var StoreOrder $order */
        $order = DB::transaction(function () use ($lines, $customer, $user): StoreOrder {
            $checkout = $this->prepareCheckout($lines, $customer, $user);

            return $this->createOrderRecords($checkout['lines'], $customer, $user, $checkout['totals']);
        });

        $this->syncOrderState($order);
        $this->updateUserProfileFromOrder($user, $customer, $authUser);
        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        $this->queueOrderConfirmationEmail($freshOrder);
        $this->queueAdminOrderNotification($freshOrder, 'created');

        return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
    }

    public function createAndChargeFromCart(Collection $lines, array $payload, ?string $sourceId, ?User $authUser = null): StoreOrder
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $customer = $this->normalizeCustomerPayload($payload);
        $user = $this->resolveUser($customer, $authUser);

        /** @var StoreOrder $order */
        $order = DB::transaction(function () use ($lines, $customer, $user, $sourceId, $authUser): StoreOrder {
            $checkout = $this->prepareCheckout($lines, $customer, $user);
            $totals = $checkout['totals'];

            if ((float) ($totals['total'] ?? 0) <= 0.0001) {
                throw ValidationException::withMessages([
                    'source_id' => 'This checkout does not require card payment.',
                ]);
            }

            $order = $this->createOrderRecords($checkout['lines'], $customer, $user, $totals);
            $invoice = $order->invoice;

            if (! $invoice instanceof Invoice) {
                throw ValidationException::withMessages([
                    'source_id' => 'This order cannot be paid because the invoice record is missing.',
                ]);
            }

            $creditApplied = $authUser instanceof User
                ? $this->accountCredit->applyCreditToInvoice($invoice, $authUser, (float) $invoice->outstandingAmount())
                : 0.0;
            if ($creditApplied > 0.0001) {
                $invoice->refresh();
                $order->refresh();
                $order->load('invoice');
            }

            $remainingAmount = round((float) $invoice->outstandingAmount(), 2);
            if ($remainingAmount > 0.0001 && trim((string) ($sourceId ?? '')) === '') {
                throw ValidationException::withMessages([
                    'source_id' => 'Card details are required.',
                ]);
            }

            if ($remainingAmount > 0.0001) {
                $locationId = trim((string) config('services.square.location_id'));
                if (! $this->squareApi->isEnabled()) {
                    throw ValidationException::withMessages([
                        'source_id' => 'Credit card payments are not available right now.',
                    ]);
                }
                if ($locationId === '') {
                    throw ValidationException::withMessages([
                        'source_id' => 'Square location is not configured.',
                    ]);
                }

                $this->chargeLockedOrder($order, $invoice, $sourceId, $locationId, $authUser);
            } else {
                $this->syncInvoiceState($invoice);
                $this->syncOrderState($order->fresh('invoice'));
            }

            return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
        });

        $this->updateUserProfileFromOrder($user, $customer, $authUser);
        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        $this->queueOrderPaidEmail($freshOrder);
        $this->queueAdminOrderNotification($freshOrder, 'paid');

        return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
    }

    public function createQuoteRequestFromCart(Collection $lines, array $payload, ?User $authUser = null): Quote
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $customer = $this->normalizeCustomerPayload($payload);
        $user = $this->resolveUser($customer, $authUser);

        /** @var Quote $quote */
        $quote = DB::transaction(function () use ($lines, $customer, $user): Quote {
            $checkout = $this->prepareCheckout($lines, $customer, $user, true);
            $quoteLineItems = $this->storeQuoteLineItems($checkout['lines'], $checkout['totals']);

            $quote = new Quote();
            $quote->quote_number = $this->documentNumbers->nextQuoteNumber();
            $quote->user_id = $user?->id;
            $quote->status = Quote::STATUS_DRAFT;
            $quote->context_type = Quote::CONTEXT_STORE_MANUAL_SHIPPING;
            $quote->quote_date = Carbon::today();
            $quote->title = 'Store quote request';
            $quote->description = '';
            $quote->line_items = $quoteLineItems;
            $quote->subtotal_amount = $this->calculateQuoteSubtotal($quoteLineItems);
            $quote->gst_amount = $this->calculateQuoteGst($quoteLineItems);
            $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);
            $quote->notes = $customer['notes'];
            $quote->context_payload = $this->storeQuoteContextPayload($customer, $checkout['totals']);
            $quote->setAcceptanceSettings(true, true);
            $quote->save();

            return $quote->fresh('user');
        });

        $this->updateUserProfileFromOrder($user, $customer, $authUser);
        $this->queueAdminQuoteRequestNotification($quote);

        return $quote->fresh('user');
    }

    public function createOrderFromStoreQuote(Quote $quote, Invoice $invoice): StoreOrder
    {
        if (! $quote->hasStoreProductLines()) {
            throw ValidationException::withMessages([
                'quote' => 'This quote does not contain any store products to convert into an order.',
            ]);
        }

        $quote->loadMissing('user');

        /** @var StoreOrder $order */
        $order = DB::transaction(function () use ($quote, $invoice): StoreOrder {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->with('lines')
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (StoreOrder::query()->where('invoice_id', $lockedInvoice->id)->exists()) {
                throw ValidationException::withMessages([
                    'quote' => 'A store order already exists for this invoice.',
                ]);
            }

            $context = is_array($quote->context_payload ?? null) ? $quote->context_payload : [];
            $customer = $this->normalizeCustomerPayload(is_array($context['customer'] ?? null) ? $context['customer'] : []);
            $lineItems = is_array($quote->line_items) ? array_values($quote->line_items) : [];
            $productLinePayloads = [];

            foreach ($lineItems as $index => $lineItem) {
                if (! is_array($lineItem) || (string) ($lineItem['kind'] ?? 'product') !== 'product') {
                    continue;
                }

                $storeContext = is_array($lineItem['store_context'] ?? null) ? $lineItem['store_context'] : [];
                $productId = (int) ($storeContext['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                /** @var Product $product */
                $product = Product::query()
                    ->with(['hero'])
                    ->lockForUpdate()
                    ->findOrFail($productId);

                $variantId = (int) ($storeContext['variant_id'] ?? 0);
                $variant = $variantId > 0
                    ? ProductVariant::query()->lockForUpdate()->find($variantId)
                    : null;

                $quantity = max(0, (int) ($lineItem['quantity'] ?? 0));
                if ($quantity <= 0) {
                    continue;
                }

                $actualInventory = $product->availableInventory($variant);
                $fulfilment = $this->resolveFulfilment($product, $quantity, $actualInventory, $variant);
                $displayTitle = trim((string) ($lineItem['description'] ?? '')) ?: $product->displayTitle($variant);
                $unitPriceExTax = round((float) ($lineItem['unit_price'] ?? 0), 2);
                $taxRate = ($lineItem['gst_applicable'] ?? true) === true ? 0.10 : 0.00;
                $lineTotalExTax = round((float) ($lineItem['line_total'] ?? ($unitPriceExTax * $quantity)), 2);

                $reservationLine = (object) [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'available_now_quantity' => (int) ($fulfilment['available_now_quantity'] ?? $quantity),
                    'display_title' => $displayTitle,
                ];

                $reservedQuantity = $this->reserveInventoryForPreparedLine($reservationLine);
                $invoiceLine = $lockedInvoice->lines->firstWhere('line_number', $index + 1);

                $productLinePayloads[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'invoice_line' => $invoiceLine instanceof InvoiceLine ? $invoiceLine : null,
                    'display_title' => $displayTitle,
                    'quantity' => $quantity,
                    'available_now_quantity' => (int) ($fulfilment['available_now_quantity'] ?? $quantity),
                    'delayed_quantity' => (int) ($fulfilment['delayed_quantity'] ?? 0),
                    'delayed_fulfilment_type' => $fulfilment['delayed_fulfilment_type'] ?? null,
                    'delayed_shipping_estimate' => $fulfilment['delayed_shipping_estimate'] ?? null,
                    'reserved_quantity' => $reservedQuantity,
                    'box_only' => (bool) ($storeContext['box_only'] ?? false),
                    'is_preorder' => (bool) ($product->isPreorder($variant)),
                    'preorder_shipping_estimate' => $product->isPreorder($variant)
                        ? ($variant->preorder_shipping_estimate ?? $product->preorder_shipping_estimate)
                        : null,
                    'unit_shipping_units' => round((float) ($product->shippingUnitsForVariant($variant) ?? ($storeContext['unit_shipping_units'] ?? 0)), 2),
                    'unit_min_satchel_rank' => $product->minSatchelRankForVariant($variant) ?? ($storeContext['unit_min_satchel_rank'] ?? null),
                    'unit_weight_grams' => $product->weightGramsForVariant($variant) ?? ($storeContext['unit_weight_grams'] ?? null),
                    'unit_price_inc_tax' => round($unitPriceExTax * (1 + $taxRate), 2),
                    'unit_price_ex_tax' => $unitPriceExTax,
                    'line_total_inc_tax' => round($lineTotalExTax * (1 + $taxRate), 2),
                    'line_total_ex_tax' => $lineTotalExTax,
                    'line_gst_amount' => round($lineTotalExTax * $taxRate, 2),
                    'tax_rate' => $taxRate,
                ];
            }

            if ($productLinePayloads === []) {
                throw ValidationException::withMessages([
                    'quote' => 'This store quote does not contain any product lines to convert into an order.',
                ]);
            }

            $shippingLines = $lockedInvoice->lines
                ->filter(fn (InvoiceLine $line): bool => (string) $line->kind === 'shipping')
                ->values();
            $discountLines = $lockedInvoice->lines
                ->filter(fn (InvoiceLine $line): bool => (string) $line->kind === 'discount')
                ->values();

            $containsDigital = collect($productLinePayloads)->contains(
                fn (array $payload): bool => $payload['product']->isDigital()
            );
            $containsPhysical = collect($productLinePayloads)->contains(
                fn (array $payload): bool => $payload['product']->isPhysical()
            );
            $containsPreorder = collect($productLinePayloads)->contains(
                fn (array $payload): bool => (bool) $payload['is_preorder']
            );
            $shippingAmount = round((float) $shippingLines->sum('line_total_inc_tax'), 2);
            $discountAmount = round(abs((float) $discountLines->sum('line_total_inc_tax')), 2);
            $shippingMethod = $shippingLines->count() === 1
                ? trim((string) $shippingLines->first()->description)
                : 'Quoted shipping';

            $order = new StoreOrder();
            $order->order_number = $this->documentNumbers->nextStoreOrderNumber();
            $order->access_token = Str::random(40);
            $order->user_id = $quote->user_id;
            $order->quote_id = $quote->id;
            $order->invoice_id = $lockedInvoice->id;
            $order->coupon_id = null;
            $order->status = (float) $lockedInvoice->total_amount <= 0.0001
                ? ($containsPhysical ? StoreOrder::STATUS_PROCESSING : StoreOrder::STATUS_FULFILLED)
                : StoreOrder::STATUS_PENDING_PAYMENT;
            $order->contains_digital = $containsDigital;
            $order->contains_physical = $containsPhysical;
            $order->contains_preorder = $containsPreorder;
            $order->split_shipments = false;
            $order->consolidate_shipments = false;
            $order->shipment_count = 1;
            $order->preorder_acknowledged = $containsPreorder;
            $order->billing_name = $customer['billing_name'];
            $order->billing_email = $customer['billing_email'];
            $order->billing_phone = $customer['billing_phone'];
            $order->billing_company = $customer['billing_company'];
            $order->shipping_name = $customer['shipping_name'];
            $order->shipping_phone = $customer['shipping_phone'];
            $order->shipping_address = $customer['shipping_address'];
            $order->shipping_address2 = $customer['shipping_address2'];
            $order->shipping_city = $customer['shipping_city'];
            $order->shipping_state = $customer['shipping_state'];
            $order->shipping_postcode = $customer['shipping_postcode'];
            $order->shipping_country = $customer['shipping_country'];
            $order->shipping_method = $shippingMethod !== '' ? $shippingMethod : null;
            $order->shipping_method_code = null;
            $order->shipping_package_summary = null;
            $order->shipping_breakdown_data = null;
            $order->shipping_zone = null;
            $order->shipping_chargeable_weight_grams = (int) collect($productLinePayloads)
                ->sum(fn (array $payload): int => (int) $payload['unit_weight_grams'] * (int) $payload['quantity']);
            $order->coupon_code = trim((string) ($context['coupon_code'] ?? '')) ?: null;
            $order->coupon_type = trim((string) ($context['coupon_type'] ?? '')) ?: null;
            $order->notes = trim(implode("\n", array_filter([
                (string) ($customer['notes'] ?? ''),
                (string) ($quote->private_notes ?? ''),
            ])));
            $order->subtotal_amount = round((float) collect($productLinePayloads)->sum('line_total_inc_tax'), 2);
            $order->shipping_amount = $shippingAmount;
            $order->discount_amount = $discountAmount;
            $order->gst_amount = round((float) $lockedInvoice->gst_amount, 2);
            $order->total_amount = round((float) $lockedInvoice->total_amount, 2);
            $order->paid_at = (float) $lockedInvoice->outstandingAmount() <= 0.0001 ? now() : null;
            $order->fulfilled_at = (float) $lockedInvoice->outstandingAmount() <= 0.0001 && ! $containsPhysical ? now() : null;
            $order->save();

            foreach ($productLinePayloads as $payload) {
                /** @var Product $product */
                $product = $payload['product'];
                /** @var ProductVariant|null $variant */
                $variant = $payload['variant'];
                /** @var InvoiceLine|null $invoiceLine */
                $invoiceLine = $payload['invoice_line'];

                $orderItem = new StoreOrderItem();
                $orderItem->store_order_id = $order->id;
                $orderItem->product_id = $product->id;
                $orderItem->product_variant_id = $variant?->id;
                $orderItem->invoice_line_id = $invoiceLine?->id;
                $orderItem->product_title = (string) $product->title;
                $orderItem->product_slug = (string) $product->slug;
                $orderItem->variant_name = $product->variantDisplayName($variant);
                $orderItem->product_sku = $product->sku;
                $orderItem->variant_sku = $variant instanceof ProductVariant ? $variant->sku : null;
                $orderItem->product_type = (string) $product->product_type;
                $orderItem->box_only = (bool) $payload['box_only'];
                $orderItem->is_preorder = (bool) $payload['is_preorder'];
                $orderItem->preorder_shipping_estimate = $payload['preorder_shipping_estimate'];
                $orderItem->quantity = (int) $payload['quantity'];
                $orderItem->available_now_quantity = (int) $payload['available_now_quantity'];
                $orderItem->delayed_quantity = (int) $payload['delayed_quantity'];
                $orderItem->delayed_fulfilment_type = $payload['delayed_fulfilment_type'];
                $orderItem->delayed_shipping_estimate = $payload['delayed_shipping_estimate'];
                $orderItem->inventory_reserved_quantity = (int) $payload['reserved_quantity'];
                $orderItem->unit_shipping_units = round((float) $payload['unit_shipping_units'], 2);
                $orderItem->unit_min_satchel_rank = $payload['unit_min_satchel_rank'];
                $orderItem->unit_price = round((float) $payload['unit_price_inc_tax'], 2);
                $orderItem->unit_shipping_rate = 0;
                $orderItem->tax_rate = (float) $payload['tax_rate'];
                $orderItem->unit_weight_grams = $payload['unit_weight_grams'];
                $orderItem->unit_length_cm = null;
                $orderItem->unit_width_cm = null;
                $orderItem->unit_height_cm = null;
                $orderItem->line_price_amount = round((float) $payload['line_total_inc_tax'], 2);
                $orderItem->line_shipping_amount = 0;
                $orderItem->line_gst_amount = round((float) $payload['line_gst_amount'], 2);
                $orderItem->line_total_amount = round((float) $payload['line_total_inc_tax'], 2);
                $orderItem->save();

                if ($product->isDigital()) {
                    foreach ($product->downloadMedia()->get() as $index => $media) {
                        $orderItem->downloads()->create([
                            'media_name' => (string) $media->name,
                            'title' => (string) ($media->title ?? $media->name),
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            return $order->load(['invoice', 'items.downloads.media', 'coupon']);
        });

        return $order;
    }

    public function sendInvoiceDocumentBundleToCustomer(
        Invoice $invoice,
        ?string $recipient = null,
        ?string $recipientName = null,
        ?User $actingUser = null,
    ): bool {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return false;
        }

        $recipient = strtolower(trim((string) ($recipient ?: $invoice->billing_email ?: $invoice->user?->email)));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $invoice->loadMissing('user', 'lines', 'allocations.customerPayment', 'storeOrders');
        $invoicePdf = $this->buildInvoicePdf($invoice)->output();
        if ($invoicePdf === '') {
            return false;
        }

        [$initiatedByEmail, $initiatedByName] = $this->mailInitiatorIdentity($actingUser);
        $linkedOrderNumber = $invoice->storeOrders
            ->sortByDesc(fn (StoreOrder $order) => optional($order->created_at)->timestamp ?? (int) $order->id)
            ->first()?->order_number;

        dispatch(new SendEmail(
            $recipient,
            new InvoiceDocumentBundle(
                recipientName: trim((string) ($recipientName ?: $invoice->user?->getName() ?: $invoice->billing_name ?: $recipient)),
                invoiceNumber: (string) $invoice->invoice_number,
                orderNumber: $linkedOrderNumber !== null ? (string) $linkedOrderNumber : null,
                attachments: [[
                    'filename' => $this->invoicePdfFilename($invoice),
                    'content' => $invoicePdf,
                    'mime' => 'application/pdf',
                ]],
                outstandingAmount: $invoice->outstandingAmount(),
                payUrl: $invoice->outstandingAmount() > 0.0001 ? route('invoice.public.pay.show', $invoice) : null,
                initiatedByEmail: $initiatedByEmail,
                initiatedByName: $initiatedByName,
            )
        ))->onQueue('mail');

        return true;
    }

    public function sendOrderConfirmationEmailToCustomer(StoreOrder $order, ?User $actingUser = null): bool
    {
        return $this->queueOrderConfirmationEmail($order, $actingUser);
    }

    public function sendOrderPaidEmailToCustomer(StoreOrder $order, ?User $actingUser = null): bool
    {
        return $this->queueOrderPaidEmail($order, $actingUser);
    }

    public function queueDeferredOrderEmailToCustomer(StoreOrder $order, int $delayMinutes = 10): bool
    {
        $delayMinutes = max(1, $delayMinutes);
        $scheduledFor = now()->addMinutes($delayMinutes);

        $recipient = strtolower(trim((string) $order->billing_email));
        if ($recipient === '') {
            return false;
        }

        $scheduledEmail = SentEmail::query()->create([
            'recipient' => $recipient,
            'mailable_class' => SendDeferredStoreOrderEmail::class,
            'status' => SentEmail::STATUS_SCHEDULED,
            'scheduled_for_at' => $scheduledFor,
        ]);

        dispatch(new SendDeferredStoreOrderEmail($order->id, (string) $scheduledEmail->id))
            ->delay($scheduledFor);

        return true;
    }

    public function charge(StoreOrder $order, string $sourceId, ?User $actingUser = null): Payment
    {
        if (! $this->squareApi->isEnabled()) {
            throw ValidationException::withMessages([
                'source_id' => 'Credit card payments are not available right now.',
            ]);
        }

        $locationId = trim((string) config('services.square.location_id'));
        if ($locationId === '') {
            throw ValidationException::withMessages([
                'source_id' => 'Square location is not configured.',
            ]);
        }

        $paymentId = DB::transaction(function () use ($order, $sourceId, $locationId, $actingUser): int {
            /** @var StoreOrder $lockedOrder */
            $lockedOrder = StoreOrder::query()
                ->with('invoice')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $lockedOrder->invoice;
            if (! $invoice instanceof Invoice) {
                throw ValidationException::withMessages([
                    'source_id' => 'This order cannot be paid because the invoice record is missing.',
                ]);
            }

            return $this->chargeLockedOrder($lockedOrder, $invoice, trim($sourceId), $locationId, $actingUser);
        });

        $payment = Payment::query()->findOrFail($paymentId);
        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        $this->queueOrderPaidEmail($freshOrder);
        $this->queueAdminOrderNotification($freshOrder, 'paid');

        return $payment;
    }

    public function updateOrderStatus(
        StoreOrder $order,
        string $status,
        ?string $notes = null,
        ?string $publicNotes = null,
        bool $suppressAdminNotifications = false
    ): StoreOrder
    {
        $result = DB::transaction(function () use ($order, $status, $notes, $publicNotes): array {
            /** @var StoreOrder $lockedOrder */
            $lockedOrder = StoreOrder::query()
                ->with('items')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $lockedOrder->status;
            $previousPublicNotes = trim((string) ($lockedOrder->public_notes ?? ''));
            $targetStatus = $this->allOrderItemsCancelled($lockedOrder->items)
                ? StoreOrder::STATUS_CANCELLED
                : $status;
            $eventIds = [];
            $releasedInventorySources = [];
            if ($previousStatus !== StoreOrder::STATUS_CANCELLED && $targetStatus === StoreOrder::STATUS_CANCELLED) {
                $releasedInventorySources = $this->releaseInventoryReservations($lockedOrder);
            }

            if ($previousStatus === StoreOrder::STATUS_CANCELLED && $targetStatus !== StoreOrder::STATUS_CANCELLED) {
                $this->reserveInventoryForExistingOrder($lockedOrder);
            }

            $lockedOrder->status = $targetStatus;
            $lockedOrder->notes = trim((string) $notes) ?: null;
            $lockedOrder->public_notes = trim((string) $publicNotes) ?: null;
            if (in_array((string) $lockedOrder->status, StoreOrder::FULFILMENT_STATUSES, true)) {
                $lockedOrder->fulfilled_at ??= now();
            } else {
                $lockedOrder->fulfilled_at = null;
            }
            $lockedOrder->save();

            if ($targetStatus !== StoreOrder::STATUS_CANCELLED) {
                $this->syncOrderState($lockedOrder->fresh('invoice'));
                $lockedOrder = StoreOrder::query()
                    ->with('items')
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($previousStatus !== (string) $lockedOrder->status) {
                $statusUpdate = $this->orderUpdates->recordStatusChange($lockedOrder, $previousStatus, (string) $lockedOrder->status);
                if ($statusUpdate !== null && (int) $statusUpdate->id > 0) {
                    $eventIds[] = (int) $statusUpdate->id;
                }
            }

            $newPublicNotes = trim((string) ($lockedOrder->public_notes ?? ''));
            if ($newPublicNotes !== '' && $newPublicNotes !== $previousPublicNotes) {
                $publicNoteUpdate = $this->orderUpdates->recordPublicNoteUpdate($lockedOrder, $newPublicNotes);
                if ($publicNoteUpdate !== null && (int) $publicNoteUpdate->id > 0) {
                    $eventIds[] = (int) $publicNoteUpdate->id;
                }
            }

            $this->allocateInventoryForSources($releasedInventorySources);

            return [
                'order' => $lockedOrder->fresh(['invoice', 'items.downloads.media', 'items.product.hero', 'items.variant', 'user', 'coupon']),
                'event_ids' => array_values(array_unique($eventIds)),
            ];
        });

        $freshOrder = $result['order'];
        if ($freshOrder instanceof StoreOrder && ($result['event_ids'] ?? []) !== []) {
            $this->queueImmediateOrderUpdateNotifications($freshOrder, (array) $result['event_ids'], $suppressAdminNotifications);
        }

        return $freshOrder;
    }

    public function cancelOrderItemQuantities(
        StoreOrder $order,
        StoreOrderItem $item,
        int $availableQuantity = 0,
        int $delayedQuantity = 0,
        ?string $reason = null,
        ?User $actingUser = null,
        bool $suppressAdminNotifications = false
    ): array {
        $summary = DB::transaction(function () use ($order, $item, $availableQuantity, $delayedQuantity, $reason, $actingUser): array {
            $lockedItem = $this->lockOrderItemForUpdate($order, $item);
            $releasedReservedQuantity = 0;

            if ((string) $lockedItem->order->status === StoreOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'available_quantity' => 'This order has already been cancelled.',
                ]);
            }

            $requestedQuantity = max(0, $availableQuantity) + max(0, $delayedQuantity);

            if ($requestedQuantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Enter a quantity to cancel.',
                ]);
            }

            $reason = $this->nullableTrimmedString($reason);
            if ($reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Enter a reason for the cancellation.',
                ]);
            }

            if ($requestedQuantity > $lockedItem->remainingFulfillableQuantity()) {
                throw ValidationException::withMessages([
                    'quantity' => 'The cancellation quantity exceeds what is still open on this item.',
                ]);
            }

            $resolvedQuantities = $this->resolveCancellationQuantities($lockedItem, $requestedQuantity);
            $availableQuantity = (int) $resolvedQuantities['available_quantity'];
            $delayedQuantity = (int) $resolvedQuantities['delayed_quantity'];

            if ($availableQuantity > 0) {
                $lockedItem->cancelled_available_quantity = $lockedItem->cancelledAvailableQuantity() + $availableQuantity;
                $releasedReservedQuantity = min($availableQuantity, max(0, (int) $lockedItem->inventory_reserved_quantity));
                $lockedItem->inventory_reserved_quantity = max(0, (int) $lockedItem->inventory_reserved_quantity - $releasedReservedQuantity);
                $this->restoreInventoryQuantity($lockedItem, $releasedReservedQuantity);
            }

            if ($delayedQuantity > 0) {
                $lockedItem->cancelled_delayed_quantity = $lockedItem->cancelledDelayedQuantity() + $delayedQuantity;
            }

            $lockedItem->save();

            $cancellation = new StoreOrderItemCancellation();
            $cancellation->store_order_item_id = $lockedItem->id;
            $cancellation->cancelled_by_user_id = $actingUser?->id;
            $cancellation->available_quantity = $availableQuantity;
            $cancellation->delayed_quantity = $delayedQuantity;
            $cancellation->reason = $reason;
            $cancellation->save();

            $orderItems = StoreOrderItem::query()
                ->with('invoiceLine')
                ->where('store_order_id', $lockedItem->order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $invoice = $lockedItem->order->invoice_id
                ? Invoice::query()
                    ->with(['lines', 'allocations.customerPayment', 'user'])
                    ->whereKey($lockedItem->order->invoice_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $adjustment = null;
            $reconciliation = [
                'allocated' => 0.0,
                'remaining' => 0.0,
                'consumed_outstanding' => 0.0,
            ];

            $originallyPaid = $invoice instanceof Invoice
                ? $invoice->settledAmount() >= ($invoice->dueAmount() - 0.0001)
                : false;

            if ($invoice instanceof Invoice) {
                $creditLines = collect();
                $cancelledQuantity = $availableQuantity + $delayedQuantity;

                $itemCreditLine = $this->buildProductCreditLineForOrderItemCancellation(
                    $lockedItem->order,
                    $lockedItem,
                    $invoice,
                    $cancelledQuantity,
                    $orderItems,
                );
                if ($itemCreditLine !== null) {
                    $creditLines->push($itemCreditLine);
                }

                $shippingCreditLine = $this->buildShippingCreditLineForOrderCancellation(
                    $lockedItem->order,
                    $invoice,
                    $orderItems,
                );
                if ($shippingCreditLine !== null) {
                    $creditLines->push($shippingCreditLine);
                }

                if ($creditLines->isNotEmpty()) {
                    $adjustment = $this->createTaxAdjustmentNoteForOrderItemCancellation(
                        $lockedItem->order,
                        $lockedItem,
                        $invoice,
                        $creditLines,
                        $reason,
                    );
                    $reconciliation = $this->reconcileCreditAllocationsForAdjustment($invoice, $adjustment);
                    $this->syncInvoiceState($invoice);
                }
            }

            $this->syncOrderState($lockedItem->order->fresh('invoice'));

            $update = $this->orderUpdates->recordItemCancellation($lockedItem->order, $lockedItem, $cancellation);
            if ($releasedReservedQuantity > 0) {
                $this->allocator->allocateForOrderItem($lockedItem);
            }

            $expectedRefundCents = $adjustment instanceof TaxAdjustment
                ? max(0, (int) round(((float) ($reconciliation['allocated'] ?? 0)) * 100))
                : 0;
            $hasSquarePayment = $invoice instanceof Invoice
                ? $this->invoiceHasRefundableSquarePayment($invoice)
                : false;
            $refundOperationIds = $adjustment instanceof TaxAdjustment
                ? $this->createSquareRefundOperationsForOrderCancellation(
                    $invoice,
                    $adjustment,
                    $lockedItem->order,
                    $cancellation,
                    $expectedRefundCents,
                )
                : [];

            return [
                'item' => $lockedItem->fresh(['product.hero', 'variant', 'downloads.media', 'trackingEntries', 'cancellations.cancelledBy', 'order']),
                'order_id' => (int) $lockedItem->order->id,
                'invoice_id' => $invoice?->id,
                'adjustment_id' => $adjustment?->id,
                'adjustment_note_number' => $adjustment?->adjustment_number,
                'event_id' => $update?->id,
                'expected_refund_cents' => $expectedRefundCents,
                'refund_operation_ids' => $refundOperationIds,
                'originally_paid' => $originallyPaid,
                'has_square_payment' => $hasSquarePayment,
            ];
        });

        $refundOutcome = [
            'refunded_cents' => 0,
            'refund_payment_ids' => [],
        ];

        if ((int) $summary['expected_refund_cents'] > 0) {
            $refundOutcome = $this->processSquareRefundOperations(
                (array) $summary['refund_operation_ids'],
                'Store order cancellation '.($summary['item'] instanceof StoreOrderItem ? (string) $summary['item']->order->order_number : (string) $order->order_number),
                $actingUser,
            );
        }

        $summary['refunded_cents'] = (int) $refundOutcome['refunded_cents'];
        $summary['refund_payment_ids'] = array_values(array_unique(array_map('intval', (array) $refundOutcome['refund_payment_ids'])));
        $summary['manual_refund_required'] = (bool) $summary['originally_paid']
            && (int) $summary['refunded_cents'] < (int) $summary['expected_refund_cents'];

        $freshOrder = StoreOrder::query()
            ->with(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon', 'user'])
            ->find((int) $summary['order_id']);

        if ($freshOrder instanceof StoreOrder && (int) ($summary['event_id'] ?? 0) > 0) {
            $this->queueImmediateOrderUpdateNotifications($freshOrder, [(int) $summary['event_id']], $suppressAdminNotifications);
        }

        $invoice = ($freshOrder?->invoice instanceof Invoice)
            ? $freshOrder->invoice
            : Invoice::query()->with(['user', 'lines', 'allocations.customerPayment'])->find((int) ($summary['invoice_id'] ?? 0));
        $adjustment = ($summary['adjustment_id'] ?? null)
            ? TaxAdjustment::query()->with('lines')->find((int) $summary['adjustment_id'])
            : null;

        if (
            $freshOrder instanceof StoreOrder
            && $invoice instanceof Invoice
            && $adjustment instanceof TaxAdjustment
            && $summary['refund_payment_ids'] === []
        ) {
            $this->sendOrderAdjustmentDocumentBundle($freshOrder, $invoice, $adjustment, $actingUser);
        }

        if ($freshOrder instanceof StoreOrder && $invoice instanceof Invoice && $summary['refund_payment_ids'] !== []) {
            $this->sendRefundReceiptEmailsForOrder($freshOrder, $invoice, $summary['refund_payment_ids']);
        }

        return $summary;
    }

    public function addOrderItemTracking(
        StoreOrder $order,
        StoreOrderItem $item,
        array $payload,
        bool $suppressAdminNotifications = false
    ): array
    {
        $summary = DB::transaction(function () use ($order, $item, $payload): array {
            $lockedItem = $this->lockOrderItemForUpdate($order, $item);

            if ((string) $lockedItem->order->status === StoreOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'quantity' => 'Tracking cannot be added to a cancelled order.',
                ]);
            }

            if (! $lockedItem->order->contains_physical || $lockedItem->isDigital()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Tracking can only be added to physical order items.',
                ]);
            }

            $shipmentType = (string) ($payload['shipment_type'] ?? StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE);
            if (! in_array($shipmentType, [
                StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
            ], true)) {
                throw ValidationException::withMessages([
                    'shipment_type' => 'Choose a valid shipment type.',
                ]);
            }

            $trackingMode = $this->normalizeTrackingMode($payload['tracking_mode'] ?? null, $payload['tracking_number'] ?? null, $payload['tracking_url'] ?? null);
            $quantity = max(0, (int) ($payload['quantity'] ?? 0));
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Enter a quantity to track.',
                ]);
            }

            $remainingQuantity = $shipmentType === StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED
                ? $lockedItem->remainingDelayedQuantity()
                : $lockedItem->remainingAvailableQuantity();

            if ($quantity > $remainingQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'The tracking quantity exceeds what is still open for that shipment stage.',
                ]);
            }

            $trackingNumber = trim((string) ($payload['tracking_number'] ?? ''));
            $trackingUrl = trim((string) ($payload['tracking_url'] ?? ''));
            if ($trackingMode === 'tracking_number' && $trackingNumber === '') {
                throw ValidationException::withMessages([
                    'tracking_number' => 'Enter a tracking number for this shipment entry.',
                ]);
            }

            if ($trackingMode === 'none') {
                $trackingNumber = '';
                $trackingUrl = '';
            }

            if ($trackingNumber !== '' && $trackingUrl === '') {
                $trackingUrl = $this->resolveTrackingUrl((string) ($payload['carrier'] ?? ''), $trackingNumber) ?? '';
            }

            if ($shipmentType === StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) {
                $lockedItem->inventory_reserved_quantity = max(
                    0,
                    (int) $lockedItem->inventory_reserved_quantity - min($quantity, max(0, (int) $lockedItem->inventory_reserved_quantity))
                );
                $lockedItem->save();
            }

            $tracking = new StoreOrderItemTracking();
            $tracking->store_order_item_id = $lockedItem->id;
            $tracking->shipment_type = $shipmentType;
            $tracking->quantity = $quantity;
            $tracking->parcel_number = $this->normalizeParcelNumber($payload['parcel_number'] ?? null);
            $tracking->carrier = $this->nullableTrimmedString($payload['carrier'] ?? null);
            $tracking->tracking_number = $trackingNumber !== '' ? $trackingNumber : null;
            $tracking->tracking_url = $trackingUrl !== '' ? $trackingUrl : null;
            $tracking->notes = $this->nullableTrimmedString($payload['notes'] ?? null);
            $tracking->dispatched_at = isset($payload['dispatched_at']) && trim((string) $payload['dispatched_at']) !== ''
                ? Carbon::parse((string) $payload['dispatched_at'])
                : now();
            $tracking->save();
            $update = $this->orderUpdates->recordTrackingAdded($lockedItem->order, $lockedItem, $tracking);
            $this->syncOrderState($lockedItem->order->fresh('invoice'));

            return [
                'tracking' => $tracking->fresh('orderItem.order'),
                'event_id' => $update?->id,
                'order_id' => (int) $lockedItem->order->id,
            ];
        });

        $freshOrder = StoreOrder::query()
            ->with(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon', 'user'])
            ->find((int) ($summary['order_id'] ?? 0));
        if ($freshOrder instanceof StoreOrder && (int) ($summary['event_id'] ?? 0) > 0) {
            $this->queueImmediateOrderUpdateNotifications($freshOrder, [(int) $summary['event_id']], $suppressAdminNotifications);
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array{
     *     order_id:int,
     *     invoice_id:int|null,
     *     adjustment_id:int|null,
     *     adjustment_note_number:string|null,
     *     event_ids:array<int, int>,
     *     expected_refund_cents:int,
     *     refund_operation_ids:array<int, int>,
     *     originally_paid:bool,
     *     has_square_payment:bool,
     *     refunded_cents:int,
     *     refund_payment_ids:array<int, int>,
     *     manual_refund_required:bool,
     *     item_action_count:int,
     *     cancellation_count:int,
     *     tracking_count:int
     * }
     */
    public function applyOrderItemActions(
        StoreOrder $order,
        array $actions,
        ?User $actingUser = null,
        bool $suppressAdminNotifications = false
    ): array
    {
        $normalizedActions = $this->normalizeQueuedOrderItemActions($actions);
        if ($normalizedActions === []) {
            return [
                'order_id' => (int) $order->id,
                'invoice_id' => $order->invoice_id ? (int) $order->invoice_id : null,
                'adjustment_id' => null,
                'adjustment_note_number' => null,
                'event_ids' => [],
                'expected_refund_cents' => 0,
                'refund_operation_ids' => [],
                'originally_paid' => false,
                'has_square_payment' => false,
                'refunded_cents' => 0,
                'refund_payment_ids' => [],
                'manual_refund_required' => false,
                'item_action_count' => 0,
                'cancellation_count' => 0,
                'tracking_count' => 0,
            ];
        }

        $summary = DB::transaction(function () use ($order, $normalizedActions, $actingUser): array {
            /** @var StoreOrder $lockedOrder */
            $lockedOrder = StoreOrder::query()
                ->with('invoice')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $orderItems = StoreOrderItem::query()
                ->with(['trackingEntries', 'invoiceLine'])
                ->where('store_order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (StoreOrderItem $item) => (int) $item->id);

            $invoice = $lockedOrder->invoice_id
                ? Invoice::query()
                    ->with(['lines', 'allocations.customerPayment', 'user'])
                    ->whereKey($lockedOrder->invoice_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $originallyPaid = $invoice instanceof Invoice
                ? $invoice->settledAmount() >= ($invoice->dueAmount() - 0.0001)
                : false;

            $releasedInventorySources = [];
            $eventIds = [];
            $cancellationSummary = [];
            $cancellationCount = 0;
            $trackingCount = 0;

            foreach ($normalizedActions as $index => $action) {
                $itemId = (int) ($action['item_id'] ?? 0);
                $item = $orderItems->get($itemId);
                if (! $item instanceof StoreOrderItem) {
                    throw ValidationException::withMessages([
                        'item_actions_json' => 'Queued action #'.($index + 1).' references an item that is not on this order.',
                    ]);
                }

                if ((string) ($action['type'] ?? '') === 'cancel') {
                    $result = $this->applyQueuedCancellationAction(
                        $lockedOrder,
                        $item,
                        $action,
                        $actingUser,
                        $index + 1,
                    );

                    if ((int) ($result['released_reserved_quantity'] ?? 0) > 0) {
                        $source = $this->inventorySourceForOrderItem($item);
                        if ($source !== null) {
                            $releasedInventorySources[] = $source;
                        }
                    }

                    $summaryRow = $cancellationSummary[$itemId] ?? [
                        'available_quantity' => 0,
                        'delayed_quantity' => 0,
                        'reasons' => [],
                    ];
                    $summaryRow['available_quantity'] += max(0, (int) ($result['available_quantity'] ?? 0));
                    $summaryRow['delayed_quantity'] += max(0, (int) ($result['delayed_quantity'] ?? 0));
                    $reason = trim((string) ($result['reason'] ?? ''));
                    if ($reason !== '') {
                        $summaryRow['reasons'][] = $reason;
                    }
                    $cancellationSummary[$itemId] = $summaryRow;
                    $cancellationCount++;
                } else {
                    $result = $this->applyQueuedTrackingAction(
                        $lockedOrder,
                        $item,
                        $action,
                        $index + 1,
                    );
                    $trackingCount++;
                }

                if ((int) ($result['event_id'] ?? 0) > 0) {
                    $eventIds[] = (int) $result['event_id'];
                }
            }

            $adjustment = null;
            $reconciliation = [
                'allocated' => 0.0,
                'remaining' => 0.0,
                'consumed_outstanding' => 0.0,
            ];

            if ($invoice instanceof Invoice && $cancellationSummary !== []) {
                $creditLines = collect();
                $itemsCollection = $orderItems->values();

                foreach ($cancellationSummary as $itemId => $itemSummary) {
                    $item = $orderItems->get((int) $itemId);
                    if (! $item instanceof StoreOrderItem) {
                        continue;
                    }

                    $cancelledQuantity = max(0, (int) $itemSummary['available_quantity'])
                        + max(0, (int) $itemSummary['delayed_quantity']);

                    $creditLine = $this->buildProductCreditLineForOrderItemCancellation(
                        $lockedOrder,
                        $item,
                        $invoice,
                        $cancelledQuantity,
                        $itemsCollection,
                    );

                    if ($creditLine === null) {
                        continue;
                    }

                    $reasons = collect((array) $itemSummary['reasons'])
                        ->map(fn ($reason) => trim((string) $reason))
                        ->filter()
                        ->unique()
                        ->values();

                    if ($reasons->isNotEmpty()) {
                        $creditLine['notes'] = trim(implode("\n", array_filter([
                            (string) ($creditLine['notes'] ?? ''),
                            ...$reasons->all(),
                        ])));
                    }

                    $creditLines->push($creditLine);
                }

                $shippingCreditLine = $this->buildShippingCreditLineForOrderCancellation(
                    $lockedOrder,
                    $invoice,
                    $orderItems->values(),
                );
                if ($shippingCreditLine !== null) {
                    $creditLines->push($shippingCreditLine);
                }

                if ($creditLines->isNotEmpty()) {
                    $adjustment = $this->createTaxAdjustmentNoteForOrderBatchCancellation(
                        $lockedOrder,
                        $invoice,
                        $creditLines,
                        $cancellationSummary,
                    );
                    $reconciliation = $this->reconcileCreditAllocationsForAdjustment($invoice, $adjustment);
                    $this->syncInvoiceState($invoice);
                }
            }

            $this->syncOrderState($lockedOrder->fresh('invoice'));

            return [
                'order_id' => (int) $lockedOrder->id,
                'invoice_id' => $invoice?->id,
                'adjustment_id' => $adjustment?->id,
                'adjustment_note_number' => $adjustment?->adjustment_number,
                'event_ids' => array_values(array_unique($eventIds)),
                'expected_refund_cents' => $adjustment instanceof TaxAdjustment
                    ? max(0, (int) round(((float) ($reconciliation['allocated'] ?? 0)) * 100))
                    : 0,
                'refund_operation_ids' => $adjustment instanceof TaxAdjustment
                    ? $this->createSquareRefundOperationsForOrderCancellation(
                        $invoice,
                        $adjustment,
                        $lockedOrder,
                        null,
                        max(0, (int) round(((float) ($reconciliation['allocated'] ?? 0)) * 100)),
                    )
                    : [],
                'originally_paid' => $originallyPaid,
                'has_square_payment' => $invoice instanceof Invoice
                    ? $this->invoiceHasRefundableSquarePayment($invoice)
                    : false,
                'released_inventory_sources' => array_values(array_unique($releasedInventorySources)),
                'item_action_count' => count($normalizedActions),
                'cancellation_count' => $cancellationCount,
                'tracking_count' => $trackingCount,
            ];
        });

        $this->allocateInventoryForSources((array) ($summary['released_inventory_sources'] ?? []));
        unset($summary['released_inventory_sources']);

        $refundOutcome = [
            'refunded_cents' => 0,
            'refund_payment_ids' => [],
        ];

        if ((int) ($summary['expected_refund_cents'] ?? 0) > 0) {
            $refundOutcome = $this->processSquareRefundOperations(
                (array) ($summary['refund_operation_ids'] ?? []),
                'Store order batch update '.$order->order_number,
                $actingUser,
            );
        }

        $summary['refunded_cents'] = (int) ($refundOutcome['refunded_cents'] ?? 0);
        $summary['refund_payment_ids'] = array_values(array_unique(array_map('intval', (array) ($refundOutcome['refund_payment_ids'] ?? []))));
        $summary['manual_refund_required'] = (bool) ($summary['originally_paid'] ?? false)
            && (int) ($summary['refunded_cents'] ?? 0) < (int) ($summary['expected_refund_cents'] ?? 0);

        $refundOperationIds = array_values(array_unique(array_map('intval', (array) ($summary['refund_operation_ids'] ?? []))));

        $freshOrder = StoreOrder::query()
            ->with(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon', 'user'])
            ->find((int) ($summary['order_id'] ?? 0));

        if ($freshOrder instanceof StoreOrder && ($summary['event_ids'] ?? []) !== []) {
            $this->queueImmediateOrderUpdateNotifications($freshOrder, (array) $summary['event_ids'], $suppressAdminNotifications);
        }

        $invoice = ($freshOrder?->invoice instanceof Invoice)
            ? $freshOrder->invoice
            : Invoice::query()->with(['user', 'lines', 'allocations.customerPayment'])->find((int) ($summary['invoice_id'] ?? 0));
        $adjustment = ($summary['adjustment_id'] ?? null)
            ? TaxAdjustment::query()->with('lines')->find((int) $summary['adjustment_id'])
            : null;

        if (
            (bool) ($summary['manual_refund_required'] ?? false)
            && $refundOperationIds === []
            && (int) ($summary['expected_refund_cents'] ?? 0) > 0
            && $invoice instanceof Invoice
        ) {
            $manualRefundOperation = $this->createManualRefundOperationForOrderCancellation(
                order: $order,
                invoice: $invoice,
                adjustment: $adjustment,
                requestedCents: (int) $summary['expected_refund_cents'],
                reason: 'Store order batch update '.$order->order_number,
                actingUser: $actingUser,
            );
            if ($manualRefundOperation instanceof SquareRefundOperation) {
                $summary['refund_operation_ids'] = [(int) $manualRefundOperation->id];
            }
        }

        if (
            $freshOrder instanceof StoreOrder
            && $invoice instanceof Invoice
            && $adjustment instanceof TaxAdjustment
            && ($summary['refund_payment_ids'] ?? []) === []
        ) {
            $this->sendOrderAdjustmentDocumentBundle($freshOrder, $invoice, $adjustment, $actingUser);
        }

        if ($freshOrder instanceof StoreOrder && $invoice instanceof Invoice && ($summary['refund_payment_ids'] ?? []) !== []) {
            $this->sendRefundReceiptEmailsForOrder($freshOrder, $invoice, (array) $summary['refund_payment_ids']);
        }

        return $summary;
    }

    public function syncOrderState(StoreOrder $order): void
    {
        $invoice = $order->relationLoaded('invoice') && $order->invoice instanceof Invoice
            ? $order->invoice
            : $order->invoice()->first();

        if (! $invoice instanceof Invoice) {
            if ((string) $order->status === StoreOrder::STATUS_QUOTE_REQUESTED) {
                return;
            }

            return;
        }

        if ((string) $order->status === StoreOrder::STATUS_CANCELLED) {
            return;
        }

        $orderItems = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with('trackingEntries')->get();

        if ($this->allOrderItemsCancelled($orderItems)) {
            $order->status = StoreOrder::STATUS_CANCELLED;
            $order->fulfilled_at = null;
            $order->save();

            return;
        }

        $isPaid = $invoice->outstandingAmount() <= 0.0001;

        if (! $isPaid) {
            $order->status = StoreOrder::STATUS_PENDING_PAYMENT;
            $order->paid_at = null;
            $order->fulfilled_at = null;
            $order->save();

            return;
        }

        $order->paid_at ??= now();

        if ($order->contains_physical) {
            if ($order->usesPickup()) {
                if (! in_array((string) $order->status, [
                    StoreOrder::STATUS_READY_FOR_PICKUP,
                    StoreOrder::STATUS_COLLECTED,
                ], true)) {
                    $order->status = StoreOrder::STATUS_PROCESSING;
                }
            } elseif ($order->isFullyShippedByEntries()) {
                $order->status = StoreOrder::STATUS_SHIPPED;
            } elseif ($order->isPartiallyShippedByEntries()) {
                $order->status = StoreOrder::STATUS_PARTIALLY_SHIPPED;
            } else {
                $order->status = StoreOrder::STATUS_PROCESSING;
            }
        } else {
            $order->status = StoreOrder::STATUS_FULFILLED;
            $order->fulfilled_at ??= now();
        }

        if (in_array((string) $order->status, StoreOrder::FULFILMENT_STATUSES, true)) {
            $order->fulfilled_at ??= now();
        } else {
            $order->fulfilled_at = null;
        }

        $order->save();
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQueuedOrderItemActions(array $actions): array
    {
        return collect($actions)
            ->values()
            ->map(function ($action, int $index): array {
                if (! is_array($action)) {
                    throw ValidationException::withMessages([
                        'item_actions_json' => 'Queued action #'.($index + 1).' is invalid.',
                    ]);
                }

                $type = trim((string) ($action['type'] ?? ''));
                $itemId = max(0, (int) ($action['item_id'] ?? 0));
                if ($itemId <= 0) {
                    throw ValidationException::withMessages([
                        'item_actions_json' => 'Queued action #'.($index + 1).' is missing an order item.',
                    ]);
                }

                if ($type === 'cancel') {
                    $requestedQuantity = max(
                        0,
                        (int) ($action['quantity'] ?? 0),
                        max(0, (int) ($action['available_quantity'] ?? 0)) + max(0, (int) ($action['delayed_quantity'] ?? 0)),
                    );

                    return [
                        'type' => 'cancel',
                        'item_id' => $itemId,
                        'quantity' => $requestedQuantity,
                        'available_quantity' => max(0, (int) ($action['available_quantity'] ?? 0)),
                        'delayed_quantity' => max(0, (int) ($action['delayed_quantity'] ?? 0)),
                        'reason' => trim((string) ($action['reason'] ?? '')),
                    ];
                }

                if ($type === 'tracking') {
                    $shipmentType = trim((string) ($action['shipment_type'] ?? StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE));
                    if (! in_array($shipmentType, [
                        StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                        StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
                    ], true)) {
                        throw ValidationException::withMessages([
                            'item_actions_json' => 'Queued tracking action #'.($index + 1).' has an invalid shipment stage.',
                        ]);
                    }

                    $trackingMode = $this->normalizeTrackingMode(
                        $action['tracking_mode'] ?? null,
                        $action['tracking_number'] ?? null,
                        $action['tracking_url'] ?? null
                    );
                    $trackingUrl = trim((string) ($action['tracking_url'] ?? ''));
                    if ($trackingUrl !== '' && ! filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
                        throw ValidationException::withMessages([
                            'item_actions_json' => 'Queued tracking action #'.($index + 1).' has an invalid tracking URL.',
                        ]);
                    }

                    $trackingNumber = trim((string) ($action['tracking_number'] ?? ''));
                    if ($trackingMode === 'tracking_number' && $trackingNumber === '') {
                        throw ValidationException::withMessages([
                            'item_actions_json' => 'Queued tracking action #'.($index + 1).' needs a tracking number.',
                        ]);
                    }

                    if ($trackingMode === 'none') {
                        $trackingNumber = '';
                        $trackingUrl = '';
                    }

                    return [
                        'type' => 'tracking',
                        'item_id' => $itemId,
                        'tracking_mode' => $trackingMode,
                        'shipment_type' => $shipmentType,
                        'quantity' => max(0, (int) ($action['quantity'] ?? 0)),
                        'parcel_number' => $this->normalizeParcelNumber($action['parcel_number'] ?? null),
                        'carrier' => trim((string) ($action['carrier'] ?? '')),
                        'tracking_number' => $trackingNumber,
                        'tracking_url' => $trackingUrl,
                        'notes' => trim((string) ($action['notes'] ?? '')),
                        'dispatched_at' => trim((string) ($action['dispatched_at'] ?? '')),
                    ];
                }

                throw ValidationException::withMessages([
                    'item_actions_json' => 'Queued action #'.($index + 1).' has an unknown type.',
                ]);
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{event_id:int|null,available_quantity:int,delayed_quantity:int,released_reserved_quantity:int,reason:string}
     */
    private function applyQueuedCancellationAction(
        StoreOrder $order,
        StoreOrderItem $item,
        array $action,
        ?User $actingUser,
        int $actionNumber
    ): array {
        $releasedReservedQuantity = 0;

        if ((string) $order->status === StoreOrder::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued cancellation #'.$actionNumber.' cannot be applied because this order is already cancelled.',
            ]);
        }

        $requestedQuantity = max(
            0,
            (int) ($action['quantity'] ?? 0),
            max(0, (int) ($action['available_quantity'] ?? 0)) + max(0, (int) ($action['delayed_quantity'] ?? 0)),
        );
        if ($requestedQuantity <= 0) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued cancellation #'.$actionNumber.' must remove at least one unit.',
            ]);
        }

        $reason = $this->nullableTrimmedString($action['reason'] ?? null);
        if ($reason === null) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued cancellation #'.$actionNumber.' is missing a reason.',
            ]);
        }

        if ($requestedQuantity > $item->remainingFulfillableQuantity()) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued cancellation #'.$actionNumber.' exceeds the quantity still open on '.$item->displayTitle().'.',
            ]);
        }

        $resolvedQuantities = $this->resolveCancellationQuantities($item, $requestedQuantity);
        $availableQuantity = (int) $resolvedQuantities['available_quantity'];
        $delayedQuantity = (int) $resolvedQuantities['delayed_quantity'];

        if ($availableQuantity > 0) {
            $item->cancelled_available_quantity = $item->cancelledAvailableQuantity() + $availableQuantity;
            $releasedReservedQuantity = min($availableQuantity, max(0, (int) $item->inventory_reserved_quantity));
            $item->inventory_reserved_quantity = max(0, (int) $item->inventory_reserved_quantity - $releasedReservedQuantity);
            $this->restoreInventoryQuantity($item, $releasedReservedQuantity);
        }

        if ($delayedQuantity > 0) {
            $item->cancelled_delayed_quantity = $item->cancelledDelayedQuantity() + $delayedQuantity;
        }

        $item->save();

        $cancellation = new StoreOrderItemCancellation();
        $cancellation->store_order_item_id = $item->id;
        $cancellation->cancelled_by_user_id = $actingUser?->id;
        $cancellation->available_quantity = $availableQuantity;
        $cancellation->delayed_quantity = $delayedQuantity;
        $cancellation->reason = $reason;
        $cancellation->save();

        $update = $this->orderUpdates->recordItemCancellation($order, $item, $cancellation);

        return [
            'event_id' => $update?->id,
            'available_quantity' => $availableQuantity,
            'delayed_quantity' => $delayedQuantity,
            'released_reserved_quantity' => $releasedReservedQuantity,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{available_quantity:int,delayed_quantity:int}
     */
    private function resolveCancellationQuantities(StoreOrderItem $item, int $requestedQuantity): array
    {
        $requestedQuantity = max(0, $requestedQuantity);
        $delayedQuantity = min($requestedQuantity, $item->remainingDelayedQuantity());
        $availableQuantity = max(0, min(
            $requestedQuantity - $delayedQuantity,
            $item->remainingAvailableQuantity()
        ));

        return [
            'available_quantity' => $availableQuantity,
            'delayed_quantity' => $delayedQuantity,
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{event_id:int|null}
     */
    private function applyQueuedTrackingAction(
        StoreOrder $order,
        StoreOrderItem $item,
        array $action,
        int $actionNumber
    ): array {
        if ((string) $order->status === StoreOrder::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' cannot be applied because this order is already cancelled.',
            ]);
        }

        if (! $order->contains_physical || $item->isDigital()) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' can only be applied to physical items.',
            ]);
        }

        $shipmentType = (string) ($action['shipment_type'] ?? StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE);
        $quantity = max(0, (int) ($action['quantity'] ?? 0));
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' must include a quantity.',
            ]);
        }

        $remainingQuantity = $shipmentType === StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED
            ? $item->remainingDelayedQuantity()
            : $item->remainingAvailableQuantity();

        if ($quantity > $remainingQuantity) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' exceeds the quantity still open on '.$item->displayTitle().'.',
            ]);
        }

        if ($shipmentType === StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE) {
            $item->inventory_reserved_quantity = max(
                0,
                (int) $item->inventory_reserved_quantity - min($quantity, max(0, (int) $item->inventory_reserved_quantity))
            );
            $item->save();
        }

        $dispatchedAt = trim((string) ($action['dispatched_at'] ?? ''));
        try {
            $parsedDispatchedAt = $dispatchedAt !== ''
                ? Carbon::parse($dispatchedAt)
                : now();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' has an invalid dispatch date.',
            ]);
        }

        $tracking = new StoreOrderItemTracking();
        $tracking->store_order_item_id = $item->id;
        $tracking->shipment_type = $shipmentType;
        $tracking->quantity = $quantity;
        $tracking->parcel_number = $this->normalizeParcelNumber($action['parcel_number'] ?? null);
        $tracking->carrier = $this->nullableTrimmedString($action['carrier'] ?? null);
        $trackingMode = $this->normalizeTrackingMode($action['tracking_mode'] ?? null, $action['tracking_number'] ?? null, $action['tracking_url'] ?? null);
        $trackingNumber = $this->nullableTrimmedString($action['tracking_number'] ?? null);
        $trackingUrl = $this->nullableTrimmedString($action['tracking_url'] ?? null);

        if ($trackingMode === 'tracking_number' && $trackingNumber === null) {
            throw ValidationException::withMessages([
                'item_actions_json' => 'Queued tracking action #'.$actionNumber.' needs a tracking number.',
            ]);
        }

        if ($trackingMode === 'none') {
            $trackingNumber = null;
            $trackingUrl = null;
        } elseif ($trackingNumber !== null && $trackingUrl === null) {
            $trackingUrl = $this->resolveTrackingUrl((string) $tracking->carrier, $trackingNumber);
        }

        $tracking->tracking_number = $trackingNumber;
        $tracking->tracking_url = $trackingUrl;
        $tracking->notes = $this->nullableTrimmedString($action['notes'] ?? null);
        $tracking->dispatched_at = $parsedDispatchedAt;
        $tracking->save();

        if ($item->relationLoaded('trackingEntries')) {
            $item->setRelation('trackingEntries', $item->trackingEntries->push($tracking));
        }

        $update = $this->orderUpdates->recordTrackingAdded($order, $item, $tracking);

        return [
            'event_id' => $update?->id,
        ];
    }

    private function inventorySourceForOrderItem(StoreOrderItem $item): ?string
    {
        if ($item->product_variant_id) {
            return 'variant:'.$item->product_variant_id;
        }

        if ($item->product_id) {
            return 'product:'.$item->product_id;
        }

        return null;
    }

    /**
     * @param  array<int, array{available_quantity:int,delayed_quantity:int,reasons:array<int, string>}>  $cancellationSummary
     */
    private function createTaxAdjustmentNoteForOrderBatchCancellation(
        StoreOrder $order,
        Invoice $invoice,
        Collection $creditLines,
        array $cancellationSummary
    ): TaxAdjustment {
        $subtotal = round((float) $creditLines->sum('line_total_ex_tax'), 2);
        $gst = round((float) $creditLines->sum('tax_amount'), 2);
        $total = round((float) $creditLines->sum('line_total_inc_tax'), 2);

        $reasonLines = collect($cancellationSummary)
            ->flatMap(fn (array $row) => (array) $row['reasons'])
            ->map(fn ($reason) => trim((string) $reason))
            ->filter()
            ->unique()
            ->values();

        $adjustment = new TaxAdjustment();
        $adjustment->invoice_id = $invoice->id;
        $adjustment->adjustment_number = $this->documentNumbers->nextTaxAdjustmentNumber();
        $adjustment->issue_date = now()->startOfDay();
        $adjustment->subtotal_amount = -1 * $subtotal;
        $adjustment->gst_amount = -1 * $gst;
        $adjustment->total_amount = -1 * $total;
        $adjustment->notes = trim(implode("\n", array_filter([
            'Tax adjustment note for invoice '.$invoice->invoice_number,
            'Store order '.$order->order_number,
            $reasonLines->isNotEmpty() ? 'Reasons:' : null,
            ...$reasonLines->map(fn (string $reason) => '- '.$reason)->all(),
        ])));
        $adjustment->save();

        foreach ($creditLines->values() as $index => $line) {
            $adjustment->lines()->create([
                'invoice_line_id' => $line['invoice_line_id'] ?? null,
                'line_number' => $index + 1,
                'description' => (string) $line['description'],
                'notes' => (string) ($line['notes'] ?? ''),
                'quantity' => abs((float) ($line['quantity'] ?? 1)),
                'unit_price_ex_tax' => abs((float) $line['unit_price_ex_tax']),
                'tax_rate' => (float) $line['tax_rate'],
                'line_total_ex_tax' => abs((float) $line['line_total_ex_tax']),
                'tax_amount' => abs((float) $line['tax_amount']),
                'line_total_inc_tax' => abs((float) $line['line_total_inc_tax']),
            ]);
        }

        return $adjustment;
    }

    /**
     * @param  iterable<int, int>  $eventIds
     */
    private function queueImmediateOrderUpdateNotifications(
        StoreOrder $order,
        iterable $eventIds,
        bool $suppressAdminNotifications = false
    ): void
    {
        $customerPayload = $this->orderUpdates->payloadForEvents($eventIds, false);
        $customerRecipient = strtolower(trim((string) $order->billing_email));
        if ($customerPayload !== null && $customerPayload['orders'] !== [] && $customerRecipient !== '' && filter_var($customerRecipient, FILTER_VALIDATE_EMAIL)) {
            dispatch(new SendEmail(
                $customerRecipient,
                new StoreOrderCustomerUpdateNotice(
                    trim((string) ($order->billing_name ?? '')) ?: 'there',
                    $customerPayload['orders'],
                )
            ))->onQueue('mail');

            $this->orderUpdates->markCustomerDigestQueued($customerPayload['event_ids']);
        }

        $adminPayload = $this->orderUpdates->payloadForEvents($eventIds, true);
        $adminRecipients = $this->orderUpdates->adminRecipients();
        if ($adminPayload !== null && $adminPayload['orders'] !== []) {
            if (! $suppressAdminNotifications && $adminRecipients !== []) {
                foreach ($adminRecipients as $recipient) {
                    dispatch(new SendEmail(
                        $recipient,
                        new StoreOrderAdminUpdateNotice($adminPayload['orders'])
                    ))->onQueue('mail');
                }
            }

            $this->orderUpdates->markAdminDigestQueued($adminPayload['event_ids']);
        }
    }

    private function sendOrderAdjustmentDocumentBundle(
        StoreOrder $order,
        Invoice $invoice,
        TaxAdjustment $adjustment,
        ?User $actingUser = null
    ): void {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return;
        }

        $recipient = strtolower(trim((string) ($order->billing_email ?: $invoice->billing_email)));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $invoice->loadMissing('user', 'lines', 'allocations.customerPayment');
        $adjustment->loadMissing('lines');

        $invoicePdf = $this->buildInvoicePdf($invoice)->output();
        $adjustmentPdf = $this->buildTaxAdjustmentPdf($invoice, $adjustment)->output();
        if ($invoicePdf === '' && $adjustmentPdf === '') {
            return;
        }

        $attachments = [];
        if ($invoicePdf !== '') {
            $attachments[] = [
                'filename' => $this->invoicePdfFilename($invoice),
                'content' => $invoicePdf,
                'mime' => 'application/pdf',
            ];
        }
        if ($adjustmentPdf !== '') {
            $attachments[] = [
                'filename' => 'tax-adjustment-'.((string) $adjustment->adjustment_number).'.pdf',
                'content' => $adjustmentPdf,
                'mime' => 'application/pdf',
            ];
        }

        $invoice->loadMissing('storeOrders');
        $linkedOrderNumber = $invoice->storeOrders
            ->sortByDesc(fn (StoreOrder $order) => optional($order->created_at)->timestamp ?? (int) $order->id)
            ->first()?->order_number;
        [$initiatedByEmail, $initiatedByName] = $this->mailInitiatorIdentity($actingUser);

        dispatch(new SendEmail(
            $recipient,
            new InvoiceDocumentBundle(
                recipientName: $invoice->user?->getName() ?: (string) ($invoice->billing_name ?: $recipient),
                invoiceNumber: (string) $invoice->invoice_number,
                orderNumber: $linkedOrderNumber !== null ? (string) $linkedOrderNumber : null,
                attachments: $attachments,
                outstandingAmount: $invoice->outstandingAmount(),
                payUrl: $invoice->outstandingAmount() > 0.0001 ? route('invoice.public.pay.show', $invoice) : null,
                initiatedByEmail: $initiatedByEmail,
                initiatedByName: $initiatedByName,
            )
        ))->onQueue('mail');
    }

    /**
     * @param  array<int, int>  $refundPaymentIds
     */
    private function sendRefundReceiptEmailsForOrder(StoreOrder $order, Invoice $invoice, array $refundPaymentIds): void
    {
        $recipient = strtolower(trim((string) ($order->billing_email ?: $invoice->billing_email)));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $refundIds = array_values(array_filter(array_map('intval', $refundPaymentIds), fn (int $id) => $id > 0));
        if ($refundIds === []) {
            return;
        }

        $refundPayments = Payment::query()
            ->whereIn('id', $refundIds)
            ->where('kind', Payment::KIND_REFUND)
            ->orderByDesc('received_on')
            ->orderByDesc('created_at')
            ->get();

        foreach ($refundPayments as $refundPayment) {
            $pdfBinary = $this->buildInvoicePaymentReceiptPdf($invoice, $refundPayment)->output();
            if ($pdfBinary === '') {
                continue;
            }

            dispatch(new SendEmail(
                $recipient,
                new PaymentReceiptPdf(
                    recipientName: $invoice->user?->getName() ?: (string) ($invoice->billing_name ?: $recipient),
                    invoiceNumber: (string) $invoice->invoice_number,
                    receiptNumber: (string) $refundPayment->id,
                    amount: money(abs((float) $refundPayment->total_amount)),
                    paidOn: ($refundPayment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a')),
                    paymentMethod: Payment::paymentMethodLabel((string) ($refundPayment->payment_method ?? Payment::PAYMENT_METHOD_OTHER)),
                    receiptUrl: (string) ($refundPayment->square_receipt_url ?? ''),
                    isRefund: true,
                    pdfContent: $pdfBinary,
                    pdfFilename: $this->paymentReceiptPdfFilename($refundPayment),
                )
            ))->onQueue('mail');
        }
    }

    private function buildProductCreditLineForOrderItemCancellation(
        StoreOrder $order,
        StoreOrderItem $item,
        Invoice $invoice,
        int $cancelledQuantity,
        Collection $orderItems
    ): ?array {
        $cancelledQuantity = max(0, $cancelledQuantity);
        if ($cancelledQuantity <= 0) {
            return null;
        }

        $invoiceLine = $orderItems->firstWhere('id', $item->id)?->invoiceLine
            ?: $invoice->lines->firstWhere('id', (int) $item->invoice_line_id);
        if (! $invoiceLine instanceof InvoiceLine) {
            return null;
        }

        $originalQty = max(0.0, abs((float) $invoiceLine->quantity));
        if ($originalQty <= 0.0001) {
            return null;
        }

        $refunded = $this->refundedAmountsForInvoiceLine($invoiceLine);
        $remainingQty = max(0.0, round($originalQty - (float) ($refunded['quantity'] ?? 0.0), 2));
        if ($cancelledQuantity > ($remainingQty + 0.0001)) {
            throw ValidationException::withMessages([
                'available_quantity' => 'This item already has a full invoice credit recorded.',
            ]);
        }

        $allocatedDiscount = $this->orderItemDiscountAllocation($order, $item, $orderItems);
        $originalNetInc = max(0.0, round((float) $item->line_total_amount - $allocatedDiscount, 2));
        $alreadyCreditedInc = min($originalNetInc, max(0.0, round((float) ($refunded['line_total_inc_tax'] ?? 0.0), 2)));
        $remainingNetInc = max(0.0, round($originalNetInc - $alreadyCreditedInc, 2));
        if ($remainingNetInc <= 0.0001) {
            return null;
        }

        $lineInc = $cancelledQuantity >= ($remainingQty - 0.0001)
            ? $remainingNetInc
            : round($remainingNetInc * ($cancelledQuantity / max($remainingQty, 0.01)), 2);

        if ($lineInc <= 0.0001) {
            return null;
        }

        $taxRate = max(0.0, (float) ($invoiceLine->tax_rate ?? $item->tax_rate ?? 0));
        $lineEx = $taxRate > 0
            ? round($lineInc / (1 + $taxRate), 2)
            : round($lineInc, 2);
        $taxAmount = round($lineInc - $lineEx, 2);

        return [
            'invoice_line_id' => $invoiceLine->id,
            'description' => (string) ($invoiceLine->description ?: $item->displayTitle()),
            'notes' => 'Store order cancellation refund for '.$item->displayTitle(),
            'quantity' => (float) $cancelledQuantity,
            'unit_price_ex_tax' => round($lineEx / max(1, $cancelledQuantity), 2),
            'tax_rate' => $taxRate,
            'line_total_ex_tax' => $lineEx,
            'tax_amount' => $taxAmount,
            'line_total_inc_tax' => round($lineEx + $taxAmount, 2),
        ];
    }

    private function buildShippingCreditLineForOrderCancellation(
        StoreOrder $order,
        Invoice $invoice,
        Collection $orderItems
    ): ?array {
        if (! $this->allOrderItemsCancelled($orderItems)) {
            return null;
        }

        $shippingLine = $invoice->lines->first(fn (InvoiceLine $line) => (string) $line->kind === 'shipping');
        if (! $shippingLine instanceof InvoiceLine) {
            return null;
        }

        $shippingDiscount = (string) $order->coupon_type === Coupon::DISCOUNT_TYPE_FREE_SHIPPING
            ? min((float) $order->shipping_amount, (float) $order->discount_amount)
            : 0.0;
        $originalNetInc = max(0.0, round((float) $order->shipping_amount - $shippingDiscount, 2));
        if ($originalNetInc <= 0.0001) {
            return null;
        }

        $refunded = $this->refundedAmountsForInvoiceLine($shippingLine);
        $alreadyCreditedInc = min($originalNetInc, max(0.0, round((float) ($refunded['line_total_inc_tax'] ?? 0.0), 2)));
        $remainingNetInc = max(0.0, round($originalNetInc - $alreadyCreditedInc, 2));
        if ($remainingNetInc <= 0.0001) {
            return null;
        }

        $taxRate = max(0.0, (float) $shippingLine->tax_rate);
        $lineEx = $taxRate > 0
            ? round($remainingNetInc / (1 + $taxRate), 2)
            : round($remainingNetInc, 2);
        $taxAmount = round($remainingNetInc - $lineEx, 2);

        return [
            'invoice_line_id' => $shippingLine->id,
            'description' => (string) ($shippingLine->description ?: 'Shipping'),
            'notes' => 'Shipping refund for fully cancelled store order '.$order->order_number,
            'quantity' => 1.0,
            'unit_price_ex_tax' => $lineEx,
            'tax_rate' => $taxRate,
            'line_total_ex_tax' => $lineEx,
            'tax_amount' => $taxAmount,
            'line_total_inc_tax' => round($lineEx + $taxAmount, 2),
        ];
    }

    private function createTaxAdjustmentNoteForOrderItemCancellation(
        StoreOrder $order,
        StoreOrderItem $item,
        Invoice $invoice,
        Collection $creditLines,
        string $reason
    ): TaxAdjustment {
        $subtotal = round((float) $creditLines->sum('line_total_ex_tax'), 2);
        $gst = round((float) $creditLines->sum('tax_amount'), 2);
        $total = round((float) $creditLines->sum('line_total_inc_tax'), 2);

        $adjustment = new TaxAdjustment();
        $adjustment->invoice_id = $invoice->id;
        $adjustment->adjustment_number = $this->documentNumbers->nextTaxAdjustmentNumber();
        $adjustment->issue_date = now()->startOfDay();
        $adjustment->subtotal_amount = -1 * $subtotal;
        $adjustment->gst_amount = -1 * $gst;
        $adjustment->total_amount = -1 * $total;
        $adjustment->notes = trim(implode("\n", array_filter([
            'Tax adjustment note for invoice '.$invoice->invoice_number,
            $reason,
            'Store order '.$order->order_number,
            'Cancelled item: '.$item->displayTitle(),
        ])));
        $adjustment->save();

        foreach ($creditLines->values() as $index => $line) {
            $adjustment->lines()->create([
                'invoice_line_id' => $line['invoice_line_id'] ?? null,
                'line_number' => $index + 1,
                'description' => (string) $line['description'],
                'notes' => (string) ($line['notes'] ?? ''),
                'quantity' => abs((float) ($line['quantity'] ?? 1)),
                'unit_price_ex_tax' => abs((float) $line['unit_price_ex_tax']),
                'tax_rate' => (float) $line['tax_rate'],
                'line_total_ex_tax' => abs((float) $line['line_total_ex_tax']),
                'tax_amount' => abs((float) $line['tax_amount']),
                'line_total_inc_tax' => abs((float) $line['line_total_inc_tax']),
            ]);
        }

        return $adjustment;
    }

    private function reconcileCreditAllocationsForAdjustment(Invoice $invoice, TaxAdjustment $taxAdjustment): array
    {
        DB::table('invoice_payment_allocations')
            ->where('tax_adjustment_id', $taxAdjustment->id)
            ->delete();

        $remaining = abs(round((float) $taxAdjustment->total_amount, 2));
        if ($remaining <= 0.0001) {
            return ['allocated' => 0.0, 'remaining' => 0.0, 'consumed_outstanding' => 0.0];
        }

        $outstandingBefore = $this->outstandingBeforeThisAdjustment($invoice, $taxAdjustment);
        $consumedOutstanding = min($remaining, $outstandingBefore);
        $remaining = max(0, round($remaining - $consumedOutstanding, 2));
        if ($remaining <= 0.0001) {
            return ['allocated' => 0.0, 'remaining' => 0.0, 'consumed_outstanding' => $consumedOutstanding];
        }

        $rows = $invoice->allocations()
            ->with('customerPayment')
            ->orderBy('id')
            ->get();

        $netByPayment = $rows
            ->groupBy('payment_id')
            ->map(fn (Collection $allocations) => round((float) $allocations->sum('allocated_amount'), 2))
            ->filter(fn (float $amount) => $amount > 0.0001);

        $allocated = 0.0;
        foreach ($netByPayment as $paymentId => $netAllocated) {
            if ($remaining <= 0.0001) {
                break;
            }

            $sourcePayment = $rows->firstWhere('payment_id', (int) $paymentId)?->customerPayment;
            if (! $sourcePayment instanceof Payment || (string) ($sourcePayment->kind ?? Payment::KIND_PAYMENT) !== Payment::KIND_PAYMENT) {
                continue;
            }

            $portion = round(min($remaining, (float) $netAllocated), 2);
            if ($portion <= 0.0001) {
                continue;
            }

            $invoice->allocations()->create([
                'payment_id' => (int) $paymentId,
                'tax_adjustment_id' => $taxAdjustment->id,
                'allocated_amount' => -1 * $portion,
            ]);

            $remaining = max(0, round($remaining - $portion, 2));
            $allocated = round($allocated + $portion, 2);
        }

        return [
            'allocated' => $allocated,
            'remaining' => $remaining,
            'consumed_outstanding' => $consumedOutstanding,
        ];
    }

    private function createSquareRefundOperationsForOrderCancellation(
        Invoice $invoice,
        TaxAdjustment $adjustment,
        StoreOrder $order,
        ?StoreOrderItemCancellation $cancellation,
        int $targetCents
    ): array {
        if ($targetCents <= 0) {
            return [];
        }

        $rows = InvoicePaymentAllocation::query()
            ->with('customerPayment')
            ->where('invoice_id', $invoice->id)
            ->where('tax_adjustment_id', $adjustment->id)
            ->where('allocated_amount', '<', 0)
            ->orderBy('id')
            ->get();

        $allocatedByPayment = $rows
            ->groupBy('payment_id')
            ->map(fn (Collection $allocations) => round(abs((float) $allocations->sum('allocated_amount')), 2))
            ->filter(fn (float $amount) => $amount > 0.0001);

        $remaining = $targetCents;
        $operationIds = [];

        foreach ($allocatedByPayment as $paymentId => $allocatedAmount) {
            if ($remaining <= 0) {
                break;
            }

            $customerPayment = $rows->firstWhere('payment_id', (int) $paymentId)?->customerPayment;
            if (! $customerPayment instanceof Payment) {
                continue;
            }
            if ((string) $customerPayment->gateway_provider !== 'square') {
                continue;
            }
            if (! is_string($customerPayment->square_payment_id) || trim($customerPayment->square_payment_id) === '') {
                continue;
            }

            $refundable = (int) $customerPayment->square_remaining_refundable_money;
            if ($refundable <= 0) {
                continue;
            }

            $allocatedCents = max(0, (int) round($allocatedAmount * 100));
            $refundCents = min($remaining, $refundable, $allocatedCents);
            if ($refundCents <= 0) {
                continue;
            }

            $cancellationIdPart = $cancellation instanceof StoreOrderItemCancellation
                ? 'cn-'.$cancellation->id
                : 'batch';
            $idempotencyKey = mb_substr(
                'ord-'.$order->id.'-'.$cancellationIdPart.'-an-'.$adjustment->id.'-cp-'.$customerPayment->id.'-'.$refundCents,
                0,
                120
            );

            $operation = SquareRefundOperation::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'invoice_id' => $invoice->id,
                    'tax_adjustment_id' => $adjustment->id,
                    'payment_id' => $customerPayment->id,
                    'requested_cents' => $refundCents,
                    'status' => SquareRefundOperation::STATUS_PENDING,
                ]
            );

            $operationIds[] = (int) $operation->id;
            $remaining -= $refundCents;
        }

        return array_values(array_unique($operationIds));
    }

    /**
     * @param  array<int, int>  $operationIds
     * @return array{refunded_cents:int,refund_payment_ids:array<int, int>}
     */
    private function processSquareRefundOperations(array $operationIds, string $reason, ?User $actingUser = null): array
    {
        $operationIds = array_values(array_filter(array_map('intval', $operationIds), fn (int $id) => $id > 0));
        if ($operationIds === []) {
            return [
                'refunded_cents' => 0,
                'refund_payment_ids' => [],
            ];
        }

        $refundedCents = 0;
        $refundPaymentIds = [];

        foreach ($operationIds as $operationId) {
            $operation = SquareRefundOperation::query()
                ->with('customerPayment')
                ->find($operationId);
            if (! $operation instanceof SquareRefundOperation) {
                continue;
            }

            if ($operation->status === SquareRefundOperation::STATUS_COMPLETED) {
                $refundedCents += (int) $operation->refunded_cents;
                continue;
            }

            $customerPayment = $operation->customerPayment;
            if (! $customerPayment instanceof Payment) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Customer payment record is missing.';
                $operation->processed_at = now();
                $operation->save();
                continue;
            }

            if (! $this->squareApi->isEnabled()) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Square integration is not enabled.';
                $operation->processed_at = now();
                $operation->save();
                continue;
            }

            if (! is_string($customerPayment->square_payment_id) || trim((string) $customerPayment->square_payment_id) === '') {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'Square payment id is missing on the customer payment.';
                $operation->processed_at = now();
                $operation->save();
                continue;
            }

            $refundable = (int) $customerPayment->square_remaining_refundable_money;
            $refundCents = min((int) $operation->requested_cents, $refundable);
            if ($refundCents <= 0) {
                $operation->status = SquareRefundOperation::STATUS_MANUAL_REQUIRED;
                $operation->failure_message = 'No remaining refundable Square balance on the payment.';
                $operation->processed_at = now();
                $operation->save();
                continue;
            }

            try {
                $response = $this->squareApi->createRefund([
                    'idempotency_key' => (string) $operation->idempotency_key,
                    'payment_id' => (string) $customerPayment->square_payment_id,
                    'amount_money' => [
                        'amount' => $refundCents,
                        'currency' => 'AUD',
                    ],
                    'reason' => mb_substr($reason, 0, 255),
                ]);

                $refund = (array) ($response['refund'] ?? []);
                if ($refund === []) {
                    throw new RuntimeException('Square refund failed: no refund object was returned.');
                }

                $refundValue = (int) ($refund['amount_money']['amount'] ?? 0);
                $refundStatus = strtoupper(trim((string) ($refund['status'] ?? 'UNKNOWN')));
                if ($refundValue <= 0 || ! in_array($refundStatus, ['PENDING', 'COMPLETED'], true)) {
                    throw new RuntimeException('Square refund was not accepted.');
                }

                $currentRefunded = (int) ($customerPayment->square_refunded_money_amount ?? 0);
                $paidValue = (int) ($customerPayment->square_paid_money_amount ?? 0);
                $refundPayment = $this->createSquareRefundPaymentRecord(
                    $customerPayment,
                    $refundValue,
                    (string) ($refund['id'] ?? ''),
                    (string) ($refund['status'] ?? 'PENDING'),
                    $reason,
                    $actingUser,
                );
                if ($refundPayment instanceof Payment) {
                    $refundPaymentIds[] = (int) $refundPayment->id;
                }

                $recordedRefundedCents = (int) round(((float) $customerPayment->refunds()->sum('total_amount')) * 100);
                $customerPayment->square_refunded_money_amount = min($paidValue, max($currentRefunded, $recordedRefundedCents));
                $customerPayment->save();

                $operation->status = SquareRefundOperation::STATUS_COMPLETED;
                $operation->refunded_cents = $refundValue;
                $operation->square_refund_id = (string) ($refund['id'] ?? null);
                $operation->payload = $response;
                $operation->failure_message = null;
                $operation->processed_at = now();
                $operation->save();

                $refundedCents += $refundValue;
            } catch (Throwable $e) {
                report($e);

                $operation->status = SquareRefundOperation::STATUS_FAILED;
                $operation->failure_message = mb_substr($e->getMessage(), 0, 500);
                $operation->processed_at = now();
                $operation->save();
            }
        }

        return [
            'refunded_cents' => $refundedCents,
            'refund_payment_ids' => array_values(array_unique(array_map('intval', $refundPaymentIds))),
        ];
    }

    private function createSquareRefundPaymentRecord(
        Payment $originalPayment,
        int $refundCents,
        string $squareRefundId,
        string $squareStatus,
        string $reason,
        ?User $actingUser = null
    ): ?Payment {
        $refundAmount = round(max(0, $refundCents) / 100, 2);
        if ($refundAmount <= 0.0001) {
            return null;
        }

        $existing = Payment::query()
            ->where('refund_of_payment_id', $originalPayment->id)
            ->where('gateway_provider', 'square')
            ->where('gateway_reference_id', $squareRefundId)
            ->first();

        if ($existing instanceof Payment) {
            return $existing;
        }

        $refundPayment = new Payment();
        $refundPayment->refund_of_payment_id = $originalPayment->id;
        $refundPayment->kind = Payment::KIND_REFUND;
        $refundPayment->user_id = $originalPayment->user_id;
        $refundPayment->created_by = $actingUser?->id;
        $refundPayment->received_on = now();
        $refundPayment->payment_method = (string) ($originalPayment->payment_method ?: Payment::PAYMENT_METHOD_CREDIT_CARD);
        $refundPayment->reference = trim(implode(' | ', array_filter([
            'Refund for payment #'.$originalPayment->id,
            $originalPayment->reference ? 'Original: '.$originalPayment->reference : null,
        ])));
        $refundPayment->total_amount = $refundAmount;
        $refundPayment->gst_amount = 0;
        $refundPayment->notes = $reason !== '' ? $reason : 'Square refund';
        $refundPayment->gateway_provider = 'square';
        $refundPayment->gateway_status = $squareStatus !== '' ? $squareStatus : 'PENDING';
        $refundPayment->gateway_reference_id = $squareRefundId !== '' ? $squareRefundId : null;
        $refundPayment->save();

        return $refundPayment;
    }

    private function createManualRefundOperationForOrderCancellation(
        StoreOrder $order,
        ?Invoice $invoice,
        ?TaxAdjustment $adjustment,
        int $requestedCents,
        string $reason,
        ?User $actingUser = null
    ): ?SquareRefundOperation {
        if (! $invoice instanceof Invoice || $requestedCents <= 0) {
            return null;
        }

        $invoice->loadMissing('allocations.customerPayment');
        $candidatePayment = $invoice->allocations
            ->map(fn ($allocation) => $allocation->customerPayment)
            ->filter(fn ($payment) => $payment instanceof Payment && (string) ($payment->kind ?? Payment::KIND_PAYMENT) === Payment::KIND_PAYMENT)
            ->first(function (Payment $payment): bool {
                $remaining = max(0, round((float) $payment->total_amount - (float) $payment->refunds()->sum('total_amount'), 2));

                return $remaining > 0.0001;
            });

        if (! $candidatePayment instanceof Payment) {
            return null;
        }

        $idempotencyKey = mb_substr(
            'ord-manual-'.$order->id.'-inv-'.$invoice->id.'-cp-'.$candidatePayment->id.'-'.$requestedCents,
            0,
            120
        );

        return SquareRefundOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'invoice_id' => $invoice->id,
                'tax_adjustment_id' => $adjustment?->id,
                'payment_id' => $candidatePayment->id,
                'requested_cents' => $requestedCents,
                'status' => SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                'failure_message' => 'Payment was not processed through Square. Manual refund required.',
                'processed_at' => now(),
                'payload' => [
                    'manual_refund' => [
                        'source' => 'store_order_cancellation',
                        'reason' => $reason,
                        'order_id' => (int) $order->id,
                        'invoice_id' => (int) $invoice->id,
                        'processed_by' => $actingUser?->id,
                    ],
                ],
            ]
        );
    }

    private function orderItemDiscountAllocation(StoreOrder $order, StoreOrderItem $targetItem, Collection $orderItems): float
    {
        $discountTotal = max(0.0, round((float) $order->discount_amount, 2));
        if ($discountTotal <= 0.0001 || (string) $order->coupon_type === Coupon::DISCOUNT_TYPE_FREE_SHIPPING) {
            return 0.0;
        }

        $subtotal = max(0.0, round((float) $orderItems->sum(fn (StoreOrderItem $item) => (float) $item->line_price_amount), 2));
        if ($subtotal <= 0.0001) {
            return 0.0;
        }

        $remaining = $discountTotal;
        $orderedItems = $orderItems->sortBy('id')->values();

        foreach ($orderedItems as $index => $item) {
            $isLast = $index === ($orderedItems->count() - 1);
            $lineAmount = max(0.0, round((float) $item->line_price_amount, 2));
            $share = $isLast
                ? $remaining
                : round(min($remaining, $discountTotal * ($lineAmount / $subtotal)), 2);

            if ((int) $item->id === (int) $targetItem->id) {
                return max(0.0, round($share, 2));
            }

            $remaining = max(0.0, round($remaining - $share, 2));
        }

        return 0.0;
    }

    private function refundedAmountsForInvoiceLine(InvoiceLine $invoiceLine): array
    {
        $row = DB::table('tax_adjustment_lines')
            ->where('invoice_line_id', $invoiceLine->id)
            ->selectRaw('
                COALESCE(SUM(quantity), 0) as quantity,
                COALESCE(SUM(line_total_ex_tax), 0) as line_total_ex_tax,
                COALESCE(SUM(tax_amount), 0) as tax_amount,
                COALESCE(SUM(line_total_inc_tax), 0) as line_total_inc_tax
            ')
            ->first();

        return [
            'quantity' => (float) ($row->quantity ?? 0.0),
            'line_total_ex_tax' => (float) ($row->line_total_ex_tax ?? 0.0),
            'tax_amount' => (float) ($row->tax_amount ?? 0.0),
            'line_total_inc_tax' => (float) ($row->line_total_inc_tax ?? 0.0),
        ];
    }

    private function allOrderItemsCancelled(Collection $orderItems): bool
    {
        return $orderItems->isNotEmpty()
            && $orderItems->every(fn (StoreOrderItem $item) => $item->cancelledQuantity() >= max(0, (int) $item->quantity));
    }

    private function outstandingBeforeThisAdjustment(Invoice $invoice, TaxAdjustment $taxAdjustment): float
    {
        $otherIssuedCredits = (float) TaxAdjustment::query()
            ->where('invoice_id', $invoice->id)
            ->where('id', '!=', $taxAdjustment->id)
            ->sum(DB::raw('ABS(total_amount)'));
        $netInvoiceDue = max(0, round(abs((float) $invoice->total_amount) - $otherIssuedCredits, 2));

        $paidAgainstInvoice = (float) DB::table('invoice_payment_allocations')
            ->where('invoice_id', $invoice->id)
            ->where('allocated_amount', '>', 0)
            ->whereIn('payment_id', Payment::query()
                ->where('kind', Payment::KIND_PAYMENT)
                ->select('id'))
            ->sum('allocated_amount');

        return max(0, round($netInvoiceDue - $paidAgainstInvoice, 2));
    }

    private function invoiceHasRefundableSquarePayment(Invoice $invoice): bool
    {
        return $invoice->allocations()
            ->whereHas('customerPayment', function ($query): void {
                $query->where(function ($paymentQuery): void {
                    $paymentQuery->where('gateway_provider', 'square')
                        ->orWhereNotNull('square_integration_meta->square_payment_id');
                });
            })
            ->exists();
    }

    private function mailInitiatorIdentity(?User $actingUser): array
    {
        $email = trim((string) ($actingUser instanceof User ? $actingUser->email : ''));
        $name = trim((string) ($actingUser instanceof User ? $actingUser->getName() : ''));

        return [
            $email !== '' ? $email : null,
            $name !== '' ? $name : null,
        ];
    }

    public function summarizeLines(Collection $lines, array $shippingQuote, array $couponEvaluation): array
    {
        $subtotal = round((float) $lines->sum('line_price'), 2);
        $shipping = round((float) ($shippingQuote['amount'] ?? 0), 2);
        $discount = round(min($subtotal + $shipping, (float) ($couponEvaluation['discount_amount'] ?? 0)), 2);
        $itemGst = round((float) $lines->sum('line_gst'), 2);
        $itemExTax = 0.0;

        foreach ($lines as $line) {
            $breakdown = $this->inclusiveBreakdown((float) $line->line_price, (float) $line->tax_rate);
            $itemExTax += (float) $breakdown['line_ex_tax'];
        }

        $firstPhysicalLine = $lines->first(fn ($line) => $line->product->isPhysical());
        $shippingTaxRate = is_object($firstPhysicalLine) && isset($firstPhysicalLine->tax_rate)
            ? (float) $firstPhysicalLine->tax_rate
            : 0.1;
        $shippingBreakdown = $this->inclusiveBreakdown($shipping, $shippingTaxRate);
        $shippingGst = round((float) $shippingBreakdown['tax_amount'], 2);
        $discountTaxRate = (string) ($couponEvaluation['discount_type'] ?? '') === Coupon::DISCOUNT_TYPE_FREE_SHIPPING
            ? $shippingTaxRate
            : $this->effectiveTaxRate($subtotal, $itemGst);
        $discountBreakdown = $this->inclusiveBreakdown($discount, $discountTaxRate);
        $gst = round(max(0, $itemGst + $shippingGst - (float) $discountBreakdown['tax_amount']), 2);
        $invoiceSubtotal = round(
            $itemExTax
            + (float) $shippingBreakdown['line_ex_tax']
            - (float) $discountBreakdown['line_ex_tax'],
            2
        );

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'gst' => $gst,
            'total' => round(max(0, $subtotal + $shipping - $discount), 2),
            'invoice_subtotal' => $invoiceSubtotal,
            'contains_digital' => $lines->contains(fn ($line) => $line->product->isDigital()),
            'contains_physical' => $lines->contains(fn ($line) => $line->product->isPhysical()),
            'contains_preorder' => $lines->contains(fn ($line) => (bool) ($line->is_preorder ?? false)),
            'contains_backorder' => $lines->contains(
                fn ($line) => (int) ($line->delayed_quantity ?? 0) > 0 && ! (bool) ($line->is_preorder ?? false)
            ),
            'shipping_tax_rate' => $shippingTaxRate,
            'discount_tax_rate' => $discountTaxRate,
            'coupon' => $couponEvaluation['coupon'] ?? null,
            'coupon_code' => $couponEvaluation['coupon_code'] ?? null,
            'coupon_type' => $couponEvaluation['discount_type'] ?? null,
            'shipping_method' => (string) ($shippingQuote['method'] ?? 'Shipping'),
            'shipping_method_code' => $shippingQuote['selected_method_code'] ?? null,
            'shipping_package_summary' => $shippingQuote['package_summary'] ?? null,
            'split_shipments' => (bool) ($shippingQuote['split_shipments'] ?? false),
            'consolidate_shipments' => (bool) ($shippingQuote['consolidate_shipments'] ?? false),
            'shipment_count' => max(1, (int) ($shippingQuote['shipment_count'] ?? 1)),
            'shipping_breakdown_data' => $shippingQuote,
            'shipping_zone' => null,
            'shipping_chargeable_weight_grams' => (int) ($shippingQuote['known_weight_grams'] ?? 0),
        ];
    }

    private function prepareCheckout(Collection $lines, array $customer, ?User $user, bool $allowManualQuote = false): array
    {
        $preparedLines = $this->lockAndPrepareLines($lines);
        $shippingQuote = $this->shipping->quote(
            $preparedLines,
            $customer['shipping_country'],
            $customer['shipping_method_code'] ?? null,
            (bool) ($customer['consolidate_shipments'] ?? false),
        );

        if (! ($shippingQuote['can_checkout'] ?? true) && ! ($allowManualQuote && (bool) ($shippingQuote['requires_manual_quote'] ?? false))) {
            throw ValidationException::withMessages([
                'shipping_country' => (string) ($shippingQuote['reason'] ?? 'Shipping could not be calculated for this order.'),
            ]);
        }

        $couponEvaluation = $this->coupons->evaluate(
            $customer['coupon_code'],
            (float) $preparedLines->sum('line_price'),
            (float) ($shippingQuote['amount'] ?? 0),
            $user,
            $customer['billing_email'],
        );

        if (($couponEvaluation['error'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'coupon_code' => (string) $couponEvaluation['error'],
            ]);
        }

        return [
            'lines' => $preparedLines,
            'totals' => $this->summarizeLines($preparedLines, $shippingQuote, $couponEvaluation),
        ];
    }

    private function createOrderRecords(
        Collection $preparedLines,
        array $customer,
        ?User $user,
        array $totals,
        bool $createInvoice = true,
        ?string $forcedStatus = null,
        bool $reserveInventory = true,
    ): StoreOrder
    {
        $invoice = null;
        if ($createInvoice) {
            $invoice = new Invoice();
            $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
            $invoice->user_id = $user?->id;
            $invoice->billing_name = $customer['billing_name'];
            $invoice->billing_email = $customer['billing_email'];
            $invoice->billing_phone = $customer['billing_phone'];
            $invoice->status = $totals['total'] <= 0.0001 ? Invoice::STATUS_PAID : Invoice::STATUS_ISSUED;
            $invoice->issue_date = Carbon::today();
            $invoice->issued_at = now();
            $invoice->due_date = Carbon::today()->addDays(7);
            $invoice->subtotal_amount = $totals['invoice_subtotal'];
            $invoice->gst_amount = $totals['gst'];
            $invoice->total_amount = $totals['total'];
            $invoice->notes = $customer['notes'];
            $invoice->save();
        }

        $order = new StoreOrder();
        $order->order_number = $this->documentNumbers->nextStoreOrderNumber();
        $order->access_token = Str::random(40);
        $order->user_id = $user?->id;
        $order->invoice_id = $invoice?->id;
        $order->coupon_id = $totals['coupon']?->id;
        $order->status = $forcedStatus ?? ($totals['total'] <= 0.0001
            ? ($totals['contains_physical'] ? StoreOrder::STATUS_PROCESSING : StoreOrder::STATUS_FULFILLED)
            : StoreOrder::STATUS_PENDING_PAYMENT);
        $order->contains_digital = $totals['contains_digital'];
        $order->contains_physical = $totals['contains_physical'];
        $order->contains_preorder = $totals['contains_preorder'];
        $order->split_shipments = (bool) ($totals['split_shipments'] ?? false);
        $order->consolidate_shipments = (bool) ($totals['consolidate_shipments'] ?? false);
        $order->shipment_count = max(1, (int) ($totals['shipment_count'] ?? 1));
        $order->preorder_acknowledged = $totals['contains_preorder']
            ? (bool) ($customer['preorder_acknowledged'] ?? true)
            : false;
        $order->billing_name = $customer['billing_name'];
        $order->billing_email = $customer['billing_email'];
        $order->billing_phone = $customer['billing_phone'];
        $order->billing_company = $customer['billing_company'];
        $order->shipping_name = $customer['shipping_name'];
        $order->shipping_phone = $customer['shipping_phone'];
        $order->shipping_address = $customer['shipping_address'];
        $order->shipping_address2 = $customer['shipping_address2'];
        $order->shipping_city = $customer['shipping_city'];
        $order->shipping_state = $customer['shipping_state'];
        $order->shipping_postcode = $customer['shipping_postcode'];
        $order->shipping_country = $customer['shipping_country'];
        $order->shipping_method = $totals['shipping_method'];
        $order->shipping_method_code = $totals['shipping_method_code'];
        $order->shipping_package_summary = $totals['shipping_package_summary'];
        $order->shipping_breakdown_data = $totals['shipping_breakdown_data'] ?? null;
        $order->shipping_zone = $totals['shipping_zone'];
        $order->shipping_chargeable_weight_grams = $totals['shipping_chargeable_weight_grams'];
        $order->coupon_code = $totals['coupon_code'];
        $order->coupon_type = $totals['coupon_type'];
        $order->notes = $customer['notes'];
        $order->subtotal_amount = $totals['subtotal'];
        $order->shipping_amount = $totals['shipping'];
        $order->discount_amount = $totals['discount'];
        $order->gst_amount = $totals['gst'];
        $order->total_amount = $totals['total'];
        $order->paid_at = $createInvoice && $totals['total'] <= 0.0001 ? now() : null;
        $order->fulfilled_at = $createInvoice && $totals['total'] <= 0.0001 && ! $totals['contains_physical'] ? now() : null;
        $order->save();

        $lineNumber = 1;
        foreach ($preparedLines as $line) {
            $unitBreakdown = $this->inclusiveBreakdown((float) $line->unit_price, (float) $line->tax_rate);
            $lineBreakdown = $this->inclusiveBreakdown((float) $line->line_price, (float) $line->tax_rate);

            $invoiceLine = null;
            if ($invoice instanceof Invoice) {
                $invoiceLine = new InvoiceLine();
                $invoiceLine->invoice_id = $invoice->id;
                $invoiceLine->line_number = $lineNumber++;
                $invoiceLine->kind = 'product';
                $invoiceLine->description = (string) $line->display_title;
                $invoiceLine->quantity = (int) $line->quantity;
                $invoiceLine->unit_price_ex_tax = $unitBreakdown['line_ex_tax'];
                $invoiceLine->tax_rate = (float) $line->tax_rate;
                $invoiceLine->line_total_ex_tax = $lineBreakdown['line_ex_tax'];
                $invoiceLine->tax_amount = $lineBreakdown['tax_amount'];
                $invoiceLine->line_total_inc_tax = round((float) $line->line_price, 2);
                $invoiceLine->source_type = Product::class;
                $invoiceLine->source_id = $line->product->id;
                $invoiceLine->save();
            }

            $reservedQuantity = $reserveInventory ? $this->reserveInventoryForPreparedLine($line) : 0;

            $orderItem = new StoreOrderItem();
            $orderItem->store_order_id = $order->id;
            $orderItem->product_id = $line->product->id;
            $orderItem->product_variant_id = $line->variant?->id;
            $orderItem->invoice_line_id = $invoiceLine?->id;
            $orderItem->product_title = (string) $line->product->title;
            $orderItem->product_slug = (string) $line->product->slug;
            $orderItem->variant_name = $line->product->variantDisplayName($line->variant);
            $orderItem->product_sku = $line->product->sku;
            $orderItem->variant_sku = $line->variant?->sku;
            $orderItem->product_type = (string) $line->product->product_type;
            $orderItem->box_only = (bool) $line->box_only;
            $orderItem->is_preorder = (bool) $line->is_preorder;
            $orderItem->preorder_shipping_estimate = $line->product->isPreorder($line->variant)
                ? ($line->variant->preorder_shipping_estimate ?? $line->product->preorder_shipping_estimate)
                : null;
            $orderItem->quantity = (int) $line->quantity;
            $orderItem->available_now_quantity = (int) ($line->available_now_quantity ?? $line->quantity);
            $orderItem->delayed_quantity = (int) ($line->delayed_quantity ?? 0);
            $orderItem->delayed_fulfilment_type = $line->delayed_fulfilment_type;
            $orderItem->delayed_shipping_estimate = $line->delayed_shipping_estimate;
            $orderItem->inventory_reserved_quantity = $reservedQuantity;
            $orderItem->unit_shipping_units = round((float) $line->unit_shipping_units, 2);
            $orderItem->unit_min_satchel_rank = $line->unit_min_satchel_rank;
            $orderItem->unit_price = round((float) $line->unit_price, 2);
            $orderItem->unit_shipping_rate = 0;
            $orderItem->tax_rate = (float) $line->tax_rate;
            $orderItem->unit_weight_grams = $line->unit_weight_grams;
            $orderItem->unit_length_cm = null;
            $orderItem->unit_width_cm = null;
            $orderItem->unit_height_cm = null;
            $orderItem->line_price_amount = round((float) $line->line_price, 2);
            $orderItem->line_shipping_amount = 0;
            $orderItem->line_gst_amount = round((float) $line->line_gst, 2);
            $orderItem->line_total_amount = round((float) $line->line_price, 2);
            $orderItem->save();

            if ($line->product->isDigital()) {
                foreach ($line->product->downloadMedia()->get() as $index => $media) {
                    $orderItem->downloads()->create([
                        'media_name' => (string) $media->name,
                        'title' => (string) ($media->title ?? $media->name),
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        if ($invoice instanceof Invoice && $totals['shipping'] > 0.0001) {
            $shippingBreakdown = $this->inclusiveBreakdown($totals['shipping'], $totals['shipping_tax_rate']);
            $shippingDescription = $this->shippingLineDescription($totals);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $lineNumber++,
                'kind' => 'shipping',
                'description' => $shippingDescription,
                'quantity' => 1,
                'unit_price_ex_tax' => $shippingBreakdown['unit_ex_tax'],
                'tax_rate' => $totals['shipping_tax_rate'],
                'line_total_ex_tax' => $shippingBreakdown['line_ex_tax'],
                'tax_amount' => $shippingBreakdown['tax_amount'],
                'line_total_inc_tax' => round((float) $totals['shipping'], 2),
            ]);
        }

        if ($invoice instanceof Invoice && $totals['discount'] > 0.0001) {
            $discountBreakdown = $this->inclusiveBreakdown(-1 * $totals['discount'], $totals['discount_tax_rate']);
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $lineNumber,
                'kind' => 'discount',
                'description' => $totals['coupon_code'] !== null
                    ? 'Voucher '.$totals['coupon_code']
                    : 'Discount',
                'quantity' => 1,
                'unit_price_ex_tax' => $discountBreakdown['unit_ex_tax'],
                'tax_rate' => $totals['discount_tax_rate'],
                'line_total_ex_tax' => $discountBreakdown['line_ex_tax'],
                'tax_amount' => $discountBreakdown['tax_amount'],
                'line_total_inc_tax' => round(-1 * (float) $totals['discount'], 2),
            ]);
        }

        return $order->load(['invoice', 'items.downloads.media', 'coupon']);
    }

    private function chargeLockedOrder(StoreOrder $order, Invoice $invoice, ?string $sourceId, string $locationId, ?User $actingUser = null): int
    {
        $outstandingAmount = $invoice->outstandingAmount();
        if ($outstandingAmount <= 0.0001) {
            throw ValidationException::withMessages([
                'source_id' => 'This order has already been paid.',
            ]);
        }

        $sourceId = trim((string) $sourceId);
        if ($sourceId === '') {
            throw ValidationException::withMessages([
                'source_id' => 'Card details are required.',
            ]);
        }

        $customerPayment = new Payment();
        $customerPayment->user_id = $order->user_id;
        $customerPayment->created_by = $actingUser?->id;
        $customerPayment->kind = Payment::KIND_PAYMENT;
        $customerPayment->received_on = now();
        $customerPayment->payment_method = Payment::PAYMENT_METHOD_CREDIT_CARD;
        $customerPayment->reference = 'Store order '.$order->order_number;
        $customerPayment->total_amount = $outstandingAmount;
        $customerPayment->gst_amount = $this->proratedGst($invoice, $outstandingAmount);
        $customerPayment->notes = 'Online store payment';
        $customerPayment->save();

        try {
            $response = $this->squareApi->createPayment([
                'idempotency_key' => 'store-order-'.$order->id.'-payment-'.$customerPayment->id,
                'source_id' => trim($sourceId),
                'location_id' => $locationId,
                'reference_id' => 'payment:'.$customerPayment->id,
                'amount_money' => [
                    'amount' => (int) round($outstandingAmount * 100),
                    'currency' => 'AUD',
                ],
                'autocomplete' => true,
                'note' => 'Store order '.$order->order_number,
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'source_id' => $this->squareApi->userFacingPaymentErrorMessage($e->getMessage()),
            ]);
        }

        $payment = (array) ($response['payment'] ?? []);
        if ($payment === []) {
            throw ValidationException::withMessages([
                'source_id' => 'Credit card payment failed with an invalid Square response.',
            ]);
        }

        $squareStatus = strtoupper(trim((string) ($payment['status'] ?? 'UNKNOWN')));
        if ($squareStatus !== 'COMPLETED') {
            $statusDetail = trim((string) ($payment['card_details']['status'] ?? ''));
            $message = $statusDetail !== ''
                ? 'Credit card payment was not completed. Square status: '.$squareStatus.' (card: '.$statusDetail.')'
                : 'Credit card payment was not completed. Square status: '.$squareStatus;

            throw ValidationException::withMessages([
                'source_id' => $this->squareApi->userFacingPaymentErrorMessage($message),
            ]);
        }

        $customerPayment->gateway_provider = 'square';
        $customerPayment->gateway_status = (string) ($payment['status'] ?? 'UNKNOWN');
        $customerPayment->gateway_reference_id = (string) ($payment['reference_id'] ?? 'payment:'.$customerPayment->id);
        $customerPayment->square_payment_id = (string) ($payment['id'] ?? null);
        $customerPayment->square_order_id = (string) ($payment['order_id'] ?? null);
        $customerPayment->square_location_id = (string) ($payment['location_id'] ?? null);
        $customerPayment->square_receipt_url = (string) ($payment['receipt_url'] ?? null);
        $customerPayment->square_card_brand = (string) ($payment['card_details']['card']['card_brand'] ?? null);
        $customerPayment->square_card_last4 = (string) ($payment['card_details']['card']['last_4'] ?? null);
        $customerPayment->square_paid_money_amount = (int) ($payment['amount_money']['amount'] ?? 0);
        $customerPayment->square_gateway_created_at = $this->squareDateTime($payment['created_at'] ?? null);
        $customerPayment->square_gateway_updated_at = $this->squareDateTime($payment['updated_at'] ?? null);
        $customerPayment->save();

        $invoice->allocations()->create([
            'payment_id' => $customerPayment->id,
            'allocated_amount' => $outstandingAmount,
        ]);

        $this->syncInvoiceState($invoice);
        $this->syncOrderState($order->fresh('invoice'));
        $this->allocator->allocateForOrder($order->fresh(['items.trackingEntries']));

        return (int) $customerPayment->id;
    }

    private function lockAndPrepareLines(Collection $lines): Collection
    {
        return $lines
            ->sortBy(fn ($line) => sprintf('%08d-%08d', (int) $line->product->id, (int) data_get($line, 'variant.id', 0)))
            ->map(function ($line) {
                /** @var Product $product */
                $product = Product::query()
                    ->whereKey($line->product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $product->isActive()) {
                    throw ValidationException::withMessages([
                        'cart' => 'One of the items in your cart is no longer available.',
                    ]);
                }

                $variant = null;
                if ($line->variant?->id) {
                    $variant = ProductVariant::query()
                        ->whereKey($line->variant->id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant instanceof ProductVariant || ! $variant->is_active) {
                        throw ValidationException::withMessages([
                            'cart' => 'One of the selected variants is no longer available.',
                        ]);
                    }
                }

                if ($line->variant?->id && ! $variant instanceof ProductVariant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Please reselect product options before checking out.',
                    ]);
                }

                $quantity = max(1, (int) $line->quantity);
                $actualInventory = $product->availableInventory($variant);
                $fulfilment = $this->resolveFulfilment($product, $quantity, $actualInventory, $variant);

                if (! $product->isPreorder($variant) && ! $product->allowsBackorder($variant) && $actualInventory !== null && $quantity > $actualInventory) {
                    $message = 'Only '.$actualInventory.' left for '.$product->displayTitle($variant).'.';

                    throw ValidationException::withMessages([
                        'cart' => $message,
                    ]);
                }

                $unitPrice = $product->priceForVariant($variant);
                $linePrice = round($unitPrice * $quantity, 2);

                return (object) [
                    'key' => $line->key,
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'display_title' => $product->displayTitle($variant),
                    'unit_price' => round($unitPrice, 2),
                    'tax_rate' => (float) $product->tax_rate,
                    'line_price' => $linePrice,
                    'line_gst' => $this->inclusiveTaxAmount($linePrice, (float) $product->tax_rate),
                    'unit_shipping_units' => $product->isPhysical() ? $product->shippingUnitsForVariant($variant) : 0.0,
                    'unit_min_satchel_rank' => $product->isPhysical() ? $product->minSatchelRankForVariant($variant) : null,
                    'box_only' => $product->isPhysical() ? $product->boxOnlyForVariant($variant) : false,
                    'unit_weight_grams' => $product->isPhysical() ? $product->weightGramsForVariant($variant) : null,
                    'available_now_inventory' => $actualInventory,
                    'available_now_quantity' => $fulfilment['available_now_quantity'],
                    'delayed_quantity' => $fulfilment['delayed_quantity'],
                    'delayed_fulfilment_type' => $fulfilment['delayed_fulfilment_type'],
                    'delayed_shipping_estimate' => $fulfilment['delayed_shipping_estimate'],
                    'is_preorder' => $product->isPreorder($variant),
                ];
            })
            ->values();
    }

    private function reserveInventoryForPreparedLine(object $line): int
    {
        $quantityToReserve = max(0, (int) ($line->available_now_quantity ?? 0));
        if ($quantityToReserve <= 0) {
            return 0;
        }

        if ($line->variant instanceof ProductVariant && $line->variant->tracksInventory()) {
            $available = max(0, (int) $line->variant->inventory_quantity);
            if ($available < $quantityToReserve) {
                throw ValidationException::withMessages([
                    'cart' => 'Not enough stock remains for '.$line->display_title.'.',
                ]);
            }

            $line->variant->inventory_quantity = $available - $quantityToReserve;
            $line->variant->save();

            return $quantityToReserve;
        }

        if ($line->product->tracksInventory()) {
            $available = max(0, (int) $line->product->inventory_quantity);
            if ($available < $quantityToReserve) {
                throw ValidationException::withMessages([
                    'cart' => 'Not enough stock remains for '.$line->display_title.'.',
                ]);
            }

            $line->product->inventory_quantity = $available - $quantityToReserve;
            $line->product->save();

            return $quantityToReserve;
        }

        return 0;
    }

    private function reserveInventoryForExistingOrder(StoreOrder $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            if ((int) $item->inventory_reserved_quantity > 0) {
                continue;
            }

            $quantity = $this->reservationQuantityForOrderItem($item);
            if ($quantity <= 0) {
                continue;
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first();
                if (! $variant instanceof ProductVariant || $variant->inventory_quantity === null) {
                    continue;
                }

                if ((int) $variant->inventory_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'status' => 'Unable to restore this order because the reserved variant stock is no longer available.',
                    ]);
                }

                $variant->inventory_quantity = (int) $variant->inventory_quantity - $quantity;
                $variant->save();
                $item->inventory_reserved_quantity = $quantity;
                $item->save();

                continue;
            }

            if (! $item->product_id) {
                continue;
            }

            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product instanceof Product || $product->inventory_quantity === null) {
                continue;
            }

            if ((int) $product->inventory_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'status' => 'Unable to restore this order because the reserved product stock is no longer available.',
                ]);
            }

            $product->inventory_quantity = (int) $product->inventory_quantity - $quantity;
            $product->save();
            $item->inventory_reserved_quantity = $quantity;
            $item->save();
        }
    }

    private function releaseInventoryReservations(StoreOrder $order): array
    {
        $sources = [];

        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $reserved = max(0, (int) $item->inventory_reserved_quantity);
            if ($reserved <= 0) {
                continue;
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first();
                if ($variant instanceof ProductVariant && $variant->inventory_quantity !== null) {
                    $variant->inventory_quantity = (int) $variant->inventory_quantity + $reserved;
                    $variant->save();
                    $sources[] = 'variant:'.$variant->id;
                }
            } elseif ($item->product_id) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
                if ($product instanceof Product && $product->inventory_quantity !== null) {
                    $product->inventory_quantity = (int) $product->inventory_quantity + $reserved;
                    $product->save();
                    $sources[] = 'product:'.$product->id;
                }
            }

            $item->inventory_reserved_quantity = 0;
            $item->save();
        }

        return array_values(array_unique($sources));
    }

    private function lockOrderItemForUpdate(StoreOrder $order, StoreOrderItem $item): StoreOrderItem
    {
        $lockedItem = StoreOrderItem::query()
            ->with(['order', 'trackingEntries'])
            ->whereKey($item->id)
            ->where('store_order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedItem instanceof StoreOrderItem) {
            throw ValidationException::withMessages([
                'item' => 'That item does not belong to this order.',
            ]);
        }

        return $lockedItem;
    }

    private function restoreInventoryQuantity(StoreOrderItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        if ($item->product_variant_id) {
            $variant = ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first();
            if ($variant instanceof ProductVariant && $variant->inventory_quantity !== null) {
                $variant->inventory_quantity = (int) $variant->inventory_quantity + $quantity;
                $variant->save();
            }

            return;
        }

        if (! $item->product_id) {
            return;
        }

        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
        if ($product instanceof Product && $product->inventory_quantity !== null) {
            $product->inventory_quantity = (int) $product->inventory_quantity + $quantity;
            $product->save();
        }
    }

    /**
     * @param  array<int, string>  $sources
     */
    private function allocateInventoryForSources(array $sources): void
    {
        foreach (array_values(array_unique($sources)) as $source) {
            [$type, $id] = explode(':', (string) $source, 2);
            $sourceId = (int) $id;
            if ($sourceId <= 0) {
                continue;
            }

            if ($type === 'variant') {
                $this->allocator->allocateForVariant(ProductVariant::query()->findOrFail($sourceId));

                continue;
            }

            if ($type === 'product') {
                $this->allocator->allocateForProduct(Product::query()->findOrFail($sourceId));
            }
        }
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeTrackingMode(mixed $value, mixed $trackingNumber = null, mixed $trackingUrl = null): string
    {
        $mode = trim((string) $value);
        if (in_array($mode, ['none', 'tracking_number'], true)) {
            return $mode;
        }

        return trim((string) $trackingNumber) !== '' || trim((string) $trackingUrl) !== ''
            ? 'tracking_number'
            : 'none';
    }

    private function resolveTrackingUrl(?string $carrier, ?string $trackingNumber): ?string
    {
        return ShopShippingSettings::resolveTrackingLink($carrier, $trackingNumber);
    }

    private function normalizeParcelNumber(mixed $value): ?int
    {
        $parcelNumber = max(0, (int) $value);

        return $parcelNumber > 0 ? $parcelNumber : null;
    }

    private function resolveUser(array $customer, ?User $authUser = null): ?User
    {
        if ($authUser instanceof User) {
            return $authUser;
        }

        $email = strtolower(trim((string) ($customer['billing_email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user instanceof User) {
            return $user;
        }

        $user = new User();
        $user->email = $email;
        $nameParts = preg_split('/\s+/', trim((string) ($customer['billing_name'] ?? ''))) ?: [];
        $user->firstname = $nameParts[0] ?? null;
        $user->surname = count($nameParts) > 1 ? trim(implode(' ', array_slice($nameParts, 1))) : null;
        $user->phone = $customer['billing_phone'] ?: null;
        $user->save();

        return $user;
    }

    private function updateUserProfileFromOrder(?User $user, array $customer, ?User $authUser = null): void
    {
        if (! $user instanceof User) {
            return;
        }

        $billingName = trim((string) ($customer['billing_name'] ?? ''));
        if (($authUser instanceof User) || $user->email_verified_at === null) {
            $nameParts = preg_split('/\s+/', $billingName) ?: [];
            if (($user->firstname ?? '') === '' && isset($nameParts[0])) {
                $user->firstname = $nameParts[0];
            }
            if (($user->surname ?? '') === '' && count($nameParts) > 1) {
                $user->surname = trim(implode(' ', array_slice($nameParts, 1)));
            }
            if (($user->phone ?? '') === '' && $customer['billing_phone'] !== '') {
                $user->phone = $customer['billing_phone'];
            }
            if ($customer['shipping_address'] !== '') {
                $user->shipping_address = $customer['shipping_address'];
                $user->shipping_address2 = $customer['shipping_address2'];
                $user->shipping_city = $customer['shipping_city'];
                $user->shipping_state = $customer['shipping_state'];
                $user->shipping_postcode = $customer['shipping_postcode'];
                $user->shipping_country = $customer['shipping_country'];
            }
            $user->save();
        }
    }

    private function queueOrderConfirmationEmail(?StoreOrder $order, ?User $actingUser = null): bool
    {
        if (! $order instanceof StoreOrder) {
            return false;
        }

        $recipient = strtolower(trim((string) $order->billing_email));
        if ($recipient === '') {
            return false;
        }

        $updated = StoreOrder::query()
            ->whereKey($order->id)
            ->whereNull('order_confirmation_emailed_at')
            ->update([
                'order_confirmation_emailed_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        if (! $freshOrder instanceof StoreOrder) {
            return false;
        }

        $attachments = $this->orderEmailAttachments($freshOrder, $freshOrder->isPaid());
        [$initiatedByEmail, $initiatedByName] = $this->mailInitiatorIdentity($actingUser);

        dispatch(new SendEmail(
            $recipient,
            (new StoreOrderConfirmation(
                $freshOrder,
                route('shop.order.tracking', ['accessToken' => $freshOrder->access_token]),
                $attachments,
            ))->from(
                $initiatedByEmail ?: config('mail.from.address'),
                $initiatedByName ?: null,
            )
        ))->onQueue('mail');

        return true;
    }

    private function queueOrderPaidEmail(?StoreOrder $order, ?User $actingUser = null): bool
    {
        if (! $order instanceof StoreOrder || ! $order->isPaid()) {
            return false;
        }

        $recipient = strtolower(trim((string) $order->billing_email));
        if ($recipient === '') {
            return false;
        }

        $updated = StoreOrder::query()
            ->whereKey($order->id)
            ->whereNull('order_paid_emailed_at')
            ->update([
                'order_paid_emailed_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        if (! $freshOrder instanceof StoreOrder) {
            return false;
        }

        $attachments = $this->orderEmailAttachments($freshOrder, true);
        [$initiatedByEmail, $initiatedByName] = $this->mailInitiatorIdentity($actingUser);

        dispatch(new SendEmail(
            $recipient,
            (new StoreOrderPaid(
                $freshOrder,
                route('shop.order.tracking', ['accessToken' => $freshOrder->access_token]),
                $attachments,
            ))->from(
                $initiatedByEmail ?: config('mail.from.address'),
                $initiatedByName ?: null,
            )
        ))->onQueue('mail');

        return true;
    }

    private function queueAdminOrderNotification(?StoreOrder $order, string $notificationType): void
    {
        if (! $order instanceof StoreOrder) {
            return;
        }

        $recipients = $this->adminOrderNotificationRecipients();
        if ($recipients === []) {
            return;
        }

        $freshOrder = $order->fresh(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon']);
        if (! $freshOrder instanceof StoreOrder) {
            return;
        }

        $adminUrl = route('admin.shop.order.edit', $freshOrder);

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new StoreOrderAdminNotification($freshOrder, $adminUrl, $notificationType)
            ))->onQueue('mail');
        }
    }

    private function queueAdminQuoteRequestNotification(?Quote $quote): void
    {
        if (! $quote instanceof Quote) {
            return;
        }

        $recipients = $this->adminOrderNotificationRecipients();
        if ($recipients === []) {
            return;
        }

        $freshQuote = $quote->fresh('user');
        if (! $freshQuote instanceof Quote) {
            return;
        }

        $adminUrl = route('admin.quote.edit', $freshQuote);

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new StoreQuoteRequestAdminNotification($freshQuote, $adminUrl)
            ))->onQueue('mail');
        }
    }

    private function adminOrderNotificationRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function orderEmailAttachments(StoreOrder $order, bool $includeReceipts): array
    {
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice || ! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return [];
        }

        if ((float) $order->total_amount <= 0.0001) {
            return [];
        }

        $attachments = [];
        $invoicePdf = $this->buildInvoicePdf($invoice)->output();
        if ($invoicePdf !== '') {
            $attachments[] = [
                'type' => 'invoice',
                'content' => $invoicePdf,
                'filename' => $this->invoicePdfFilename($invoice),
                'mime' => 'application/pdf',
            ];
        }

        if (! $includeReceipts) {
            return $attachments;
        }

        foreach ($this->receiptPaymentsForInvoice($invoice) as $payment) {
            $receiptPdf = $this->buildInvoicePaymentReceiptPdf($invoice, $payment)->output();
            if ($receiptPdf === '') {
                continue;
            }

            $attachments[] = [
                'type' => 'receipt',
                'content' => $receiptPdf,
                'filename' => $this->paymentReceiptPdfFilename($payment),
                'mime' => 'application/pdf',
            ];
        }

        return $attachments;
    }

    private function buildInvoicePdf(Invoice $invoice): PDF
    {
        $invoice->loadMissing('user', 'lines');
        $itemPages = $this->paginateInvoiceLineItemsForPdf($invoice);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'itemPages' => $itemPages,
            'adjustments' => collect(),
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function buildTaxAdjustmentPdf(Invoice $invoice, TaxAdjustment $adjustment): PDF
    {
        $invoice->loadMissing('user');
        $adjustment->loadMissing('lines');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.tax-adjustment', [
            'invoice' => $invoice,
            'adjustment' => $adjustment,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function buildInvoicePaymentReceiptPdf(Invoice $invoice, Payment $payment): PDF
    {
        $gatewayProcessedAtRaw = trim((string) ($payment->square_gateway_updated_at ?? $payment->square_gateway_created_at ?? ''));
        $gatewayProcessedAtLabel = '';
        if ($gatewayProcessedAtRaw !== '') {
            try {
                $gatewayProcessedAtLabel = Carbon::parse($gatewayProcessedAtRaw)->format('M j, Y g:i a');
            } catch (\Throwable) {
                $gatewayProcessedAtLabel = '';
            }
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'isRefund' => $payment->isRefund(),
            'receiptTitle' => $payment->isRefund() ? 'Refund Receipt' : 'Payment Receipt',
            'amountLabel' => $payment->isRefund() ? 'Amount Refunded' : 'Amount Paid',
            'receiptNumber' => (string) $payment->id,
            'invoiceNumber' => (string) $invoice->invoice_number,
            'customerName' => $invoice->user?->getName() ?: (string) ($invoice->billing_name ?? 'Customer'),
            'amountPaid' => (float) $payment->total_amount,
            'gstAmount' => abs((float) $payment->gst_amount),
            'paymentMethod' => Payment::paymentMethodLabel((string) ($payment->payment_method ?? Payment::PAYMENT_METHOD_CREDIT_CARD)),
            'paidOn' => $payment->received_on?->format('M j, Y g:i a') ?? now()->format('M j, Y g:i a'),
            'reference' => (string) ($payment->reference ?? ''),
            'gatewayProvider' => (string) ($payment->gateway_provider ?? ''),
            'gatewayStatus' => (string) ($payment->gateway_status ?? ''),
            'transactionId' => trim((string) ($payment->square_payment_id ?: $payment->gateway_reference_id)),
            'squareOrderId' => (string) ($payment->square_order_id ?? ''),
            'cardBrand' => (string) ($payment->square_card_brand ?? ''),
            'cardLast4' => (string) ($payment->square_card_last4 ?? ''),
            'squareReceiptUrl' => (string) ($payment->square_receipt_url ?? ''),
            'gatewayProcessedAt' => $gatewayProcessedAtLabel,
            'footerMessage' => $payment->isRefund() ? 'This receipt confirms the refund transaction.' : 'Thank you for your payment.',
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function receiptPaymentsForInvoice(Invoice $invoice): Collection
    {
        return $invoice->allocations()
            ->with('customerPayment')
            ->get()
            ->map(fn ($allocation) => $allocation->customerPayment)
            ->filter(fn ($payment) => $payment instanceof Payment && ! $payment->isRefund())
            ->unique(fn (Payment $payment) => (string) $payment->id)
            ->values();
    }

    private function paginateInvoiceLineItemsForPdf(Invoice $invoice): array
    {
        $items = $this->invoiceLineItemsForPayload($invoice);

        if (count($items) === 0) {
            return [[]];
        }

        $weights = array_map(fn (array $item) => $this->invoiceLineItemPdfWeight($item), $items);
        $firstLastCap = 9.0;
        $firstCap = 16.0;
        $middleCap = 26.0;
        $lastCap = 9.0;

        if (array_sum($weights) <= $firstLastCap) {
            return [$items];
        }

        $pages = [];
        $index = 0;

        [$firstPageItems, $index] = $this->packInvoiceLineItemsForPdf($items, $weights, $index, $firstCap);
        $pages[] = $firstPageItems;

        while ($this->remainingInvoiceLineItemPdfWeight($weights, $index) > $lastCap) {
            [$middlePageItems, $index] = $this->packInvoiceLineItemsForPdf($items, $weights, $index, $middleCap);
            $pages[] = $middlePageItems;
        }

        $pages[] = array_slice($items, $index);

        return $pages;
    }

    private function invoiceLineItemsForPayload(Invoice $invoice): array
    {
        return $invoice->lines->map(function (InvoiceLine $line): array {
            return [
                'description' => (string) $line->description,
                'notes' => (string) ($line->notes ?? ''),
                'quantity' => (float) $line->quantity,
                'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
                'tax_rate' => (float) $line->tax_rate,
                'line_total_ex_tax' => (float) $line->line_total_ex_tax,
            ];
        })->all();
    }

    private function packInvoiceLineItemsForPdf(array $items, array $weights, int $startIndex, float $capacity): array
    {
        $currentWeight = 0.0;
        $currentItems = [];
        $index = $startIndex;
        $count = count($items);

        while ($index < $count) {
            $nextWeight = $weights[$index] ?? 1.0;
            if (count($currentItems) > 0 && ($currentWeight + $nextWeight) > $capacity) {
                break;
            }

            $currentItems[] = $items[$index];
            $currentWeight += $nextWeight;
            $index++;
        }

        if (count($currentItems) === 0 && $startIndex < $count) {
            $currentItems[] = $items[$startIndex];
            $index = $startIndex + 1;
        }

        return [$currentItems, $index];
    }

    private function remainingInvoiceLineItemPdfWeight(array $weights, int $startIndex): float
    {
        $remaining = 0.0;
        $count = count($weights);

        for ($i = $startIndex; $i < $count; $i++) {
            $remaining += (float) ($weights[$i] ?? 0);
        }

        return $remaining;
    }

    private function invoiceLineItemPdfWeight(array $item): float
    {
        $notes = trim((string) ($item['notes'] ?? ''));
        if ($notes === '') {
            return 1.0;
        }

        $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        $lineCount = max(count($noteLines), 1);

        return 1.0 + min($lineCount * 0.35, 4.0);
    }

    private function invoicePdfFilename(Invoice $invoice): string
    {
        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $invoice->invoice_number);
        if (! is_string($safeInvoiceNumber) || $safeInvoiceNumber === '') {
            $safeInvoiceNumber = (string) $invoice->id;
        }

        return 'invoice-'.$safeInvoiceNumber.'.pdf';
    }

    private function paymentReceiptPdfFilename(Payment $payment): string
    {
        return ($payment->isRefund() ? 'refund-receipt-' : 'payment-receipt-').$payment->id.'.pdf';
    }

    private function storeQuoteLineItems(Collection $preparedLines, array $totals): array
    {
        $lineItems = $preparedLines->map(function ($line): array {
            $taxRate = (float) ($line->tax_rate ?? 0);
            $unitPriceIncTax = round((float) ($line->unit_price ?? 0), 2);
            $linePriceIncTax = round((float) ($line->line_price ?? 0), 2);
            $unitBreakdown = $this->inclusiveBreakdown($unitPriceIncTax, $taxRate);
            $lineBreakdown = $this->inclusiveBreakdown($linePriceIncTax, $taxRate);
            $variant = $line->variant ?? null;
            $variantId = $variant?->id ? (int) $variant->id : null;

            return [
                'kind' => 'product',
                'description' => trim((string) ($line->display_title ?? '')),
                'notes' => '',
                'quantity' => max(0, (int) ($line->quantity ?? 0)),
                'unit_price' => $unitBreakdown['unit_ex_tax'],
                'line_total' => $lineBreakdown['line_ex_tax'],
                'gst_applicable' => $taxRate > 0,
                'source_id' => (int) ($line->product->id ?? 0),
                'source_variant_id' => $variantId,
                'product_title' => (string) ($line->product->title ?? ''),
                'product_slug' => (string) ($line->product->slug ?? ''),
                'variant_name' => $line->product->variantDisplayName($variant),
                'product_sku' => (string) ($line->product->sku ?? ''),
                'variant_sku' => (string) ($variant instanceof ProductVariant ? $variant->sku : $line->product->sku),
                'product_type' => (string) ($line->product->product_type ?? ''),
                'store_context' => [
                    'line_key' => trim((string) ($line->key ?? '')),
                    'product_id' => (int) ($line->product->id ?? 0),
                    'variant_id' => $variantId,
                    'product_title' => (string) ($line->product->title ?? ''),
                    'product_slug' => (string) ($line->product->slug ?? ''),
                    'variant_name' => $line->product->variantDisplayName($variant),
                    'product_sku' => (string) ($line->product->sku ?? ''),
                    'variant_sku' => (string) ($variant instanceof ProductVariant ? $variant->sku : ''),
                    'product_type' => (string) ($line->product->product_type ?? ''),
                    'box_only' => (bool) ($line->box_only ?? false),
                    'is_preorder' => (bool) ($line->is_preorder ?? false),
                    'preorder_shipping_estimate' => $line->product->isPreorder($line->variant)
                        ? ($line->variant->preorder_shipping_estimate ?? $line->product->preorder_shipping_estimate)
                        : null,
                    'available_now_quantity' => (int) ($line->available_now_quantity ?? 0),
                    'delayed_quantity' => (int) ($line->delayed_quantity ?? 0),
                    'delayed_fulfilment_type' => $line->delayed_fulfilment_type ?? null,
                    'delayed_shipping_estimate' => $line->delayed_shipping_estimate ?? null,
                    'unit_shipping_units' => round((float) ($line->unit_shipping_units ?? 0), 2),
                    'unit_min_satchel_rank' => $line->unit_min_satchel_rank,
                    'unit_weight_grams' => $line->unit_weight_grams,
                    'tax_rate' => $taxRate,
                    'unit_price_inc_tax' => $unitPriceIncTax,
                    'line_price_inc_tax' => $linePriceIncTax,
                ],
            ];
        })->values()->all();

        if ((float) ($totals['discount'] ?? 0) > 0.0001) {
            $discountBreakdown = $this->inclusiveBreakdown(-1 * (float) $totals['discount'], (float) ($totals['discount_tax_rate'] ?? 0));

            $lineItems[] = [
                'kind' => 'discount',
                'description' => ($totals['coupon_code'] ?? null) !== null
                    ? 'Voucher '.$totals['coupon_code']
                    : 'Discount',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => $discountBreakdown['unit_ex_tax'],
                'line_total' => $discountBreakdown['line_ex_tax'],
                'gst_applicable' => ((float) ($totals['discount_tax_rate'] ?? 0)) > 0,
            ];
        }

        return $lineItems;
    }

    private function storeQuoteContextPayload(array $customer, array $totals): array
    {
        return [
            'customer' => $customer,
            'coupon_code' => $totals['coupon_code'] ?? null,
            'coupon_type' => $totals['coupon_type'] ?? null,
            'contains_digital' => (bool) ($totals['contains_digital'] ?? false),
            'contains_physical' => (bool) ($totals['contains_physical'] ?? false),
            'contains_preorder' => (bool) ($totals['contains_preorder'] ?? false),
            'contains_backorder' => (bool) ($totals['contains_backorder'] ?? false),
        ];
    }

    private function calculateQuoteSubtotal(array $lineItems): float
    {
        return round((float) collect($lineItems)->sum(fn (array $lineItem): float => (float) ($lineItem['line_total'] ?? 0)), 2);
    }

    private function calculateQuoteGst(array $lineItems): float
    {
        return round((float) collect($lineItems)->sum(function (array $lineItem): float {
            if (($lineItem['gst_applicable'] ?? true) !== true) {
                return 0.0;
            }

            return ((float) ($lineItem['line_total'] ?? 0)) * 0.10;
        }), 2);
    }

    private function normalizeCustomerPayload(array $payload): array
    {
        return [
            'billing_name' => trim((string) ($payload['billing_name'] ?? '')),
            'billing_email' => strtolower(trim((string) ($payload['billing_email'] ?? ''))),
            'billing_phone' => trim((string) ($payload['billing_phone'] ?? '')),
            'billing_company' => trim((string) ($payload['billing_company'] ?? '')),
            'shipping_name' => trim((string) ($payload['shipping_name'] ?? '')),
            'shipping_phone' => trim((string) ($payload['shipping_phone'] ?? '')),
            'shipping_address' => trim((string) ($payload['shipping_address'] ?? '')),
            'shipping_address2' => trim((string) ($payload['shipping_address2'] ?? '')),
            'shipping_city' => trim((string) ($payload['shipping_city'] ?? '')),
            'shipping_state' => trim((string) ($payload['shipping_state'] ?? '')),
            'shipping_postcode' => trim((string) ($payload['shipping_postcode'] ?? '')),
            'shipping_country' => trim((string) ($payload['shipping_country'] ?? 'Australia')) ?: 'Australia',
            'shipping_method_code' => trim((string) ($payload['shipping_method_code'] ?? '')),
            'consolidate_shipments' => (bool) ($payload['consolidate_shipments'] ?? false),
            'coupon_code' => Coupon::normalizeCode($payload['coupon_code'] ?? null),
            'preorder_acknowledged' => array_key_exists('preorder_acknowledged', $payload)
                ? (bool) $payload['preorder_acknowledged']
                : null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];
    }

    private function resolveFulfilment(Product $product, int $quantity, ?int $actualInventory, ?ProductVariant $variant = null): array
    {
        if ($product->isPreorder($variant)) {
            return [
                'available_now_quantity' => 0,
                'delayed_quantity' => $quantity,
                'delayed_fulfilment_type' => 'preorder',
                'delayed_shipping_estimate' => $variant->preorder_shipping_estimate ?? $product->preorder_shipping_estimate,
            ];
        }

        if ($product->allowsBackorder($variant) && $actualInventory !== null && $quantity > $actualInventory) {
            $availableNowQuantity = max(0, $actualInventory);

            return [
                'available_now_quantity' => $availableNowQuantity,
                'delayed_quantity' => max(0, $quantity - $availableNowQuantity),
                'delayed_fulfilment_type' => 'backorder',
                'delayed_shipping_estimate' => $product->backorderShippingEstimateLabel('Y-m-d', $variant),
            ];
        }

        return [
            'available_now_quantity' => $quantity,
            'delayed_quantity' => 0,
            'delayed_fulfilment_type' => null,
            'delayed_shipping_estimate' => null,
        ];
    }

    private function shippingLineDescription(array $totals): string
    {
        return trim((string) ($totals['shipping_method'] ?? 'Shipping')) ?: 'Shipping';
    }

    private function reservationQuantityForOrderItem(StoreOrderItem $item): int
    {
        $availableNowQuantity = max(0, (int) ($item->available_now_quantity ?? 0));
        $delayedQuantity = max(0, (int) ($item->delayed_quantity ?? 0));

        if ($availableNowQuantity > 0) {
            return $availableNowQuantity;
        }

        if ($delayedQuantity > 0) {
            return 0;
        }

        return max(0, (int) $item->quantity);
    }

    private function effectiveTaxRate(float $inclusiveAmount, float $taxAmount): float
    {
        $exclusiveAmount = round($inclusiveAmount - $taxAmount, 2);
        if ($inclusiveAmount <= 0 || $taxAmount <= 0 || $exclusiveAmount <= 0) {
            return 0.0;
        }

        return round($taxAmount / $exclusiveAmount, 4);
    }

    private function inclusiveTaxAmount(float $amount, float $taxRate): float
    {
        if ($amount <= 0 || $taxRate <= 0) {
            return 0.0;
        }

        return round($amount - ($amount / (1 + $taxRate)), 2);
    }

    private function inclusiveBreakdown(float $lineAmount, float $taxRate): array
    {
        $lineAmount = round($lineAmount, 2);
        $sign = $lineAmount < 0 ? -1 : 1;
        $absoluteAmount = abs($lineAmount);

        if ($absoluteAmount <= 0 || $taxRate <= 0) {
            return [
                'unit_ex_tax' => round($lineAmount, 2),
                'line_ex_tax' => round($lineAmount, 2),
                'tax_amount' => 0.0,
            ];
        }

        $lineExTax = round($absoluteAmount / (1 + $taxRate), 2) * $sign;
        $taxAmount = round($lineAmount - $lineExTax, 2);

        return [
            'unit_ex_tax' => $lineExTax,
            'line_ex_tax' => $lineExTax,
            'tax_amount' => $taxAmount,
        ];
    }

    private function proratedGst(Invoice $invoice, float $amount): float
    {
        $invoiceTotal = max(0.01, (float) $invoice->total_amount);
        $invoiceGst = max(0.0, (float) $invoice->gst_amount);

        return round(min($invoiceGst, ($amount / $invoiceTotal) * $invoiceGst), 2);
    }

    private function syncInvoiceState(Invoice $invoice): void
    {
        if ($invoice->outstandingAmount() <= 0.0001) {
            $invoice->status = Invoice::STATUS_PAID;
        } elseif ((string) $invoice->status === Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_ISSUED;
        }

        $invoice->save();
    }

    private function squareDateTime($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
