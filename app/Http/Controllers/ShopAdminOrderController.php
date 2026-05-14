<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCollection;
use App\Models\StoreOrderItemTracking;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\QuoteWorkflowService;
use App\Services\StoreOrderService;
use App\Support\InvoiceDueDate;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ShopAdminOrderController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly QuoteWorkflowService $quoteWorkflow,
    ) {}

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
            'quote',
            'invoice.quote',
            'invoice.allocations.customerPayment',
            'items.downloads.media',
            'items.product.hero',
            'items.variant',
            'items.collectionEntries.collectedBy',
            'items.trackingEntries',
            'items.cancellations.cancelledBy',
            'user',
            'coupon',
        ]);
        $orders->syncOrderState($order);

        return view('admin.shop.order.edit', [
            'order' => $order->fresh([
                'quote',
                'invoice.quote',
                'invoice.allocations.customerPayment',
                'items.downloads.media',
                'items.product.hero',
                'items.variant',
                'items.collectionEntries.collectedBy',
                'items.trackingEntries',
                'items.cancellations.cancelledBy',
                'user',
                'coupon',
            ]),
            'carrierSuggestions' => $this->carrierSuggestions(),
        ]);
    }

    public function pickListPdf(Request $request, StoreOrder $storeOrder, StoreOrderService $orders)
    {
        $order = $storeOrder->load([
            'user',
            'items.product.hero',
            'items.variant',
            'items.collectionEntries.collectedBy',
            'items.trackingEntries',
        ]);

        $pdf = $orders->buildStoreOrderPickListPdf($order);
        $filename = $orders->storeOrderPickListPdfFilename($order);
        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function sendQuote(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): RedirectResponse
    {
        $validated = $request->validate([
            'shipping_amount' => ['required', 'numeric', 'min:0'],
            'email_message' => ['nullable', 'string'],
        ]);

        if ((string) $storeOrder->status !== StoreOrder::STATUS_QUOTE_REQUESTED) {
            return redirect()->back()->withErrors([
                'shipping_amount' => 'This order is not waiting on a shipping quote.',
            ]);
        }

        $recipient = strtolower(trim((string) ($storeOrder->billing_email ?? '')));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withErrors([
                'shipping_amount' => 'The order needs a valid billing email address before a quote can be sent.',
            ]);
        }

        try {
            [$quote, $invoice] = DB::transaction(function () use ($storeOrder, $validated, $orders): array {
                $order = StoreOrder::query()
                    ->with(['items', 'invoice'])
                    ->whereKey($storeOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((string) $order->status !== StoreOrder::STATUS_QUOTE_REQUESTED) {
                    throw ValidationException::withMessages([
                        'shipping_amount' => 'This order is no longer waiting on a shipping quote.',
                    ]);
                }

                if ($order->invoice instanceof Invoice) {
                    throw ValidationException::withMessages([
                        'shipping_amount' => 'A quote invoice has already been created for this order.',
                    ]);
                }

                $quote = $this->createQuoteForOrder($order, (float) $validated['shipping_amount']);
                $invoice = $this->createInvoiceFromQuote($quote);

                $order->invoice_id = $invoice->id;
                $order->status = StoreOrder::STATUS_PENDING_PAYMENT;
                $order->shipping_amount = round((float) $validated['shipping_amount'], 2);
                $order->gst_amount = round((float) $invoice->gst_amount, 2);
                $order->total_amount = round((float) $invoice->total_amount, 2);
                $order->save();

                foreach ($order->items as $index => $item) {
                    $invoiceLine = $invoice->lines->firstWhere('line_number', $index + 1);
                    if (! $invoiceLine instanceof InvoiceLine) {
                        continue;
                    }

                    $item->invoice_line_id = $invoiceLine->id;
                    $item->save();
                }

                $orders->syncOrderState($order->fresh('invoice'));

                return [$quote, $invoice];
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        $emailMessage = trim((string) ($validated['email_message'] ?? ''));
        if ($emailMessage === '') {
            $emailMessage = $this->defaultQuoteEmailMessage($quote, $storeOrder);
        }

        $quoteDueDate = $quote->quote_date->copy()->addDays(28)->format('M j, Y');
        $pdfBinary = $this->buildQuotePdf($quote)->output();
        $reviewUrl = $this->quoteWorkflow->quoteReviewUrl($quote);
        [$initiatedByEmail, $initiatedByName] = $this->mailInitiatorIdentity($request->user());

        try {
            dispatch(new SendEmail(
                $recipient,
                new FinanceDocumentPdf(
                    documentType: 'quote',
                    documentNumber: $quote->quote_number,
                    recipientName: $storeOrder->billing_name ?: $recipient,
                    pdfContent: $pdfBinary,
                    pdfFilename: $this->getQuotePdfFilename($quote),
                    fullMessage: $emailMessage,
                    documentTotal: (float) $quote->total_amount,
                    documentOutstanding: (float) $quote->total_amount,
                    documentDue: $quoteDueDate,
                    initiatedByEmail: $initiatedByEmail,
                    initiatedByName: $initiatedByName,
                    actionUrl: $reviewUrl,
                    actionLabel: 'Review Quote',
                )
            ))->onQueue('mail');
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withErrors([
                'shipping_amount' => 'The quote was created, but the email could not be queued.',
            ]);
        }

        session()->flash('message', 'Quote created and emailed to '.$recipient.'.');
        session()->flash('message-title', 'Quote sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function update(Request $request, StoreOrder $storeOrder, StoreOrderService $orders): RedirectResponse
    {
        $statusRules = ['required', Rule::in(StoreOrder::STATUSES)];

        $validated = $request->validate([
            'status' => $statusRules,
            'notes' => ['nullable', 'string'],
            'public_notes' => ['nullable', 'string'],
            'item_actions_json' => ['nullable', 'string'],
            'send_update_email' => ['nullable', 'boolean'],
        ]);

        $itemActions = $this->parseQueuedItemActions($validated['item_actions_json'] ?? null);
        $sendUpdateEmail = $request->boolean('send_update_email', true);

        try {
            $itemSummary = $itemActions !== []
                ? $orders->applyOrderItemActions($storeOrder, $itemActions, $request->user(), true, ! $sendUpdateEmail)
                : null;

            $orders->updateOrderStatus(
                $storeOrder,
                (string) $validated['status'],
                $validated['notes'] ?? null,
                $validated['public_notes'] ?? null,
                true,
                ! $sendUpdateEmail,
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
            'tracking_mode' => ['nullable', Rule::in(['none', 'tracking_number'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'parcel_number' => ['required', 'integer', 'min:1'],
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

    public function storeItemCollection(Request $request, StoreOrder $storeOrder, StoreOrderItem $storeOrderItem, StoreOrderService $orders): RedirectResponse
    {
        $this->ensureOrderItemMatches($storeOrder, $storeOrderItem);

        $bag = 'collectionItem_'.$storeOrderItem->id;
        $validator = Validator::make($request->all(), [
            'collection_type' => ['required', Rule::in([
                StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                StoreOrderItemCollection::COLLECTION_TYPE_DELAYED,
            ])],
            'pickup_state' => ['required', Rule::in([
                StoreOrderItemCollection::PICKUP_STATE_READY,
                StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
            ])],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'collected_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator, $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        try {
            $orders->addOrderItemCollection($storeOrder, $storeOrderItem, $validator->validated(), $request->user(), true);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors(), $bag)
                ->withInput()
                ->withFragment('item-'.$storeOrderItem->id);
        }

        session()->flash('message', 'Pickup collection recorded.');
        session()->flash('message-title', 'Collection saved');
        session()->flash('message-type', 'success');

        return redirect()->back()->withFragment('item-'.$storeOrderItem->id);
    }

    private function ensureOrderItemMatches(StoreOrder $order, StoreOrderItem $item): void
    {
        abort_unless($item->store_order_id === $order->id, 404);
    }

    private function createQuoteForOrder(StoreOrder $order, float $shippingAmount): Quote
    {
        $shippingAmount = round(max(0, $shippingAmount), 2);
        $lineItems = $order->items
            ->map(function (StoreOrderItem $item): array {
                $taxRate = (float) ($item->tax_rate ?? 0.1);
                $unitPriceIncTax = (float) ($item->unit_price ?? 0);
                $unitPriceExTax = round($unitPriceIncTax / (1 + $taxRate), 2);

                return [
                    'kind' => 'product',
                    'description' => $item->displayTitle(),
                    'notes' => '',
                    'quantity' => max(1, (int) $item->quantity),
                    'unit_price' => $unitPriceExTax,
                    'line_total' => round($unitPriceExTax * max(1, (int) $item->quantity), 2),
                    'gst_applicable' => $taxRate > 0,
                    'store_context' => [
                        'product_id' => $item->product_id ? (int) $item->product_id : null,
                        'variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                        'product_title' => (string) ($item->product_title ?? ''),
                        'product_slug' => (string) ($item->product_slug ?? ''),
                        'variant_name' => (string) ($item->variant_name ?? ''),
                        'product_sku' => (string) ($item->product_sku ?? ''),
                        'variant_sku' => (string) ($item->variant_sku ?? ''),
                        'product_type' => (string) ($item->product_type ?? ''),
                        'box_only' => (bool) ($item->box_only ?? false),
                        'available_now_quantity' => (int) ($item->available_now_quantity ?? 0),
                        'delayed_quantity' => (int) ($item->delayed_quantity ?? 0),
                        'delayed_fulfilment_type' => $item->delayed_fulfilment_type,
                        'delayed_shipping_estimate' => $item->delayed_shipping_estimate,
                        'unit_shipping_units' => round((float) ($item->unit_shipping_units ?? 0), 3),
                        'unit_min_satchel_rank' => $item->unit_min_satchel_rank,
                        'unit_weight_grams' => $item->unit_weight_grams,
                        'tax_rate' => $taxRate,
                        'unit_price_inc_tax' => round($unitPriceIncTax, 2),
                        'line_price_inc_tax' => round((float) ($item->line_price_amount ?? 0), 2),
                    ],
                ];
            })
            ->values()
            ->all();

        if ($shippingAmount > 0.0001) {
            $shippingUnitPrice = round($shippingAmount / 1.1, 2);
            $lineItems[] = [
                'kind' => 'shipping',
                'description' => trim((string) ($order->shipping_method ?: 'Shipping')),
                'notes' => 'Shipping quoted manually for store order '.$order->order_number,
                'quantity' => 1,
                'unit_price' => $shippingUnitPrice,
                'line_total' => $shippingUnitPrice,
                'gst_applicable' => true,
            ];
        }

        $quote = new Quote();
        $quote->quote_number = $this->documentNumbers->nextQuoteNumber();
        $quote->user_id = $order->user_id;
        $quote->status = Quote::STATUS_OPEN;
        $quote->context_type = Quote::CONTEXT_STORE_MANUAL_SHIPPING;
        $quote->quote_date = Carbon::today();
        $quote->title = 'Shipping quote for store order '.$order->order_number;
        $quote->description = 'Requested from online checkout.';
        $quote->line_items = $lineItems;
        $quote->subtotal_amount = round((float) collect($lineItems)->sum('line_total'), 2);
        $quote->gst_amount = round((float) collect($lineItems)->sum(function (array $item): float {
            return $item['gst_applicable'] ? ((float) $item['line_total'] * 0.10) : 0.0;
        }), 2);
        $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);
        $quote->notes = trim((string) ($order->public_notes ?? '')) ?: null;
        $quote->private_notes = trim(implode("\n", array_filter([
            'Store order '.$order->order_number,
            (string) $order->notes,
        ]))) ?: null;
        $quote->context_payload = [
            'source_order_number' => (string) $order->order_number,
            'customer' => [
                'billing_name' => (string) $order->billing_name,
                'billing_email' => (string) $order->billing_email,
                'billing_phone' => (string) $order->billing_phone,
                'billing_company' => (string) $order->billing_company,
                'shipping_name' => (string) $order->shipping_name,
                'shipping_phone' => (string) $order->shipping_phone,
                'shipping_address' => (string) $order->shipping_address,
                'shipping_address2' => (string) $order->shipping_address2,
                'shipping_city' => (string) $order->shipping_city,
                'shipping_state' => (string) $order->shipping_state,
                'shipping_postcode' => (string) $order->shipping_postcode,
                'shipping_country' => (string) $order->shipping_country,
                'notes' => (string) $order->notes,
            ],
        ];
        $quote->setAcceptanceSettings(true, true);
        $quote->save();
        $order->quote_id = $quote->id;
        $order->save();

        return $quote->fresh();
    }

    private function createInvoiceFromQuote(Quote $quote): Invoice
    {
        $quote->loadMissing('user');
        $sourceLineItems = is_array($quote->line_items) ? array_values($quote->line_items) : [];

        $invoice = new Invoice();
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->quote_id = $quote->id;
        $invoice->user_id = $quote->user_id;
        $invoice->status = Invoice::STATUS_ISSUED;
        $invoice->issue_date = Carbon::now()->startOfDay();
        $invoice->issued_at = now();
        $invoice->due_date = InvoiceDueDate::fromIssueDate($invoice->issue_date);
        $invoice->subtotal_amount = round((float) $quote->subtotal_amount, 2);
        $invoice->gst_amount = round((float) $quote->gst_amount, 2);
        $invoice->total_amount = round((float) $quote->total_amount, 2);
        $invoice->notes = trim((string) ($quote->notes ?? ''));
        $invoice->save();

        foreach ($sourceLineItems as $index => $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $quantity = (float) ($lineItem['quantity'] ?? 0);
            $unitPrice = (float) ($lineItem['unit_price'] ?? 0);
            $lineTotal = round($quantity * $unitPrice, 2);
            $taxRate = $lineItem['gst_applicable'] === true ? 0.10 : 0.00;

            $invoice->lines()->create([
                'line_number' => $index + 1,
                'kind' => 'product',
                'description' => trim((string) ($lineItem['description'] ?? '')),
                'notes' => trim((string) ($lineItem['notes'] ?? '')),
                'details_json' => [],
                'quantity' => $quantity,
                'unit_price_ex_tax' => $unitPrice,
                'tax_rate' => $taxRate,
                'line_total_ex_tax' => $lineTotal,
                'tax_amount' => round($lineTotal * $taxRate, 2),
                'line_total_inc_tax' => round($lineTotal * (1 + $taxRate), 2),
            ]);
        }

        return $invoice->fresh('lines');
    }

    private function buildQuotePdf(Quote $quote): PDF
    {
        $quote->loadMissing('user');

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'Quote PDF generation requires barryvdh/laravel-dompdf.');
        }

        $itemPages = $this->paginateLineItemsForPdf($quote);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'itemPages' => $itemPages,
        ])->setOption([
            'enable_font_subsetting' => true,
        ]);
    }

    private function getQuotePdfFilename(Quote $quote): string
    {
        $safeQuoteNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $quote->quote_number);
        if (! is_string($safeQuoteNumber) || $safeQuoteNumber === '') {
            $safeQuoteNumber = (string) $quote->id;
        }

        return 'quote-'.$safeQuoteNumber.'.pdf';
    }

    private function defaultQuoteEmailMessage(Quote $quote, StoreOrder $order): string
    {
        $quoteUser = $quote->user;
        $nameSource = trim((string) ($order->billing_name ?: ($quoteUser instanceof User ? $quoteUser->getName() : '') ?: ''));
        $name = trim((string) strtok($nameSource, ' '));
        if ($name === '') {
            $name = $nameSource !== '' ? $nameSource : 'there';
        }

        return "Hi {$name},\n\nAttached is the shipping quote for store order {$order->order_number}. You can review it online and choose to accept it using the link below.\n\nIf you accept the quote, we'll proceed with processing your request.\n\n{{action}}";
    }

    private function mailInitiatorIdentity(mixed $actingUser): array
    {
        $email = trim((string) ($actingUser instanceof User ? $actingUser->email : ''));
        $name = trim((string) ($actingUser instanceof User ? $actingUser->getName() : ''));

        return [
            $email !== '' ? $email : null,
            $name !== '' ? $name : null,
        ];
    }

    private function paginateLineItemsForPdf(Quote $quote): array
    {
        $items = is_array($quote->line_items) ? array_values($quote->line_items) : [];

        if (count($items) === 0) {
            return [[]];
        }

        $weights = array_map(fn (array $item) => $this->lineItemPdfWeight($item), $items);
        $firstLastCap = 9.0;
        $firstCap = 16.0;
        $middleCap = 26.0;
        $lastCap = 9.0;

        $totalWeight = array_sum($weights);
        if ($totalWeight <= $firstLastCap) {
            return [$items];
        }

        $pages = [];
        $index = 0;

        [$firstPageItems, $index] = $this->packPage($items, $weights, $index, $firstCap);
        $pages[] = $firstPageItems;

        while ($this->remainingWeight($weights, $index) > $lastCap) {
            [$middlePageItems, $index] = $this->packPage($items, $weights, $index, $middleCap);
            $pages[] = $middlePageItems;
        }

        $pages[] = array_slice($items, $index);

        return $pages;
    }

    private function packPage(array $items, array $weights, int $startIndex, float $capacity): array
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

    private function remainingWeight(array $weights, int $startIndex): float
    {
        $remaining = 0.0;
        $count = count($weights);

        for ($i = $startIndex; $i < $count; $i++) {
            $remaining += (float) ($weights[$i] ?? 0);
        }

        return $remaining;
    }

    private function lineItemPdfWeight(array $item): float
    {
        $notes = trim((string) ($item['notes'] ?? ''));
        if ($notes === '') {
            return 1.0;
        }

        $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        $lineCount = max(count($noteLines), 1);

        return 1.0 + min($lineCount * 0.35, 4.0);
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
