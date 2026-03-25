<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\QuoteCustomerResponseAdminNotification;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\User;
use App\Support\InvoiceDueDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class QuoteWorkflowService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly StoreOrderService $storeOrders,
    ) {}

    /**
     * @return array{quote: Quote, invoice: ?Invoice, order: ?StoreOrder, invoice_emailed: bool}
     */
    public function acceptByCustomer(Quote $quote, ?User $actingUser = null): array
    {
        $invoice = null;
        $order = null;
        $invoiceEmailed = false;

        DB::transaction(function () use ($quote, &$invoice, &$order): void {
            /** @var Quote $lockedQuote */
            $lockedQuote = Quote::query()
                ->with(['user', 'invoices'])
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedQuote->refreshLifecycleStatus();

            if (! $lockedQuote->canCustomerRespond()) {
                throw ValidationException::withMessages([
                    'quote' => 'This quote can no longer be accepted.',
                ]);
            }

            $lockedQuote->markAccepted();
            $lockedQuote->save();

            $shouldCreateInvoice = $lockedQuote->acceptanceCreatesOrder() || $lockedQuote->acceptanceEmailsInvoice();
            if (! $shouldCreateInvoice) {
                $quote->setRawAttributes($lockedQuote->getAttributes(), true);
                $quote->setRelation('user', $lockedQuote->user);

                return;
            }

            if ($lockedQuote->invoices->isNotEmpty()) {
                $invoice = $lockedQuote->invoices->sortByDesc('id')->first();
            } else {
                $invoice = $this->createInvoiceFromQuote($lockedQuote, $lockedQuote->acceptanceEmailsInvoice());
            }

            if ($lockedQuote->acceptanceCreatesOrder() && $invoice instanceof Invoice) {
                $order = $this->storeOrders->createOrderFromStoreQuote($lockedQuote, $invoice);
            }

            $quote->setRawAttributes($lockedQuote->getAttributes(), true);
            $quote->setRelation('user', $lockedQuote->user);
        });

        $freshQuote = $quote->fresh(['user', 'invoices']);
        if ($freshQuote instanceof Quote) {
            $quote = $freshQuote;
        }

        if ($invoice instanceof Invoice && $quote->acceptanceEmailsInvoice()) {
            $invoiceEmailed = $this->storeOrders->sendInvoiceDocumentBundleToCustomer(
                $invoice,
                $this->quoteRecipientEmail($quote),
                $this->quoteRecipientName($quote),
                $actingUser,
            );
        }

        $this->notifyAdminOfCustomerResponse($quote, Quote::STATUS_ACCEPTED, $invoice, $order, $invoiceEmailed);

        return [
            'quote' => $quote,
            'invoice' => $invoice,
            'order' => $order,
            'invoice_emailed' => $invoiceEmailed,
        ];
    }

    public function cancelByCustomer(Quote $quote): Quote
    {
        DB::transaction(function () use ($quote): void {
            /** @var Quote $lockedQuote */
            $lockedQuote = Quote::query()
                ->with('user')
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedQuote->refreshLifecycleStatus();

            if (! $lockedQuote->canCustomerRespond()) {
                throw ValidationException::withMessages([
                    'quote' => 'This quote can no longer be cancelled.',
                ]);
            }

            $lockedQuote->markCancelled();
            $lockedQuote->save();

            $quote->setRawAttributes($lockedQuote->getAttributes(), true);
            $quote->setRelation('user', $lockedQuote->user);
        });

        $freshQuote = $quote->fresh('user');
        if ($freshQuote instanceof Quote) {
            $quote = $freshQuote;
        }

        $this->notifyAdminOfCustomerResponse($quote, Quote::STATUS_CANCELLED);

        return $quote;
    }

    public function createInvoiceFromQuote(Quote $quote, bool $issueInvoice = false): Invoice
    {
        $quote->refreshLifecycleStatus();
        $quote->loadMissing('user', 'privateFinanceFiles');

        if ((string) ($quote->context_type ?? '') === Quote::CONTEXT_STORE_MANUAL_SHIPPING) {
            $existingInvoiceIds = $quote->invoices()->pluck('invoices.id');
            if ($existingInvoiceIds->isNotEmpty() && Invoice::query()->whereIn('id', $existingInvoiceIds)->whereHas('storeOrders')->exists()) {
                throw ValidationException::withMessages([
                    'quote' => 'This store quote has already been converted into a store order.',
                ]);
            }
        }

        $sourceLineItems = is_array($quote->line_items) ? array_values($quote->line_items) : [];
        $context = is_array($quote->context_payload ?? null) ? $quote->context_payload : [];
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];

        $invoice = new Invoice();
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->quote_id = $quote->id;
        $invoice->user_id = $quote->user_id;
        $invoice->billing_name = $this->quoteRecipientName($quote);
        $invoice->billing_email = $this->quoteRecipientEmail($quote);
        $invoice->billing_phone = trim((string) ($customer['billing_phone'] ?? '')) ?: null;
        $invoice->purchase_order_number = $quote->purchase_order_number;
        $invoice->status = $issueInvoice ? Invoice::STATUS_ISSUED : Invoice::STATUS_DRAFT;
        $invoice->issue_date = Carbon::now()->startOfDay();
        $invoice->issued_at = $issueInvoice ? now() : null;
        $invoice->due_date = InvoiceDueDate::fromIssueDate($invoice->issue_date);
        $invoice->subtotal_amount = $this->calculateSubtotal($sourceLineItems);
        $invoice->gst_amount = $this->calculateGst($sourceLineItems);
        $invoice->total_amount = round((float) $invoice->subtotal_amount + (float) $invoice->gst_amount, 2);

        $notes = trim((string) ($quote->notes ?? ''));
        $privateNotes = trim((string) ($quote->private_notes ?? ''));
        $quoteTitle = trim((string) ($quote->title ?? ''));
        $quoteDescription = trim((string) ($quote->description ?? ''));

        $prefix = [];
        if ($quoteTitle !== '') {
            $prefix[] = 'Quote Title: '.$quoteTitle;
        }
        if ($quoteDescription !== '') {
            $prefix[] = 'Quote Description: '.$quoteDescription;
        }
        if ($privateNotes !== '') {
            $prefix[] = 'Private Notes: '.$privateNotes;
        }

        $invoice->notes = trim(implode("\n", array_filter([
            implode("\n", $prefix),
            $notes,
        ])));

        $invoice->save();

        if (! $quote->accepted_at instanceof Carbon || (string) $quote->status !== Quote::STATUS_ACCEPTED) {
            $quote->markAccepted();
        } else {
            $quote->cancelled_at = null;
        }
        $quote->save();

        $invoice->syncPrivateFinanceFiles($quote->privateFinanceFiles()->pluck('finance_files.id')->all());
        foreach ($quote->files('private')->get() as $file) {
            $invoice->files('private')->attach($file->name, ['collection' => 'private']);
        }

        foreach ($sourceLineItems as $index => $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $quantity = (float) ($lineItem['quantity'] ?? 0);
            $unitPrice = (float) ($lineItem['unit_price'] ?? 0);
            $lineTotal = round($quantity * $unitPrice, 2);
            $taxRate = (($lineItem['gst_applicable'] ?? true) === true) ? 0.10 : 0.00;

            $invoice->lines()->create([
                'line_number' => $index + 1,
                'kind' => (string) ($lineItem['kind'] ?? 'generic'),
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

    public function quoteReviewExpiresAt(Quote $quote): Carbon
    {
        $expiresAt = $quote->expiresAt();
        if ($expiresAt instanceof Carbon) {
            return $expiresAt;
        }

        return Carbon::instance(now()->addDays(28)->endOfDay());
    }

    public function quoteReviewUrl(Quote $quote): string
    {
        return URL::temporarySignedRoute(
            'quote.magic.show',
            $this->quoteReviewExpiresAt($quote),
            ['quote' => $quote]
        );
    }

    public function quoteMagicActionUrl(Quote $quote, string $routeName): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            $this->quoteReviewExpiresAt($quote),
            ['quote' => $quote]
        );
    }

    public function quoteRecipientEmail(Quote $quote): ?string
    {
        $context = is_array($quote->context_payload) ? $quote->context_payload : [];
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $email = strtolower(trim((string) ($customer['billing_email'] ?? '')));
        if ($email === '') {
            $user = $quote->user;
            $email = strtolower(trim((string) ($user instanceof User ? $user->email : '')));
        }

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function quoteRecipientName(Quote $quote): string
    {
        $context = is_array($quote->context_payload) ? $quote->context_payload : [];
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $name = trim((string) ($customer['billing_name'] ?? ''));
        if ($name === '') {
            $user = $quote->user;
            $name = trim((string) ($user instanceof User ? $user->getName() : ''));
        }

        return $name !== '' ? $name : 'Customer';
    }

    /**
     * @return array<int, string>
     */
    public function adminNotificationRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function notifyAdminOfCustomerResponse(
        Quote $quote,
        string $responseStatus,
        ?Invoice $invoice = null,
        ?StoreOrder $order = null,
        bool $invoiceEmailed = false,
    ): void {
        $recipients = $this->adminNotificationRecipients();
        if ($recipients === []) {
            return;
        }

        $freshQuote = $quote->fresh(['user', 'invoices']);
        if ($freshQuote instanceof Quote) {
            $quote = $freshQuote;
        }

        $adminQuoteUrl = route('admin.quote.edit', $quote);
        $adminInvoiceUrl = $invoice instanceof Invoice ? route('admin.invoice.edit', $invoice) : null;
        $adminOrderUrl = $order instanceof StoreOrder ? route('admin.shop.order.edit', $order) : null;

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new QuoteCustomerResponseAdminNotification(
                    quote: $quote,
                    responseStatus: $responseStatus,
                    adminQuoteUrl: $adminQuoteUrl,
                    adminInvoiceUrl: $adminInvoiceUrl,
                    adminOrderUrl: $adminOrderUrl,
                    invoiceEmailed: $invoiceEmailed,
                )
            ))->onQueue('mail');
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
}
