<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\InvoiceAllocationEditor;
use App\Services\Finance\PricingVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceAllocationController extends Controller
{
    public function edit(Request $request, Invoice $invoice, InvoiceAllocation $allocations): View
    {
        $data = $request->validate(['version_id' => 'nullable|integer|exists:finance_pricing_versions,id']);

        $retained = $allocations->context($invoice)['version']->id;
        PricingVersion::assertSelectable(isset($data['version_id']) ? (int) $data['version_id'] : null, (int) $retained);

        return view('admin.invoice.allocation', ['invoice' => $invoice, 'allocation' => $allocations->context($invoice, isset($data['version_id']) ? (int) $data['version_id'] : null)]);
    }

    public function store(Request $request, Invoice $invoice, InvoiceAllocation $allocations): JsonResponse|RedirectResponse
    {
        app(InvoiceAllocationEditor::class)->save($invoice, $request->all(), $request->user()->id);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cost centre allocation saved.', 'target' => 'invoice-cost-centres', 'html' => view($request->boolean('inline') ? 'admin.invoice.allocation-form' : 'admin.invoice.allocation-summary', ['inline' => true, 'invoice' => $invoice, 'allocation' => $allocations->context($invoice)])->render()]);
        }

        return redirect()->route('admin.invoice.edit', $invoice)->with('message', 'Cost centre allocation saved.')->with('message-type', 'success');
    }
}
