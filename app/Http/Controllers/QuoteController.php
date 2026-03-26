<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\QuoteWorkflowService;
use App\Services\StoreOrderService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class QuoteController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly StoreOrderService $storeOrders,
        private readonly QuoteWorkflowService $quoteWorkflow,
    ) {}

    public function index(Request $request)
    {
        Quote::expireOpenQuotes();

        $query = Quote::query()->with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('quote_number', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%');
                    });
            });
        }

        $quotes = $query->orderBy('quote_date', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('admin.quote.index', [
            'quotes' => $quotes,
        ]);
    }

    public function create()
    {
        return view('admin.quote.edit', array_merge($this->quoteEditorData(), [
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'nextQuoteNumber' => $this->documentNumbers->previewQuoteNumber(),
            'linkedInvoices' => collect(),
        ]));
    }

    public function store(Request $request)
    {
        $lineItems = $this->extractLineItems($request);
        $this->validateLineItems($lineItems);
        $validated = $this->normalizeValidatedQuoteData($request, $this->validateRequest($request), null, $lineItems);

        if (count($lineItems) === 0) {
            throw ValidationException::withMessages([
                'line_items_json' => 'At least one line item is required.',
            ]);
        }

        $quote = new Quote();
        $quote->fill($validated);
        $quote->line_items = $lineItems;
        $quote->setAcceptanceSettings(
            $request->boolean('acceptance_creates_order') && $this->lineItemsIncludeStoreProducts($lineItems),
            $request->boolean('acceptance_emails_invoice')
        );
        $quote->subtotal_amount = $this->calculateSubtotal($quote->line_items);
        $quote->gst_amount = $this->calculateGst($quote->line_items);
        $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);

        $quote->save();
        $quote->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));
        if ($request->has('private_files')) {
            $quote->updateFiles($request->input('private_files'), 'private');
        }

        session()->flash('message', 'Quote has been created');
        session()->flash('message-title', 'Quote created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.quote.index');
    }

    public function edit(Quote $quote)
    {
        $quote->refreshLifecycleStatus();
        $quote->loadMissing([
            'privateFinanceFiles',
            'invoices' => fn ($query) => $query->with('user')->orderByDesc('issue_date')->orderByDesc('created_at')->orderByDesc('id'),
            'storeOrders.invoice',
        ]);

        return view('admin.quote.edit', [
            'quote' => $quote,
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'linkedInvoices' => $quote->invoices,
        ] + $this->quoteEditorData($quote));
    }

    public function update(Request $request, Quote $quote)
    {
        $quote->refreshLifecycleStatus();
        $lineItems = $this->extractLineItems($request);
        $this->validateLineItems($lineItems);
        $validated = $this->normalizeValidatedQuoteData($request, $this->validateRequest($request, $quote), $quote, $lineItems);

        if (count($lineItems) === 0) {
            throw ValidationException::withMessages([
                'line_items_json' => 'At least one line item is required.',
            ]);
        }

        $quote->fill($validated);
        $quote->line_items = $lineItems;
        $quote->setAcceptanceSettings(
            $request->boolean('acceptance_creates_order') && $this->lineItemsIncludeStoreProducts($lineItems),
            $request->boolean('acceptance_emails_invoice')
        );
        $quote->subtotal_amount = $this->calculateSubtotal($quote->line_items);
        $quote->gst_amount = $this->calculateGst($quote->line_items);
        $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);

        $quote->save();
        $quote->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));
        if ($request->has('private_files')) {
            $quote->updateFiles($request->input('private_files'), 'private');
        }

        if ($request->boolean('save_and_email') && (string) $quote->status === Quote::STATUS_OPEN) {
            session()->flash('quote-email-open', true);
        }

        session()->flash('message', 'Quote has been updated');
        session()->flash('message-title', 'Quote updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.quote.edit', $quote);
    }

    public function destroy(Request $request, Quote $quote)
    {
        $quote->delete();

        session()->flash('message', 'Quote has been deleted');
        session()->flash('message-title', 'Quote deleted');
        session()->flash('message-type', 'danger');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.quote.index'),
            ]);
        }

        return redirect()->route('admin.quote.index');
    }

    public function pdf(Quote $quote)
    {
        $quote->refreshLifecycleStatus();

        return $this->buildQuotePdf($quote)->stream($this->getQuotePdfFilename($quote));
    }

    public function accountIndex(Request $request)
    {
        Quote::expireOpenQuotes();

        $query = Quote::query()
            ->with('user')
            ->where('user_id', (string) auth()->id())
            ->visibleToCustomer();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('quote_number', 'like', '%'.$search.'%');
            });
        }

        $quotes = $query->orderBy('quote_date', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('account.quotes', [
            'quotes' => $quotes,
        ]);
    }

    public function accountShow(Quote $quote): View
    {
        $quote->refreshLifecycleStatus();
        $this->abortIfQuoteNotAccessible($quote);
        $quote->loadMissing([
            'user',
            'invoices' => fn ($query) => $query->orderByDesc('issue_date')->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        $quoteViewData = $this->buildQuoteAccountViewData($quote);

        return view('account.quote-show', [
            'quote' => $quote,
            'acceptUrl' => route('account.quote.accept', $quote),
            'cancelUrl' => route('account.quote.cancel', $quote),
            'isMagicAccess' => false,
        ] + $quoteViewData);
    }

    public function accountPdf(Quote $quote)
    {
        $quote->refreshLifecycleStatus();
        $this->abortIfQuoteNotAccessible($quote);

        return $this->pdf($quote);
    }

    public function accountAccept(Request $request, Quote $quote): RedirectResponse
    {
        $quote->refreshLifecycleStatus();
        $this->abortIfQuoteNotAccessible($quote);

        try {
            $result = $this->quoteWorkflow->acceptByCustomer($quote, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('message', (string) collect($e->errors())->flatten()->first());
            session()->flash('message-title', 'Quote unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->route('account.quote.show', $quote);
        }

        $invoice = $result['invoice'] ?? null;
        $order = $result['order'] ?? null;
        $message = ($order instanceof \App\Models\StoreOrder && ($result['order_email_deferred'] ?? false))
            ? 'Quote accepted. Your order confirmation and invoice will be emailed shortly.'
            : (($order instanceof \App\Models\StoreOrder && ($result['invoice_emailed'] ?? false))
                ? 'Quote accepted. Your order confirmation has been emailed.'
                : (($invoice instanceof Invoice && ($result['invoice_emailed'] ?? false))
                    ? 'Quote accepted. Your invoice has been emailed.'
                    : 'Quote accepted. We have recorded your response.'));

        if (($result['order'] ?? null) instanceof \App\Models\StoreOrder) {
            $message .= ' The linked order is now available in your account.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Quote accepted');
        session()->flash('message-type', 'success');

        if ($request->boolean('accept_and_pay') && $invoice instanceof Invoice) {
            return redirect()->to(route('account.invoice.show', $invoice));
        }

        return redirect()->route('account.quote.show', $quote);
    }

    public function accountCancel(Quote $quote): RedirectResponse
    {
        $quote->refreshLifecycleStatus();
        $this->abortIfQuoteNotAccessible($quote);

        return $this->handleQuoteCancellation($quote, route('account.quote.show', $quote));
    }

    public function showByMagicLink(Request $request, Quote $quote): View|RedirectResponse
    {
        $quote->refreshLifecycleStatus();

        if (! $request->hasValidSignature()) {
            session()->flash('message', 'That quote link has expired or is invalid.');
            session()->flash('message-title', 'Link invalid');
            session()->flash('message-type', 'danger');

            return redirect()->route('index');
        }

        $quote->loadMissing(['user', 'invoices']);
        $quoteViewData = $this->buildQuoteAccountViewData($quote);

        return view('account.quote-show', [
            'quote' => $quote,
            'acceptUrl' => $this->quoteWorkflow->quoteMagicActionUrl($quote, 'quote.magic.accept'),
            'cancelUrl' => $this->quoteWorkflow->quoteMagicActionUrl($quote, 'quote.magic.cancel'),
            'isMagicAccess' => true,
        ] + $quoteViewData);
    }

    public function acceptByMagicLink(Request $request, Quote $quote): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $this->handleQuoteAcceptance(
            $quote,
            $this->quoteWorkflow->quoteReviewUrl($quote),
            false,
            $request->boolean('accept_and_pay')
        );
    }

    public function cancelByMagicLink(Request $request, Quote $quote): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $this->handleQuoteCancellation(
            $quote,
            $this->quoteWorkflow->quoteReviewUrl($quote)
        );
    }

    public function emailPdf(Request $request, Quote $quote): RedirectResponse
    {
        $quote->refreshLifecycleStatus();
        $quote->loadMissing('user');
        $emailMessage = trim((string) $request->input('email_message', ''));
        if ($emailMessage === '') {
            $emailMessage = $this->defaultQuoteEmailMessage($quote);
        }
        $quoteDueDate = $quote->quote_date->copy()->addDays(28)->format('M j, Y');

        $recipients = $this->resolveQuoteEmailRecipients($request, $quote);
        $ccRecipients = $this->resolveQuoteEmailCcRecipients($request);

        $pdfBinary = $this->buildQuotePdf($quote)->output();
        $reviewUrl = $this->quoteWorkflow->quoteReviewUrl($quote);

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();

        try {
            foreach ($recipients as $recipient) {
                $mailable = new FinanceDocumentPdf(
                    documentType: 'quote',
                    documentNumber: $quote->quote_number,
                    recipientName: $this->quoteWorkflow->quoteRecipientName($quote),
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
                );
                $allCcRecipients = $ccRecipients;
                if ($initiatedByEmail !== null) {
                    $allCcRecipients[] = $initiatedByEmail;
                }

                $normalizedCcRecipients = [];
                foreach ($allCcRecipients as $ccEmail) {
                    $normalizedCcRecipients[strtolower($ccEmail)] = $ccEmail;
                }

                foreach (array_values($normalizedCcRecipients) as $ccEmail) {
                    if (strcasecmp($ccEmail, $recipient) !== 0) {
                        $mailable->cc($ccEmail);
                    }
                }

                dispatch(new SendEmail($recipient, $mailable))->onQueue('mail');
            }
        } catch (Throwable $e) {
            report($e);

            session()->flash('message', 'Quote email failed: '.$e->getMessage());
            session()->flash('message-title', 'Email failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if ((string) $quote->status === Quote::STATUS_DRAFT) {
            $quote->status = Quote::STATUS_OPEN;
            $quote->save();
        }

        session()->flash('message', 'Quote PDF emailed to '.implode(', ', $recipients));
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function createInvoice(Quote $quote): RedirectResponse
    {
        $invoice = $this->quoteWorkflow->createInvoiceFromQuote($quote, false);

        if ($quote->hasStoreProductLines()) {
            $order = $this->storeOrders->createOrderFromStoreQuote($quote, $invoice);

            session()->flash('message', 'Quote converted to order and invoice');
            session()->flash('message-title', 'Store order created');
            session()->flash('message-type', 'success');

            return redirect()->route('admin.shop.order.edit', $order);
        }

        session()->flash('message', 'Quote copied to invoice');
        session()->flash('message-title', 'Invoice created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.invoice.edit', $invoice);
    }

    private function validateRequest(Request $request, ?Quote $quote = null): array
    {
        return $request->validate([
            'quote_number' => ['required', 'string', 'max:100', Rule::unique('quotes')->ignore($quote?->id)],
            'user_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::in(Quote::STATUSES)],
            'quote_date' => ['required', 'date'],
            'purchase_order_number' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'line_items_json' => ['nullable', 'string'],
            'private_file_ids' => ['nullable', 'string'],
        ]);
    }

    private function normalizeValidatedQuoteData(Request $request, array $validated, ?Quote $quote = null, array $lineItems = []): array
    {
        $contextPayload = is_array($quote?->context_payload) ? $quote->context_payload : [];
        $acceptance = is_array($contextPayload['acceptance'] ?? null) ? $contextPayload['acceptance'] : [];
        $acceptance['creates_order'] = $request->boolean('acceptance_creates_order') && $this->lineItemsIncludeStoreProducts($lineItems);
        $acceptance['emails_invoice'] = $request->boolean('acceptance_emails_invoice');
        $contextPayload['acceptance'] = $acceptance;
        $validated['context_payload'] = $contextPayload;
        $validated['private_notes'] = trim((string) $request->input('private_notes', '')) ?: null;

        return $validated;
    }

    /**
     * @return array<int, int>
     */
    private function parsePrivateFileIds(mixed $value): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($id) => is_numeric(trim($id)) ? (int) trim($id) : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function lineItemsIncludeStoreProducts(array $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem) || (string) ($lineItem['kind'] ?? 'custom') !== 'product') {
                continue;
            }

            if ((int) data_get($lineItem, 'store_context.product_id', 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function handleQuoteAcceptance(Quote $quote, string $redirectUrl, bool $isAccountAccess, bool $acceptAndPay = false): RedirectResponse
    {
        $forceAcceptAndPay = $quote->requiresAcceptancePayment();

        try {
            $result = $this->quoteWorkflow->acceptByCustomer($quote, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('message', (string) collect($e->errors())->flatten()->first());
            session()->flash('message-title', 'Quote unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->to($redirectUrl);
        }

        $invoice = $result['invoice'] ?? null;
        $order = $result['order'] ?? null;
        $message = ($order instanceof \App\Models\StoreOrder && ($result['order_email_deferred'] ?? false))
            ? 'Quote accepted. Your order confirmation and invoice will be emailed shortly.'
            : (($order instanceof \App\Models\StoreOrder && ($result['invoice_emailed'] ?? false))
                ? 'Quote accepted. Your order confirmation has been emailed.'
                : (($invoice instanceof \App\Models\Invoice && ($result['invoice_emailed'] ?? false))
                    ? 'Quote accepted. Your invoice has been emailed.'
                    : 'Quote accepted. We have recorded your response.'));

        if ($isAccountAccess && ($result['order'] ?? null) instanceof \App\Models\StoreOrder) {
            $message .= ' The linked order is now available in your account.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Quote accepted');
        session()->flash('message-type', 'success');

        if (($acceptAndPay || $forceAcceptAndPay) && $invoice instanceof Invoice) {
            if ($isAccountAccess) {
                return redirect()->route('account.invoice.show', $invoice);
            }

            return redirect()->to($this->quoteWorkflow->quoteInvoiceAccessUrl($invoice));
        }

        return redirect()->to($redirectUrl);
    }

    private function handleQuoteCancellation(Quote $quote, string $redirectUrl): RedirectResponse
    {
        try {
            $this->quoteWorkflow->cancelByCustomer($quote);
        } catch (ValidationException $e) {
            session()->flash('message', (string) collect($e->errors())->flatten()->first());
            session()->flash('message-title', 'Quote unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->to($redirectUrl);
        }

        session()->flash('message', 'Quote cancelled. We have recorded your response.');
        session()->flash('message-title', 'Quote cancelled');
        session()->flash('message-type', 'success');

        return redirect()->to($redirectUrl);
    }

    private function buildQuoteAccountViewData(Quote $quote): array
    {
        $linkedInvoice = $quote->invoices
            ->sortByDesc(function (Invoice $invoice): int {
                return (int) (
                    optional($invoice->issue_date)->timestamp
                    ?? optional($invoice->created_at)->timestamp
                    ?? $invoice->id
                );
            })
            ->first();

        $linkedInvoiceOutstanding = $linkedInvoice instanceof Invoice
            ? round((float) $linkedInvoice->outstandingAmount(), 2)
            : 0.0;
        $linkedInvoiceIsPayable = $linkedInvoice instanceof Invoice
            && $linkedInvoiceOutstanding > 0.0001
            && ! in_array((string) $linkedInvoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true);
        $quoteExpiresAt = $quote->expiresAt();

        return [
            'linkedInvoice' => $linkedInvoice,
            'linkedInvoiceOutstanding' => $linkedInvoiceOutstanding,
            'linkedInvoiceUrl' => $linkedInvoice instanceof Invoice ? route('account.invoice.show', $linkedInvoice) : null,
            'linkedInvoicePayUrl' => $linkedInvoiceIsPayable ? route('account.invoice.show', $linkedInvoice) : null,
            'quoteDueDate' => $quoteExpiresAt instanceof \Illuminate\Support\Carbon ? $quoteExpiresAt->format('M j, Y') : null,
            'quoteHasExpired' => $quote->isExpired(),
            'forceAcceptAndPay' => $quote->requiresAcceptancePayment(),
            'canAcceptAndPay' => $quote->canCustomerRespond() && (
                $linkedInvoiceIsPayable
                || $quote->acceptanceEmailsInvoice()
                || $quote->requiresAcceptancePayment()
            ),
        ];
    }

    private function quoteEditorData(?Quote $quote = null): array
    {
        return [
            'catalogProducts' => $this->quoteEditorProducts($quote),
        ];
    }

    private function quoteEditorProducts(?Quote $quote = null): array
    {
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('title')
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'slug' => (string) $product->slug,
                    'sku' => (string) ($product->sku ?? ''),
                    'base_option_name' => (string) $product->baseOptionName(),
                    'has_option_choices' => (bool) $product->hasOptionChoices(),
                    'price' => round((float) ($product->price ?? 0), 2),
                    'tax_rate' => round((float) ($product->tax_rate ?? 0), 4),
                    'product_type' => (string) ($product->product_type ?? ''),
                    'shipping_units' => round((float) ($product->shipping_units ?? 0), 2),
                    'min_satchel_rank' => $product->min_satchel_rank !== null ? (int) $product->min_satchel_rank : null,
                    'weight_grams' => $product->weight_grams !== null ? (int) $product->weight_grams : null,
                    'box_only' => (bool) ($product->box_only ?? false),
                    'summary' => trim((string) ($product->short_description ?: Str::limit(strip_tags((string) ($product->description ?? '')), 180, ''))),
                    'variants' => $product->variants->map(function ($variant): array {
                        return [
                            'id' => (int) $variant->id,
                            'name' => (string) $variant->displayName(),
                            'sku' => (string) ($variant->sku ?? ''),
                            'summary' => trim((string) ($variant->description ?? '')),
                        ];
                    })->values()->all(),
                ];
            })
            ->values();

        if ($quote instanceof Quote) {
            $referencedProductIds = collect(is_array($quote->line_items) ? $quote->line_items : [])
                ->map(function ($item): array {
                    $storeContext = is_array($item['store_context'] ?? null) ? $item['store_context'] : [];

                    return [
                        'product_id' => (int) ($item['source_id'] ?? $storeContext['product_id'] ?? 0),
                        'variant_id' => (int) ($item['source_variant_id'] ?? $storeContext['variant_id'] ?? 0),
                    ];
                })
                ->filter(fn (array $item): bool => $item['product_id'] > 0)
                ->values();

            $existingProductIds = $products->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $missingProductIds = $referencedProductIds
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => ! in_array($id, $existingProductIds, true))
                ->unique()
                ->values();

            if ($missingProductIds->isNotEmpty()) {
                $missingProducts = Product::query()
                    ->with(['variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
                    ->whereIn('id', $missingProductIds->all())
                    ->get()
                    ->map(function (Product $product): array {
                        return [
                            'id' => (int) $product->id,
                            'title' => (string) $product->title,
                            'slug' => (string) $product->slug,
                            'sku' => (string) ($product->sku ?? ''),
                            'base_option_name' => (string) $product->baseOptionName(),
                            'has_option_choices' => (bool) $product->hasOptionChoices(),
                            'price' => round((float) ($product->price ?? 0), 2),
                            'tax_rate' => round((float) ($product->tax_rate ?? 0), 4),
                            'product_type' => (string) ($product->product_type ?? ''),
                            'shipping_units' => round((float) ($product->shipping_units ?? 0), 2),
                            'min_satchel_rank' => $product->min_satchel_rank !== null ? (int) $product->min_satchel_rank : null,
                            'weight_grams' => $product->weight_grams !== null ? (int) $product->weight_grams : null,
                            'box_only' => (bool) ($product->box_only ?? false),
                            'summary' => trim((string) ($product->short_description ?: Str::limit(strip_tags((string) ($product->description ?? '')), 180, ''))),
                            'variants' => $product->variants->map(function (ProductVariant $variant): array {
                                return [
                                    'id' => (int) $variant->id,
                                    'name' => (string) $variant->displayName(),
                                    'sku' => (string) ($variant->sku ?? ''),
                                    'summary' => trim((string) ($variant->description ?? '')),
                                ];
                            })->values()->all(),
                        ];
                    })
                    ->values();

                $products = $products->merge($missingProducts)->values();
            }

            $referencedVariants = $referencedProductIds
                ->filter(fn (array $item): bool => $item['variant_id'] > 0)
                ->values();

            if ($referencedVariants->isNotEmpty()) {
                $variantIds = $referencedVariants->pluck('variant_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
                $variants = ProductVariant::query()
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->keyBy('id');

                $products = $products->map(function (array $product) use ($referencedProductIds, $variants): array {
                    $productId = (int) $product['id'];
                    $referencedVariantIds = $referencedProductIds
                        ->filter(fn (array $item): bool => $item['product_id'] === $productId)
                        ->pluck('variant_id')
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->unique()
                        ->values()
                        ->all();

                    if ($referencedVariantIds === []) {
                        return $product;
                    }

                    $existingVariantIds = array_map(
                        static fn (array $variant): int => (int) $variant['id'],
                        $product['variants']
                    );

                    foreach ($referencedVariantIds as $variantId) {
                        if (in_array((int) $variantId, $existingVariantIds, true)) {
                            continue;
                        }

                        $variant = $variants->get((int) $variantId);
                        if (! $variant instanceof ProductVariant) {
                            continue;
                        }

                        $product['variants'][] = [
                            'id' => (int) $variant->id,
                            'name' => (string) $variant->displayName(),
                            'sku' => (string) ($variant->sku ?? ''),
                            'summary' => trim((string) ($variant->description ?? '')),
                        ];
                    }

                    return $product;
                });
            }
        }

        return $products->sortBy('title')->values()->all();
    }

    private function extractLineItems(Request $request): array
    {
        $lineItemsJson = $request->input('line_items_json', '[]');

        if (! is_string($lineItemsJson) || trim($lineItemsJson) === '') {
            return [];
        }

        $decoded = json_decode($lineItemsJson, true);
        if (! is_array($decoded)) {
            return [];
        }

        $lineItems = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            $notes = trim((string) ($item['notes'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $gstApplicable = filter_var($item['gst_applicable'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($description === '' || $quantity <= 0) {
                continue;
            }

            $lineTotal = round($quantity * $unitPrice, 2);

            $lineItems[] = array_merge($item, [
                'description' => $description,
                'notes' => $notes,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'gst_applicable' => $gstApplicable,
            ]);
        }

        return $lineItems;
    }

    private function validateLineItems(array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem) || (string) ($lineItem['kind'] ?? 'custom') !== 'product') {
                continue;
            }

            if ((int) data_get($lineItem, 'store_context.product_id', 0) > 0) {
                continue;
            }

            throw ValidationException::withMessages([
                'line_items_json' => 'Each store product line must have a product selected.',
            ]);
        }
    }

    private function calculateSubtotal(array $lineItems): float
    {
        $subtotal = 0;

        foreach ($lineItems as $lineItem) {
            $subtotal += (float) ($lineItem['line_total'] ?? 0);
        }

        return round($subtotal, 2);
    }

    private function calculateGst(array $lineItems): float
    {
        $gst = 0;

        foreach ($lineItems as $lineItem) {
            if (($lineItem['gst_applicable'] ?? true) === true) {
                $gst += ((float) ($lineItem['line_total'] ?? 0)) * 0.10;
            }
        }

        return round($gst, 2);
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

    private function abortIfQuoteNotAccessible(Quote $quote): void
    {
        $user = auth()->user();
        abort_if(! $user, Response::HTTP_FORBIDDEN);

        if (! $user->isAdmin() && $quote->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $user->isAdmin() && ! $quote->isVisibleToCustomer()) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function resolveQuoteEmailRecipients(Request $request, Quote $quote): array
    {
        $input = trim((string) $request->input('recipient_emails', ''));
        if ($input === '') {
            $input = trim((string) ($this->quoteWorkflow->quoteRecipientEmail($quote) ?? ''));
        }

        if ($input === '') {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Add at least one recipient email address.',
            ]);
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'One or more email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        if (count($normalized) === 0) {
            throw ValidationException::withMessages([
                'recipient_emails' => 'Add at least one valid recipient email address.',
            ]);
        }

        return array_values($normalized);
    }

    private function defaultQuoteEmailMessage(Quote $quote): string
    {
        $nameSource = trim((string) $this->quoteWorkflow->quoteRecipientName($quote));
        $name = trim((string) strtok($nameSource, ' '));
        if ($name === '') {
            $name = $nameSource !== '' ? $nameSource : 'there';
        }

        $quoteNumber = trim((string) ($quote->quote_number ?? ''));
        $contextLabel = (string) ($quote->context_type ?? '') === Quote::CONTEXT_STORE_MANUAL_SHIPPING
            ? 'for your store items'
            : 'for your request';

        return "Hi {$name},\n\nAttached is quote **{$quoteNumber}** {$contextLabel}. You can review it online and choose to accept it using the link below.\n\nIf you accept the quote, we'll proceed with processing your request.\n\n{{action}}";
    }

    private function resolveQuoteEmailCcRecipients(Request $request): array
    {
        $input = trim((string) $request->input('cc_emails', ''));
        if ($input === '') {
            return [];
        }

        $parts = preg_split('/[;,]/', $input) ?: [];
        $normalized = [];
        $invalid = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        if (count($invalid) > 0) {
            throw ValidationException::withMessages([
                'cc_emails' => 'One or more CC email addresses are invalid. Use commas or semicolons to separate recipients.',
            ]);
        }

        return array_values($normalized);
    }

    private function getMailInitiatorIdentity(): array
    {
        $user = auth()->user();
        $email = trim((string) ($user->email ?? ''));
        $firstName = trim((string) ($user->firstname ?? ''));
        $surname = trim((string) ($user->surname ?? ''));
        $name = trim($firstName.' '.$surname);
        if ($name === '') {
            $name = trim((string) ($user?->getName() ?? ''));
        }

        return [
            $email !== '' ? $email : null,
            $name !== '' ? $name : null,
        ];
    }
}
