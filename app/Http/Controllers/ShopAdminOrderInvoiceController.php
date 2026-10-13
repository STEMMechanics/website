<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Services\StoreOrderInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopAdminOrderInvoiceController extends Controller
{
    public function options(Request $request, StoreOrder $storeOrder, StoreOrderInvoiceService $invoices): JsonResponse
    {
        abort_unless($storeOrder->canAttachInvoice(), 409, 'This order is no longer open for additional items.');
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim((string) ($validated['q'] ?? ''));
        $available = $invoices->availableStoreInvoices($storeOrder)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('billing_name', 'like', '%'.$search.'%')
                        ->orWhere('billing_email', 'like', '%'.$search.'%')
                        ->orWhere('billing_company', 'like', '%'.$search.'%')
                        ->orWhere('purchase_order_number', 'like', '%'.$search.'%')
                        ->orWhereHas('lines', fn ($lineQuery) => $lineQuery->where('description', 'like', '%'.$search.'%'))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                        ->orWhereHas('user.primaryOrganisation', fn ($organisationQuery) => $organisationQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->with(['lines', 'allocations.customerPayment', 'user.primaryOrganisation'])
            ->orderByDesc('id')->limit($search === '' ? 5 : 8)->get();

        return response()->json(['invoices' => $available->map(function (Invoice $invoice) use ($invoices): array {
            $outstanding = $invoice->outstandingAmount();

            return [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'customer' => $invoice->billing_name ?: $invoice->user?->getName(),
                'company' => $invoice->billing_company ?: $invoice->user?->primaryOrganisation?->name,
                'date' => $invoice->issue_date?->format('j M Y'),
                'total' => money($invoice->total_amount),
                'payment' => $outstanding <= 0.0001 ? 'Paid' : money($outstanding).' outstanding',
                'items' => $invoice->lines->filter(fn ($line): bool => $invoices->productId($line) > 0)->pluck('description')->take(2)->implode(' · '),
            ];
        })]);
    }

    public function create(Request $request, StoreOrder $storeOrder, StoreOrderInvoiceService $invoices): JsonResponse|RedirectResponse
    {
        abort_unless($storeOrder->canAttachInvoice(), 409, 'This order is no longer open for additional items.');
        $validated = $request->validate(['invoice_id' => [$request->expectsJson() ? 'required' : 'nullable', 'integer']]);
        $selectedId = (int) ($validated['invoice_id'] ?? old('invoice_id', '0'));
        if (! $request->expectsJson()) {
            return redirect()->route('admin.shop.order.edit', [$storeOrder, 'attach_invoice' => $selectedId]);
        }
        $selectedInvoice = $invoices->availableInvoices($storeOrder)->with('lines')->findOrFail($selectedId);
        $productOptions = ['' => 'Invoice charge only (no item to fulfil)'];
        $lineSelections = [];
        $productIds = $selectedInvoice->lines->map(fn ($line): int => $invoices->productId($line))->filter()->all();
        $needsProductMapping = $selectedInvoice->lines->contains(fn ($line): bool => $line->kind === 'generic' && $invoices->productId($line) === 0 && (float) $line->quantity > 0);
        $products = Product::query()->sellable()->where(function ($query) use ($productIds, $needsProductMapping): void {
            $query->whereIn('id', $productIds);
            if ($needsProductMapping) {
                $query->orWhere(fn ($query) => $query->active());
            }
        })
            ->with('variants')->orderBy('title')->get();
        foreach ($products as $product) {
            $productOptions[$product->id.':0'] = $product->displayTitle();
            foreach ($product->variants as $variant) {
                $productOptions[$product->id.':'.$variant->id] = $product->displayTitle($variant).($variant->is_active ? '' : ' (inactive)');
            }
        }
        foreach ($selectedInvoice->lines ?? [] as $line) {
            $productId = $invoices->productId($line);
            $lineSelections[$line->id] = $productId ? $productId.':'.$invoices->variantId($line) : '';
        }

        return response()->json(['html' => view('admin.shop.order.partials.invoice-review', [
            'order' => $storeOrder,
            'selectedInvoice' => $selectedInvoice,
            'productOptions' => $productOptions,
            'lineSelections' => $lineSelections,
        ])->render()]);
    }

    public function store(Request $request, StoreOrder $storeOrder, StoreOrderInvoiceService $invoices): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'items' => ['required', 'array'],
            'items.*' => ['array:invoice_line_id,selection'],
            'items.*.invoice_line_id' => ['required', 'integer'],
            'items.*.selection' => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
            'shipment_plan' => ['required', 'in:same_shipment,separate_shipment'],
        ]);
        $mappings = collect($validated['items'])->map(function (array $row): array {
            [$productId, $variantId] = explode(':', (string) (($row['selection'] ?? '') ?: '0:0'));

            return ['invoice_line_id' => $row['invoice_line_id'], 'product_id' => (int) $productId, 'variant_id' => (int) $variantId];
        })->all();
        $invoices->attach($storeOrder, (int) $validated['invoice_id'], $mappings, $validated['shipment_plan'], $request->user());

        $redirect = redirect()->route('admin.shop.order.edit', $storeOrder)
            ->with('message', 'Additional invoice attached and its items added to the order.')
            ->with('message-title', 'Order updated')->with('message-type', 'success');

        return $request->expectsJson() ? response()->json(['redirect_url' => $redirect->getTargetUrl()]) : $redirect;
    }
}
