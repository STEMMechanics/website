<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuoteController extends Controller
{
    public function __construct(private readonly DocumentNumberService $documentNumbers)
    {
    }

    public function index(Request $request)
    {
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
        return view('admin.quote.edit', [
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => Invoice::query()->with('user')->orderByDesc('issue_date')->orderByDesc('created_at')->get(),
            'nextQuoteNumber' => $this->documentNumbers->previewQuoteNumber(),
            'linkedInvoiceId' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $this->validateLinkedInvoiceSelection($validated['linked_invoice_id'] ?? null, $validated['user_id'] ?? null, null);
        $lineItems = $this->extractLineItems($request);

        if (count($lineItems) === 0) {
            throw ValidationException::withMessages([
                'line_items_json' => 'At least one line item is required.',
            ]);
        }

        $quote = new Quote();
        $quote->fill($validated);
        $quote->line_items = $lineItems;
        $quote->subtotal_amount = $this->calculateSubtotal($quote->line_items);
        $quote->gst_amount = $this->calculateGst($quote->line_items);
        $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);

        $quote->save();
        $this->syncLinkedInvoiceLink($quote, $validated['linked_invoice_id'] ?? null);
        $quote->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));

        session()->flash('message', 'Quote has been created');
        session()->flash('message-title', 'Quote created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.quote.index');
    }

    public function edit(Quote $quote)
    {
        $quote->loadMissing('privateFinanceFiles');
        $linkedInvoiceId = Invoice::query()->where('quote_id', $quote->id)->value('id');

        return view('admin.quote.edit', [
            'quote' => $quote,
            'users' => User::query()->orderBy('firstname')->orderBy('surname')->get(),
            'invoices' => Invoice::query()->with('user')->orderByDesc('issue_date')->orderByDesc('created_at')->get(),
            'linkedInvoiceId' => $linkedInvoiceId,
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $validated = $this->validateRequest($request, $quote);
        $this->validateLinkedInvoiceSelection($validated['linked_invoice_id'] ?? null, $validated['user_id'] ?? null, $quote);
        $lineItems = $this->extractLineItems($request);

        if (count($lineItems) === 0) {
            throw ValidationException::withMessages([
                'line_items_json' => 'At least one line item is required.',
            ]);
        }

        $quote->fill($validated);
        $quote->line_items = $lineItems;
        $quote->subtotal_amount = $this->calculateSubtotal($quote->line_items);
        $quote->gst_amount = $this->calculateGst($quote->line_items);
        $quote->total_amount = round((float) $quote->subtotal_amount + (float) $quote->gst_amount, 2);

        $quote->save();
        $this->syncLinkedInvoiceLink($quote, $validated['linked_invoice_id'] ?? null);
        $quote->syncPrivateFinanceFiles($this->parsePrivateFileIds($request->input('private_file_ids')));

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
        return $this->buildQuotePdf($quote)->stream($this->getQuotePdfFilename($quote));
    }

    public function accountIndex(Request $request)
    {
        $query = Quote::query()
            ->with('user')
            ->where('user_id', (string) auth()->id());

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

    public function accountPdf(Quote $quote)
    {
        $this->abortIfQuoteNotAccessible($quote);

        return $this->pdf($quote);
    }

    public function emailPdf(Request $request, Quote $quote): RedirectResponse
    {
        $quote->loadMissing('user');
        $emailMessage = trim((string) $request->input('email_message', ''));
        if ($emailMessage === '') {
            $emailMessage = $this->defaultQuoteEmailMessage($quote);
        }
        $quoteDueDate = $quote->quote_date->copy()->addDays(28)->format('M j, Y');

        $recipients = $this->resolveQuoteEmailRecipients($request, $quote);
        $ccRecipients = $this->resolveQuoteEmailCcRecipients($request);

        $pdfBinary = $this->buildQuotePdf($quote)->output();

        [$initiatedByEmail, $initiatedByName] = $this->getMailInitiatorIdentity();

        try {
            foreach ($recipients as $recipient) {
                $mailable = new FinanceDocumentPdf(
                    documentType: 'quote',
                    documentNumber: $quote->quote_number,
                    recipientName: $quote->user?->getName() ?? $recipient,
                    pdfContent: $pdfBinary,
                    pdfFilename: $this->getQuotePdfFilename($quote),
                    fullMessage: $emailMessage,
                    documentTotal: (float) $quote->total_amount,
                    documentOutstanding: (float) $quote->total_amount,
                    documentDue: $quoteDueDate,
                    initiatedByEmail: $initiatedByEmail,
                    initiatedByName: $initiatedByName,
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

        session()->flash('message', 'Quote PDF emailed to '.implode(', ', $recipients));
        session()->flash('message-title', 'Email sent');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function createInvoice(Quote $quote): RedirectResponse
    {
        $quote->loadMissing('user');
        $sourceLineItems = is_array($quote->line_items) ? array_values($quote->line_items) : [];

        $invoice = new Invoice();
        Invoice::query()->where('quote_id', $quote->id)->update(['quote_id' => null]);
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->quote_id = $quote->id;
        $invoice->user_id = $quote->user_id;
        $invoice->purchase_order_number = $quote->purchase_order_number;
        $invoice->status = 'draft';
        $invoice->issue_date = Carbon::now()->startOfDay();
        $invoice->due_date = Carbon::now()->startOfDay()->addDays(28);
        $invoice->subtotal_amount = $this->calculateSubtotal($sourceLineItems);
        $invoice->gst_amount = $this->calculateGst($sourceLineItems);
        $invoice->total_amount = round((float) $invoice->subtotal_amount + (float) $invoice->gst_amount, 2);

        $notes = trim((string) ($quote->notes ?? ''));
        $quoteTitle = trim((string) ($quote->title ?? ''));
        $quoteDescription = trim((string) ($quote->description ?? ''));

        $prefix = [];
        if ($quoteTitle !== '') {
            $prefix[] = 'Quote Title: '.$quoteTitle;
        }
        if ($quoteDescription !== '') {
            $prefix[] = 'Quote Description: '.$quoteDescription;
        }

        $invoice->notes = trim(implode("\n", array_filter([
            implode("\n", $prefix),
            $notes,
        ])));

        $invoice->save();
        $invoice->syncPrivateFinanceFiles($quote->privateFinanceFiles()->pluck('finance_files.id')->all());
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

        session()->flash('message', 'Quote copied to invoice');
        session()->flash('message-title', 'Invoice created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.invoice.edit', $invoice);
    }

    private function validateRequest(Request $request, ?Quote $quote = null): array
    {
        return $request->validate([
            'quote_number' => ['required', 'string', 'max:100', Rule::unique('quotes')->ignore($quote?->id)],
            'user_id' => ['required', 'exists:users,id'],
            'quote_date' => ['required', 'date'],
            'purchase_order_number' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'line_items_json' => ['nullable', 'string'],
            'private_file_ids' => ['nullable', 'string'],
            'linked_invoice_id' => [
                'nullable',
                'integer',
                'exists:invoices,id',
                Rule::unique('invoices', 'quote_id')->ignore($quote?->id, 'quote_id'),
            ],
        ]);
    }

    private function syncLinkedInvoiceLink(Quote $quote, mixed $linkedInvoiceId): void
    {
        $targetInvoiceId = is_numeric($linkedInvoiceId) ? (int) $linkedInvoiceId : 0;
        $targetInvoice = $targetInvoiceId > 0 ? Invoice::query()->find($targetInvoiceId) : null;

        Invoice::query()
            ->where('quote_id', $quote->id)
            ->when($targetInvoiceId > 0, fn ($builder) => $builder->where('id', '!=', $targetInvoiceId))
            ->update(['quote_id' => null]);

        if ($targetInvoice instanceof Invoice) {
            $targetInvoice->quote_id = $quote->id;
            $targetInvoice->save();
        }
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

    private function validateLinkedInvoiceSelection(mixed $linkedInvoiceId, mixed $userId, ?Quote $quote): void
    {
        $targetInvoiceId = is_numeric($linkedInvoiceId) ? (int) $linkedInvoiceId : 0;
        if ($targetInvoiceId <= 0) {
            return;
        }

        $normalizedUserId = trim((string) ($userId ?? ''));
        if ($normalizedUserId === '') {
            throw ValidationException::withMessages([
                'linked_invoice_id' => 'Select a linked user before linking an invoice.',
            ]);
        }

        $targetInvoice = Invoice::query()->find($targetInvoiceId);
        if (! $targetInvoice instanceof Invoice) {
            throw ValidationException::withMessages([
                'linked_invoice_id' => 'Selected invoice could not be found.',
            ]);
        }

        if ((string) ($targetInvoice->user_id ?? '') !== $normalizedUserId) {
            throw ValidationException::withMessages([
                'linked_invoice_id' => 'Linked invoice must belong to the same user as this quote.',
            ]);
        }

        if ($targetInvoice->quote_id !== null && (int) $targetInvoice->quote_id !== (int) ($quote->id ?? 0)) {
            throw ValidationException::withMessages([
                'linked_invoice_id' => 'Selected invoice is already linked to another quote.',
            ]);
        }
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

            $lineItems[] = [
                'description' => $description,
                'notes' => $notes,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'gst_applicable' => $gstApplicable,
            ];
        }

        return $lineItems;
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

    private function buildQuotePdf(Quote $quote): \Barryvdh\DomPDF\PDF
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
    }

    private function resolveQuoteEmailRecipients(Request $request, Quote $quote): array
    {
        $input = trim((string) $request->input('recipient_emails', ''));
        if ($input === '') {
            $input = trim((string) ($quote->user->email ?? ''));
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
        $nameSource = trim((string) ($quote->user?->getName() ?? $quote->billing_name ?? ''));
        $name = trim((string) strtok($nameSource, ' '));
        if ($name === '') {
            $name = $nameSource !== '' ? $nameSource : 'there';
        }

        $quoteNumber = trim((string) ($quote->quote_number ?? ''));

        return "Hi {$name},\n\nAttached is quote **{$quoteNumber}** for a workshop. Please don't hesitate to reach out if you have any questions.";
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
