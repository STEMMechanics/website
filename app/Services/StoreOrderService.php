<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\StoreOrderConfirmation;
use App\Mail\StoreOrderPaid;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StoreOrderService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly SquareApiService $squareApi,
        private readonly StoreShippingService $shipping,
        private readonly StoreCouponService $coupons,
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
        $this->queueOrderConfirmationEmail($order->fresh(['invoice', 'items.downloads.media', 'coupon']));

        return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
    }

    public function createAndChargeFromCart(Collection $lines, array $payload, string $sourceId, ?User $authUser = null): StoreOrder
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

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

        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            throw ValidationException::withMessages([
                'source_id' => 'Card details are required.',
            ]);
        }

        $customer = $this->normalizeCustomerPayload($payload);
        $user = $this->resolveUser($customer, $authUser);

        /** @var StoreOrder $order */
        $order = DB::transaction(function () use ($lines, $customer, $user, $locationId, $sourceId, $authUser): StoreOrder {
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

            $this->chargeLockedOrder($order, $invoice, $sourceId, $locationId, $authUser);

            return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
        });

        $this->updateUserProfileFromOrder($user, $customer, $authUser);
        $this->queueOrderPaidEmail($order->fresh(['invoice', 'items.downloads.media', 'coupon']));

        return $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
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
        $this->queueOrderPaidEmail($order->fresh(['invoice', 'items.downloads.media', 'coupon']));

        return $payment;
    }

    public function updateOrderStatus(StoreOrder $order, string $status, ?string $notes = null): StoreOrder
    {
        return DB::transaction(function () use ($order, $status, $notes): StoreOrder {
            /** @var StoreOrder $lockedOrder */
            $lockedOrder = StoreOrder::query()
                ->with('items')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $lockedOrder->status;
            if ($previousStatus !== StoreOrder::STATUS_CANCELLED && $status === StoreOrder::STATUS_CANCELLED) {
                $this->releaseInventoryReservations($lockedOrder);
            }

            if ($previousStatus === StoreOrder::STATUS_CANCELLED && $status !== StoreOrder::STATUS_CANCELLED) {
                $this->reserveInventoryForExistingOrder($lockedOrder);
            }

            $lockedOrder->status = $status;
            $lockedOrder->notes = trim((string) $notes) ?: null;
            if ($lockedOrder->status === StoreOrder::STATUS_FULFILLED) {
                $lockedOrder->fulfilled_at ??= now();
            }
            if ($lockedOrder->status !== StoreOrder::STATUS_FULFILLED) {
                $lockedOrder->fulfilled_at = null;
            }
            $lockedOrder->save();

            return $lockedOrder->fresh(['invoice', 'items.downloads.media', 'items.product.hero', 'items.variant', 'user', 'coupon']);
        });
    }

    public function syncOrderState(StoreOrder $order): void
    {
        $invoice = $order->relationLoaded('invoice') && $order->invoice instanceof Invoice
            ? $order->invoice
            : $order->invoice()->first();

        if (! $invoice instanceof Invoice) {
            return;
        }

        if ((string) $order->status === StoreOrder::STATUS_CANCELLED) {
            return;
        }

        $isPaid = $invoice->outstandingAmount() <= 0.0001;

        if (! $isPaid) {
            $order->status = StoreOrder::STATUS_PENDING_PAYMENT;
            $order->paid_at = null;
            if (! $order->contains_physical) {
                $order->fulfilled_at = null;
            }
            $order->save();

            return;
        }

        $order->paid_at ??= now();

        if ($order->contains_physical) {
            if ((string) $order->status !== StoreOrder::STATUS_FULFILLED) {
                $order->status = StoreOrder::STATUS_PROCESSING;
            }
        } else {
            $order->status = StoreOrder::STATUS_FULFILLED;
            $order->fulfilled_at ??= now();
        }

        $order->save();
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
            'shipping_tax_rate' => $shippingTaxRate,
            'discount_tax_rate' => $discountTaxRate,
            'coupon' => $couponEvaluation['coupon'] ?? null,
            'coupon_code' => $couponEvaluation['coupon_code'] ?? null,
            'coupon_type' => $couponEvaluation['discount_type'] ?? null,
            'shipping_method' => (string) ($shippingQuote['method'] ?? 'Shipping'),
            'shipping_package_summary' => $shippingQuote['package_summary'] ?? null,
            'shipping_zone' => null,
            'shipping_chargeable_weight_grams' => (int) ($shippingQuote['known_weight_grams'] ?? 0),
        ];
    }

    private function prepareCheckout(Collection $lines, array $customer, ?User $user): array
    {
        $preparedLines = $this->lockAndPrepareLines($lines);
        $shippingQuote = $this->shipping->quote($preparedLines, $customer['shipping_country']);

        if (! ($shippingQuote['can_checkout'] ?? true)) {
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

    private function createOrderRecords(Collection $preparedLines, array $customer, ?User $user, array $totals): StoreOrder
    {
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

        $order = new StoreOrder();
        $order->order_number = $this->documentNumbers->nextStoreOrderNumber();
        $order->access_token = Str::random(40);
        $order->user_id = $user?->id;
        $order->invoice_id = $invoice->id;
        $order->coupon_id = $totals['coupon']?->id;
        $order->status = $totals['total'] <= 0.0001
            ? ($totals['contains_physical'] ? StoreOrder::STATUS_PROCESSING : StoreOrder::STATUS_FULFILLED)
            : StoreOrder::STATUS_PENDING_PAYMENT;
        $order->contains_digital = $totals['contains_digital'];
        $order->contains_physical = $totals['contains_physical'];
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
        $order->shipping_package_summary = $totals['shipping_package_summary'];
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
        $order->paid_at = $totals['total'] <= 0.0001 ? now() : null;
        $order->fulfilled_at = $totals['total'] <= 0.0001 && ! $totals['contains_physical'] ? now() : null;
        $order->save();

        $lineNumber = 1;
        foreach ($preparedLines as $line) {
            $unitBreakdown = $this->inclusiveBreakdown((float) $line->unit_price, (float) $line->tax_rate);
            $lineBreakdown = $this->inclusiveBreakdown((float) $line->line_price, (float) $line->tax_rate);

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

            $reservedQuantity = $this->reserveInventoryForPreparedLine($line);

            $orderItem = new StoreOrderItem();
            $orderItem->store_order_id = $order->id;
            $orderItem->product_id = $line->product->id;
            $orderItem->product_variant_id = $line->variant?->id;
            $orderItem->invoice_line_id = $invoiceLine->id;
            $orderItem->product_title = (string) $line->product->title;
            $orderItem->product_slug = (string) $line->product->slug;
            $orderItem->variant_name = $line->variant?->name;
            $orderItem->product_sku = $line->product->sku;
            $orderItem->variant_sku = $line->variant?->sku;
            $orderItem->product_type = (string) $line->product->product_type;
            $orderItem->box_only = (bool) $line->box_only;
            $orderItem->quantity = (int) $line->quantity;
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

        if ($totals['shipping'] > 0.0001) {
            $shippingBreakdown = $this->inclusiveBreakdown($totals['shipping'], $totals['shipping_tax_rate']);
            $shippingDescription = $totals['shipping_package_summary'] !== null
                ? $totals['shipping_method'].' - '.$totals['shipping_package_summary']
                : $totals['shipping_method'];

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

        if ($totals['discount'] > 0.0001) {
            $discountBreakdown = $this->inclusiveBreakdown(-1 * $totals['discount'], $totals['discount_tax_rate']);
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $lineNumber,
                'kind' => 'discount',
                'description' => $totals['coupon_code'] !== null
                    ? 'Coupon '.$totals['coupon_code']
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

    private function chargeLockedOrder(StoreOrder $order, Invoice $invoice, string $sourceId, string $locationId, ?User $actingUser = null): int
    {
        $outstandingAmount = $invoice->outstandingAmount();
        if ($outstandingAmount <= 0.0001) {
            throw ValidationException::withMessages([
                'source_id' => 'This order has already been paid.',
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

                if ($product->hasVariants() && ! $variant instanceof ProductVariant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Please reselect product options before checking out.',
                    ]);
                }

                $quantity = max(1, (int) $line->quantity);
                $availableInventory = $product->availableInventory($variant);
                if ($availableInventory !== null && $quantity > $availableInventory) {
                    $message = $variant instanceof ProductVariant
                        ? 'Only '.$availableInventory.' left for '.$product->title.' - '.$variant->name.'.'
                        : 'Only '.$availableInventory.' left for '.$product->title.'.';

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
                    'display_title' => $variant instanceof ProductVariant
                        ? $product->title.' - '.$variant->name
                        : (string) $product->title,
                    'unit_price' => round($unitPrice, 2),
                    'tax_rate' => (float) $product->tax_rate,
                    'line_price' => $linePrice,
                    'line_gst' => $this->inclusiveTaxAmount($linePrice, (float) $product->tax_rate),
                    'unit_shipping_units' => $product->isPhysical() ? $product->shippingUnitsForVariant($variant) : 0.0,
                    'unit_min_satchel_rank' => $product->isPhysical() ? $product->minSatchelRankForVariant($variant) : null,
                    'box_only' => $product->isPhysical() ? $product->boxOnlyForVariant($variant) : false,
                    'unit_weight_grams' => $product->isPhysical() ? $product->weightGramsForVariant($variant) : null,
                ];
            })
            ->values();
    }

    private function reserveInventoryForPreparedLine(object $line): int
    {
        if ($line->variant instanceof ProductVariant && $line->variant->tracksInventory()) {
            $available = max(0, (int) $line->variant->inventory_quantity);
            if ($available < (int) $line->quantity) {
                throw ValidationException::withMessages([
                    'cart' => 'Not enough stock remains for '.$line->display_title.'.',
                ]);
            }

            $line->variant->inventory_quantity = $available - (int) $line->quantity;
            $line->variant->save();

            return (int) $line->quantity;
        }

        if ($line->product->tracksInventory()) {
            $available = max(0, (int) $line->product->inventory_quantity);
            if ($available < (int) $line->quantity) {
                throw ValidationException::withMessages([
                    'cart' => 'Not enough stock remains for '.$line->display_title.'.',
                ]);
            }

            $line->product->inventory_quantity = $available - (int) $line->quantity;
            $line->product->save();

            return (int) $line->quantity;
        }

        return 0;
    }

    private function reserveInventoryForExistingOrder(StoreOrder $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            if ((int) $item->inventory_reserved_quantity > 0) {
                continue;
            }

            $quantity = max(0, (int) $item->quantity);
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

    private function releaseInventoryReservations(StoreOrder $order): void
    {
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
                }
            } elseif ($item->product_id) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
                if ($product instanceof Product && $product->inventory_quantity !== null) {
                    $product->inventory_quantity = (int) $product->inventory_quantity + $reserved;
                    $product->save();
                }
            }

            $item->inventory_reserved_quantity = 0;
            $item->save();
        }
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

    private function queueOrderConfirmationEmail(?StoreOrder $order): void
    {
        if (! $order instanceof StoreOrder) {
            return;
        }

        $recipient = strtolower(trim((string) $order->billing_email));
        if ($recipient === '') {
            return;
        }

        $updated = StoreOrder::query()
            ->whereKey($order->id)
            ->whereNull('order_confirmation_emailed_at')
            ->update([
                'order_confirmation_emailed_at' => now(),
            ]);

        if ($updated !== 1) {
            return;
        }

        $freshOrder = $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
        if (! $freshOrder instanceof StoreOrder) {
            return;
        }

        dispatch(new SendEmail(
            $recipient,
            new StoreOrderConfirmation(
                $freshOrder,
                route('shop.order.show', ['storeOrder' => $freshOrder, 'accessToken' => $freshOrder->access_token]),
            )
        ))->onQueue('mail');
    }

    private function queueOrderPaidEmail(?StoreOrder $order): void
    {
        if (! $order instanceof StoreOrder || ! $order->isPaid()) {
            return;
        }

        $recipient = strtolower(trim((string) $order->billing_email));
        if ($recipient === '') {
            return;
        }

        $updated = StoreOrder::query()
            ->whereKey($order->id)
            ->whereNull('order_paid_emailed_at')
            ->update([
                'order_paid_emailed_at' => now(),
            ]);

        if ($updated !== 1) {
            return;
        }

        $freshOrder = $order->fresh(['invoice', 'items.downloads.media', 'coupon']);
        if (! $freshOrder instanceof StoreOrder) {
            return;
        }

        dispatch(new SendEmail(
            $recipient,
            new StoreOrderPaid(
                $freshOrder,
                route('shop.order.show', ['storeOrder' => $freshOrder, 'accessToken' => $freshOrder->access_token]),
            )
        ))->onQueue('mail');
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
            'coupon_code' => Coupon::normalizeCode($payload['coupon_code'] ?? null),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ];
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
